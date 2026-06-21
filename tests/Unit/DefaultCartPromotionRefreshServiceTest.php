<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CartLineContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\Contracts\GiftResolverInterface;
use Lalalili\Discount\DTOs\CartPromotionRefreshInput;
use Lalalili\Discount\Engines\DefaultCartPromotionRefreshService;

final class RefreshGiftResolverForPackageTest implements GiftResolverInterface
{
    /**
     * @var array<string, int>
     */
    public static array $map = [];

    public function resolveIdByCode(string $giftCode): ?int
    {
        return self::$map[$giftCode] ?? null;
    }
}

it('selects a shared group rebate for multiple cart lines', function (): void {
    $service = new DefaultCartPromotionRefreshService();

    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: cartContext(),
        lines: [
            new CartLineContext(id: 101, productId: 101, quantity: 1, unitPrice: 1000, attributes: ['prod_no' => 'P101']),
            new CartLineContext(id: 102, productId: 102, quantity: 1, unitPrice: 1000, attributes: ['prod_no' => 'P102']),
        ],
        promotionSetsByProductId: [
            101 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.8, eventId: 601, name: '2 books 20% off', rebateTriggerAmount: 2),
            ]),
            102 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.8, eventId: 601, name: '2 books 20% off', rebateTriggerAmount: 2),
            ]),
        ],
    ));

    expect($result->selectedGroupRebateEventIds)->toBe([101 => 601, 102 => 601])
        ->and($result->itemAdjustmentsByLineId[101][0]['value'])->toBe('-20%')
        ->and($result->itemAdjustmentsByLineId[102][0]['value'])->toBe('-20%')
        ->and($result->metadata['applied_count'])->toBe(2)
        ->and($result->metadata['skipped_count'])->toBe(0)
        ->and($result->totals['subtotal_before'])->toBe(2000.0)
        ->and($result->totals['subtotal_after_item_adjustments'])->toBe(1600.0);
});

it('keeps a self-qualified group rebate when the shared event has lower priority', function (): void {
    $service = new DefaultCartPromotionRefreshService();

    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: cartContext(),
        lines: [
            new CartLineContext(id: 101, productId: 101, quantity: 2, unitPrice: 1000),
            new CartLineContext(id: 102, productId: 102, quantity: 1, unitPrice: 1000),
        ],
        promotionSetsByProductId: [
            101 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.9, eventId: 601, name: 'Self', rebateTriggerAmount: 2),
                new PromotionContext(type: 6, sort: 3, rebateGetAmount: 0.8, eventId: 602, name: 'Shared', rebateTriggerAmount: 3),
            ]),
            102 => new PromotionSet([
                new PromotionContext(type: 6, sort: 3, rebateGetAmount: 0.8, eventId: 602, name: 'Shared', rebateTriggerAmount: 3),
            ]),
        ],
    ));

    expect($result->selectedGroupRebateEventIds[101])->toBe(601)
        ->and($result->selectedGroupRebateEventIds[102] ?? null)->toBeNull()
        ->and($result->itemAdjustmentsByLineId[101][0]['attributes']['event_id'])->toBe(601)
        ->and($result->skippedPromotions[0]['reason'])->toBe('not_selected')
        ->and($result->promotionDecisions[0]['status'])->toBe('applied')
        ->and($result->promotionDecisions[1]['status'])->toBe('skipped')
        ->and($result->promotionDecisions[1]['reason'])->toBe('not_selected');
});

it('builds repeatable cart rebate and gift total adjustments', function (): void {
    RefreshGiftResolverForPackageTest::$map = ['GIFT-1' => 501];
    setDiscountConfig([
        'cart' => [
            'gift_resolver' => RefreshGiftResolverForPackageTest::class,
        ],
    ]);

    $service = new DefaultCartPromotionRefreshService();

    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: cartContext(),
        lines: [
            new CartLineContext(id: 101, productId: 101, quantity: 2, unitPrice: 600, attributes: ['prod_no' => 'P101']),
        ],
        promotionSetsByProductId: [
            101 => new PromotionSet([
                new PromotionContext(
                    type: 4,
                    sort: 1,
                    rebateGetAmount: 100,
                    eventId: 401,
                    name: 'Every 500 off 100',
                    rebateTriggerAmount: 500,
                    repeatable: true,
                ),
                new PromotionContext(
                    type: 3,
                    sort: 2,
                    eventId: 301,
                    name: 'Gift',
                    giftTriggerAmount: 1000,
                    giftProductCode: 'GIFT-1',
                    repeatable: false,
                ),
            ]),
        ],
    ));

    expect($result->cartAdjustments)->toHaveCount(2)
        ->and($result->cartAdjustments[0]['type'])->toBe('rebate')
        ->and($result->cartAdjustments[0]['value'])->toBe('-200')
        ->and($result->cartAdjustments[0]['attributes']['sum_amount'])->toBe(1200.0)
        ->and($result->cartAdjustments[1]['type'])->toBe('gift')
        ->and($result->cartAdjustments[1]['attributes']['gift_quantity'])->toBe(1)
        ->and($result->cartAdjustments[1]['attributes']['gift_id'])->toBe(501)
        ->and($result->cartAdjustments[1]['attributes']['gift_code'])->toBe('GIFT-1')
        ->and($result->cartAdjustments[1]['attributes']['fulfillment'])->toBe('condition_only')
        ->and($result->metadata['applied_count'])->toBe(4)
        ->and($result->promotionDecisions)->toHaveCount(4);
});

it('marks cart gift fulfillment as add item when requested', function (): void {
    RefreshGiftResolverForPackageTest::$map = ['GIFT-1' => 501];
    setDiscountConfig([
        'cart' => [
            'gift_resolver' => RefreshGiftResolverForPackageTest::class,
        ],
    ]);

    $service = new DefaultCartPromotionRefreshService();

    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: cartContext(),
        lines: [
            new CartLineContext(id: 101, productId: 101, quantity: 1, unitPrice: 1000, attributes: ['prod_no' => 'P101']),
        ],
        promotionSetsByProductId: [
            101 => new PromotionSet([
                new PromotionContext(
                    type: 3,
                    sort: 1,
                    eventId: 301,
                    name: 'Gift',
                    giftTriggerAmount: 1000,
                    giftProductCode: 'GIFT-1',
                ),
            ]),
        ],
        giftFulfillment: 'add_item',
    ));

    expect($result->cartAdjustments[0]['attributes']['fulfillment'])->toBe('add_item');
});

function cartContext(): CartContext
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
