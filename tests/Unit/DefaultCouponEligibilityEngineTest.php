<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\Engines\DefaultCouponEligibilityEngine;

it('passes all-scope coupon without trigger amount', function (): void {
    $engine = new DefaultCouponEligibilityEngine();

    $result = $engine->validate(
        new CouponContext(scope: 0, triggerAmount: null, amount: 100),
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 1000,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: false,
            hasSpecificProducts: false,
        ),
        new UserContext(1),
    );

    expect($result->eligible)->toBeTrue()
        ->and($result->reason)->toBeNull();
});

it('fails when scope fallback is false and no trigger amount', function (): void {
    $engine = new DefaultCouponEligibilityEngine();

    $result = $engine->validate(
        new CouponContext(scope: 1, triggerAmount: null, amount: 100),
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 0,
            ebookAmount: 1000,
            specificProductsAmount: 0,
            hasBook: false,
            hasEbook: true,
            hasSpecificProducts: false,
        ),
        new UserContext(1),
    );

    expect($result->eligible)->toBeFalse()
        ->and($result->reason)->toBe('未達使用條件，請檢查折扣金額或使用門檻');
});

it('passes when trigger amount is met for scoped amount', function (): void {
    $engine = new DefaultCouponEligibilityEngine();

    $result = $engine->validate(
        new CouponContext(scope: 2, triggerAmount: 400, amount: 80),
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 200,
            ebookAmount: 500,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: true,
            hasSpecificProducts: false,
        ),
        new UserContext(1),
    );

    expect($result->eligible)->toBeTrue();
});

it('fails when discount amount is greater than or equal to order total', function (): void {
    $engine = new DefaultCouponEligibilityEngine();

    $result = $engine->validate(
        new CouponContext(scope: 0, triggerAmount: null, amount: 1000),
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 1000,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: false,
            hasSpecificProducts: false,
        ),
        new UserContext(1),
    );

    expect($result->eligible)->toBeFalse()
        ->and($result->reason)->toBe('折扣金額超過結帳金額，請加購商品後使用');
});
