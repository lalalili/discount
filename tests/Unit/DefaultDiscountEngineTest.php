<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\Contexts\ProductContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\Engines\DefaultDiscountEngine;

it('uses exclusive price before other discounts', function (): void {
    $engine = new DefaultDiscountEngine();

    $result = $engine->price(
        new ProductContext(1000),
        new PromotionSet([
            new PromotionContext(type: 8, sort: 1, discountAmount: 499),
            new PromotionContext(type: 1, sort: 2, discountAmount: 0.8),
        ])
    );

    expect($result->price)->toBe(499.0);
});

it('applies configured stackable discounts in sort order', function (): void {
    setDiscountConfig([
        'event' => [
            'type_role_map' => [
                901 => 'stackable_discount',
            ],
            'priorities' => [
                'pricing' => [
                    'stackable_discount',
                ],
            ],
        ],
    ]);

    $engine = new DefaultDiscountEngine();

    $result = $engine->price(
        new ProductContext(1000),
        new PromotionSet([
            new PromotionContext(type: 901, sort: 2, discountAmount: 100),
            new PromotionContext(type: 901, sort: 1, discountAmount: 0.8),
        ])
    );

    expect($result->price)->toBe(700.0);
});

it('uses configured role mapping for custom event types', function (): void {
    setDiscountConfig([
        'event' => [
            'type_role_map' => [
                900 => 'exclusive_price',
                901 => 'stackable_discount',
            ],
            'priorities' => [
                'pricing' => [
                    'exclusive_price',
                    'stackable_discount',
                ],
            ],
        ],
    ]);

    $engine = new DefaultDiscountEngine();

    $result = $engine->price(
        new ProductContext(1000),
        new PromotionSet([
            new PromotionContext(type: 901, sort: 2, discountAmount: 0.8),
            new PromotionContext(type: 900, sort: 1, discountAmount: 680),
        ])
    );

    expect($result->price)->toBe(680.0);
});

it('applies custom pricing priority sequence from config', function (): void {
    setDiscountConfig([
        'event' => [
            'type_role_map' => [
                910 => 'single_discount',
                911 => 'stackable_discount',
            ],
            'priorities' => [
                'pricing' => [
                    'stackable_discount',
                    'single_discount',
                ],
            ],
        ],
    ]);

    $engine = new DefaultDiscountEngine();

    $result = $engine->price(
        new ProductContext(1000),
        new PromotionSet([
            new PromotionContext(type: 911, sort: 1, discountAmount: 0.9),
            new PromotionContext(type: 910, sort: 1, discountAmount: 0.8),
        ])
    );

    expect($result->price)->toBe(720.0);
});
