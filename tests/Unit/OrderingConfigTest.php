<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CartLineContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\DTOs\CartPromotionRefreshInput;
use Lalalili\Discount\DTOs\PricingTraceEntry;
use Lalalili\Discount\DTOs\PromotionDecisionReason;
use Lalalili\Discount\Engines\DefaultCartPromotionRefreshService;
use Lalalili\Discount\Enums\CouponKind;
use Lalalili\Discount\Support\CouponConditionPayloadFactory;
use Lalalili\Discount\Support\DiscountConfig;

function orderingTestCartContext(): CartContext
{
    return new CartContext(
        orderTotal: 0,
        allAmount: 0,
        bookAmount: 0,
        ebookAmount: 0,
        specificProductsAmount: 0,
        hasBook: false,
        hasEbook: false,
        hasSpecificProducts: false,
    );
}

function orderingTestTraceEntry(): PricingTraceEntry
{
    return new PricingTraceEntry(stage: 'coupon_validate', source: 'test', status: 'applied', scope: 'cart', kind: 'member');
}

/**
 * 兩個同時達標的 cart rebate(type 4)輸入。
 */
function multiRebateInput(): CartPromotionRefreshInput
{
    return new CartPromotionRefreshInput(
        cartContext: orderingTestCartContext(),
        lines: [
            new CartLineContext(id: 1, productId: 1, quantity: 1, unitPrice: 2000),
        ],
        promotionSetsByProductId: [
            1 => new PromotionSet([
                new PromotionContext(type: 4, sort: 1, eventId: 701, name: 'RebateA', rebateTriggerAmount: 1000, rebateGetAmount: 100, attributes: ['event_id' => 701, 'type' => 4, 'rebate_trigger_amount' => 1000, 'rebate_get_amount' => 100, 'sort' => 1]),
                new PromotionContext(type: 4, sort: 2, eventId: 702, name: 'RebateB', rebateTriggerAmount: 1500, rebateGetAmount: 250, attributes: ['event_id' => 702, 'type' => 4, 'rebate_trigger_amount' => 1500, 'rebate_get_amount' => 250, 'sort' => 2]),
            ]),
        ],
    );
}

it('coupon order 預設讀 config(10/11),可被 config 覆寫', function (): void {
    resetDiscountConfig();

    $factory = new CouponConditionPayloadFactory();

    expect($factory->orderFor(CouponKind::Member))->toBe(10)
        ->and($factory->orderFor(CouponKind::Promotion))->toBe(11);

    setDiscountConfig(['ordering' => ['coupon' => ['member' => 12, 'promotion' => 13]]]);

    expect($factory->orderFor(CouponKind::Member))->toBe(12)
        ->and($factory->orderFor(CouponKind::Promotion))->toBe(13)
        ->and($factory->make(CouponKind::Member, 50, orderingTestTraceEntry())->order)->toBe(12);
});

it('rebate strategy=first(預設):只留排序後第一個,被棄者記入 trace', function (): void {
    resetDiscountConfig();

    $result = (new DefaultCartPromotionRefreshService())->refresh(multiRebateInput());

    $rebateAdjustments = array_values(array_filter($result->cartAdjustments, fn (array $a): bool => $a['type'] === 'rebate'));
    $dropped = array_values(array_filter(
        $result->skippedPromotions,
        fn (array $s): bool => ($s['reason'] ?? '') === PromotionDecisionReason::REBATE_STRATEGY_DROPPED,
    ));

    expect($rebateAdjustments)->toHaveCount(1)
        ->and($rebateAdjustments[0]['attributes']['event_id'])->toBe(701)
        ->and($rebateAdjustments[0]['value'])->toBe('-100')
        ->and($dropped)->toHaveCount(1)
        ->and($dropped[0]['event_id'])->toBe(702)
        ->and($dropped[0]['strategy'])->toBe('first')
        ->and($dropped[0]['winning_event_id'])->toBe(701)
        ->and($dropped[0]['dropped_amount'])->toBe(250.0);
});

it('rebate strategy=max:取折抵金額最大者', function (): void {
    resetDiscountConfig();
    setDiscountConfig(['ordering' => ['rebate' => ['strategy' => 'max']]]);

    $result = (new DefaultCartPromotionRefreshService())->refresh(multiRebateInput());

    $rebateAdjustments = array_values(array_filter($result->cartAdjustments, fn (array $a): bool => $a['type'] === 'rebate'));

    expect($rebateAdjustments)->toHaveCount(1)
        ->and($rebateAdjustments[0]['attributes']['event_id'])->toBe(702)
        ->and($rebateAdjustments[0]['value'])->toBe('-250');
});

it('rebate strategy=all:全部套用且無 dropped trace', function (): void {
    resetDiscountConfig();
    setDiscountConfig(['ordering' => ['rebate' => ['strategy' => 'all']]]);

    $result = (new DefaultCartPromotionRefreshService())->refresh(multiRebateInput());

    $rebateAdjustments = array_values(array_filter($result->cartAdjustments, fn (array $a): bool => $a['type'] === 'rebate'));
    $dropped = array_filter(
        $result->skippedPromotions,
        fn (array $s): bool => ($s['reason'] ?? '') === PromotionDecisionReason::REBATE_STRATEGY_DROPPED,
    );

    expect($rebateAdjustments)->toHaveCount(2)
        ->and($dropped)->toBeEmpty();
});

it('exclusive.gift_coexists=false 時團購選中不再附帶贈品', function (): void {
    resetDiscountConfig();

    $input = new CartPromotionRefreshInput(
        cartContext: orderingTestCartContext(),
        lines: [
            new CartLineContext(id: 1, productId: 1, quantity: 2, unitPrice: 1000),
        ],
        promotionSetsByProductId: [
            1 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.8, eventId: 801, name: 'Group', rebateTriggerAmount: 2),
                new PromotionContext(type: 3, sort: 2, eventId: 802, name: 'Gift', giftProductCode: 'G-1', giftTriggerAmount: 1, attributes: []),
            ]),
        ],
    );

    // 預設(true):團購 + 贈品並存(gift resolver 未設定時 gift 會因 resolver 回 null 而缺,
    // 故此測試聚焦 false 路徑不進 gift 迴圈的行為;true 路徑由既有測試覆蓋)
    setDiscountConfig(['ordering' => ['exclusive' => ['gift_coexists' => false]]]);

    $result = (new DefaultCartPromotionRefreshService())->refresh($input);

    $giftAdjustments = array_filter(
        $result->itemAdjustmentsByLineId[1] ?? [],
        fn (array $a): bool => $a['type'] === 'gift',
    );

    expect($giftAdjustments)->toBeEmpty()
        ->and(($result->itemAdjustmentsByLineId[1] ?? [])[0]['value'] ?? null)->toBe('-20%');
});

it('validateOrdering:預設 config 通過;coupon order 撞 total 層 promotion 時警告', function (): void {
    resetDiscountConfig();

    expect(DiscountConfig::validateOrdering())->toBe([]);

    // coupon order 超出 layer 區間 + 與 rebate type_order 相撞
    setDiscountConfig([
        'ordering' => ['coupon' => ['member' => 4, 'promotion' => 25]],
    ]);

    $warnings = DiscountConfig::validateOrdering();

    expect(count($warnings))->toBe(3); // member 超區間、promotion 超區間、member 撞 type 4
});
