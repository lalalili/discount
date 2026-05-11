<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\Contracts\GiftResolverInterface;
use Discount\Kernel\Engines\DefaultCartPromotionEngine;

final class MapGiftResolverForPackageTest implements GiftResolverInterface
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

it('builds discount, rebate, and gift adjustments', function (): void {
    MapGiftResolverForPackageTest::$map = ['GIFT-001' => 501];

    setDiscountConfig([
        'cart' => [
            'gift_resolver' => MapGiftResolverForPackageTest::class,
        ],
    ]);

    $engine = new DefaultCartPromotionEngine();
    $result = $engine->apply(
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 1000,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: false,
            hasSpecificProducts: false,
            productPrice: 1000,
        ),
        new PromotionSet([
            new PromotionContext(type: 1, eventId: 101, name: 'Single', discountAmount: 0.8),
            new PromotionContext(type: 4, eventId: 102, name: 'Rebate'),
            new PromotionContext(type: 3, eventId: 103, name: 'Gift', giftProductCode: 'GIFT-001'),
        ])
    );

    expect($result->adjustments)->toHaveCount(3)
        ->and($result->adjustments[0]['type'])->toBe('discount')
        ->and($result->adjustments[0]['value'])->toBe('-20%')
        ->and($result->adjustments[1]['type'])->toBe('rebate')
        ->and($result->adjustments[2]['type'])->toBe('gift')
        ->and($result->adjustments[2]['attributes']['gift_id'])->toBe(501);
});

it('prefers selected group rebate and only keeps gifts alongside it', function (): void {
    MapGiftResolverForPackageTest::$map = ['GIFT-010' => 510];

    setDiscountConfig([
        'cart' => [
            'gift_resolver' => MapGiftResolverForPackageTest::class,
        ],
    ]);

    $engine = new DefaultCartPromotionEngine();
    $result = $engine->apply(
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 1000,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: false,
            hasSpecificProducts: false,
            productPrice: 1000,
            selectedGroupRebateEventId: 601,
        ),
        new PromotionSet([
            new PromotionContext(type: 6, eventId: 601, name: 'Group', rebateGetAmount: 0.8),
            new PromotionContext(type: 1, eventId: 602, name: 'Single', discountAmount: 200),
            new PromotionContext(type: 3, eventId: 603, name: 'Gift', giftProductCode: 'GIFT-010'),
        ])
    );

    expect($result->adjustments)->toHaveCount(2)
        ->and($result->adjustments[0]['type'])->toBe('discount')
        ->and($result->adjustments[0]['value'])->toBe('-20%')
        ->and($result->adjustments[0]['attributes']['type'])->toBe(6)
        ->and($result->adjustments[1]['type'])->toBe('gift')
        ->and($result->adjustments[1]['attributes']['gift_id'])->toBe(510);
});

it('formats fixed price discount by product price difference', function (): void {
    $engine = new DefaultCartPromotionEngine();
    $result = $engine->apply(
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 1000,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: false,
            hasSpecificProducts: false,
            productPrice: 1000,
        ),
        new PromotionSet([
            new PromotionContext(type: 8, eventId: 701, name: 'Exclusive Price', discountAmount: 750),
        ])
    );

    expect($result->adjustments)->toHaveCount(1)
        ->and($result->adjustments[0]['type'])->toBe('discount')
        ->and($result->adjustments[0]['value'])->toBe('-250');
});

it('skips gift adjustment when gift cannot be resolved', function (): void {
    MapGiftResolverForPackageTest::$map = [];

    setDiscountConfig([
        'cart' => [
            'gift_resolver' => MapGiftResolverForPackageTest::class,
        ],
    ]);

    $engine = new DefaultCartPromotionEngine();
    $result = $engine->apply(
        new CartContext(
            orderTotal: 1000,
            allAmount: 1000,
            bookAmount: 1000,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: true,
            hasEbook: false,
            hasSpecificProducts: false,
            productPrice: 1000,
        ),
        new PromotionSet([
            new PromotionContext(type: 3, eventId: 801, name: 'Gift', giftProductCode: 'NOT-FOUND'),
        ])
    );

    expect($result->adjustments)->toBe([]);
});
