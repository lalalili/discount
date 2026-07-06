<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CouponContext;
use Lalalili\Discount\Contexts\ProductContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\Engines\DefaultCouponDiscountEngine;
use Lalalili\Discount\Engines\DefaultDiscountEngine;
use Lalalili\Discount\Enums\CouponAmountMode;
use Lalalili\Discount\Support\RoundingPolicy;

it('rounding 未設定時 RoundingPolicy 不改變數值', function (): void {
    expect(RoundingPolicy::hasRule('unit_price'))->toBeFalse()
        ->and(RoundingPolicy::apply(299.7, 'unit_price'))->toBe(299.7)
        ->and(RoundingPolicy::apply(299.7, 'coupon_discount'))->toBe(299.7);
});

it('RoundingPolicy 依規則收斂 floor、ceil 與 half_up', function (): void {
    setDiscountConfig([
        'rounding' => [
            'unit_price'      => ['precision' => 0, 'mode' => 'floor'],
            'coupon_discount' => ['precision' => 0, 'mode' => 'half_up'],
        ],
    ]);

    expect(RoundingPolicy::apply(299.7, 'unit_price'))->toBe(299.0)
        ->and(RoundingPolicy::apply(299.2, 'coupon_discount'))->toBe(299.0)
        ->and(RoundingPolicy::apply(299.5, 'coupon_discount'))->toBe(300.0);

    setDiscountConfig([
        'rounding' => ['unit_price' => ['precision' => 0, 'mode' => 'ceil']],
    ]);

    expect(RoundingPolicy::apply(299.2, 'unit_price'))->toBe(300.0);
});

it('coupon 引擎預設行為不變:Rate 裸 round、Fixed 不收斂', function (): void {
    resetDiscountConfig();

    $engine = new DefaultCouponDiscountEngine();

    $rate = $engine->discount(
        333,
        new CouponContext(scope: 0, triggerAmount: null, amount: 0.9, amountMode: CouponAmountMode::Rate)
    );
    $fixed = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 100.4, amountMode: CouponAmountMode::Fixed)
    );

    // 333 × 0.1 = 33.3 → 裸 round = 33
    expect($rate->discount)->toBe(33.0)
        ->and($fixed->discount)->toBe(100.4);
});

it('coupon 引擎設定 coupon_discount 規則後 Fixed 與 Rate 一致收斂', function (): void {
    setDiscountConfig([
        'rounding' => ['coupon_discount' => ['precision' => 0, 'mode' => 'half_up']],
    ]);

    $engine = new DefaultCouponDiscountEngine();

    $rate = $engine->discount(
        333,
        new CouponContext(scope: 0, triggerAmount: null, amount: 0.9, amountMode: CouponAmountMode::Rate)
    );
    $fixed = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 100.4, amountMode: CouponAmountMode::Fixed)
    );

    expect($rate->discount)->toBe(33.0)
        ->and($rate->finalTotal)->toBe(300.0)
        ->and($fixed->discount)->toBe(100.0)
        ->and($fixed->finalTotal)->toBe(900.0);
});

it('定價引擎預設不收斂,設定 unit_price 規則後每步 floor 收斂(含 stackable)', function (): void {
    resetDiscountConfig();

    $product = new ProductContext(listPrice: 333.0);
    $percentOff = new PromotionContext(type: 1, discountAmount: 0.9, sort: 1);

    // 預設:333 × 0.9 = 299.7(不收斂)
    expect((new DefaultDiscountEngine())->price($product, new PromotionSet([$percentOff]))->price)
        ->toBe(299.7);

    setDiscountConfig([
        'rounding' => ['unit_price' => ['precision' => 0, 'mode' => 'floor']],
        'event'    => [
            'type_role_map' => [1 => 'single_discount', 2 => 'stackable_discount'],
            'priorities'    => ['pricing' => ['single_discount', 'stackable_discount']],
        ],
    ]);

    // 設定後:floor(299.7) = 299
    expect((new DefaultDiscountEngine())->price($product, new PromotionSet([$percentOff]))->price)
        ->toBe(299.0);

    // stackable 每層收斂:floor(333×0.9)=299 → floor(299×0.9)=269
    $stackA = new PromotionContext(type: 2, discountAmount: 0.9, sort: 1);
    $stackB = new PromotionContext(type: 2, discountAmount: 0.9, sort: 2);

    expect((new DefaultDiscountEngine())->price($product, new PromotionSet([$stackA, $stackB]))->price)
        ->toBe(269.0);
});
