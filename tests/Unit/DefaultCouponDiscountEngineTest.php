<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Engines\DefaultCouponDiscountEngine;
use Discount\Kernel\Enums\CouponAmountMode;

it('calculates fixed and rate discounts', function (): void {
    $engine = new DefaultCouponDiscountEngine();

    $fixed = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 100, amountMode: CouponAmountMode::Fixed)
    );

    $rate = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 0.8, amountMode: CouponAmountMode::Rate)
    );

    expect($fixed->valid)->toBeTrue()
        ->and($fixed->discount)->toBe(100.0)
        ->and($fixed->finalTotal)->toBe(900.0)
        ->and($rate->valid)->toBeTrue()
        ->and($rate->discount)->toBe(200.0)
        ->and($rate->finalTotal)->toBe(800.0);
});

it('uses auto mode inference for rate and fixed amounts', function (): void {
    $engine = new DefaultCouponDiscountEngine();

    $rate = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 0.9, amountMode: CouponAmountMode::Auto)
    );

    $fixed = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 100, amountMode: CouponAmountMode::Auto)
    );

    expect($rate->valid)->toBeTrue()
        ->and($rate->discount)->toBe(100.0)
        ->and($fixed->valid)->toBeTrue()
        ->and($fixed->discount)->toBe(100.0);
});

it('returns invalid when discount is greater than or equal to order total', function (): void {
    $engine = new DefaultCouponDiscountEngine();

    $result = $engine->discount(
        1000,
        new CouponContext(scope: 0, triggerAmount: null, amount: 1000, amountMode: CouponAmountMode::Fixed)
    );

    expect($result->valid)->toBeFalse()
        ->and($result->reasonCode)->toBe('DISCOUNT_INVALID');
});

it('returns invalid when order total is zero', function (): void {
    $engine = new DefaultCouponDiscountEngine();

    $result = $engine->discount(
        0,
        new CouponContext(scope: 0, triggerAmount: null, amount: 100, amountMode: CouponAmountMode::Fixed)
    );

    expect($result->valid)->toBeFalse()
        ->and($result->reasonCode)->toBe('DISCOUNT_INVALID');
});
