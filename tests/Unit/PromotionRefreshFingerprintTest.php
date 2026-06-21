<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CartLineContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\DTOs\CartPromotionRefreshInput;
use Lalalili\Discount\Support\PromotionRefreshFingerprint;

it('builds stable promotion versions from promotion ids sort and timestamps', function (): void {
    $fingerprint = new PromotionRefreshFingerprint();

    $first = $fingerprint->promotionVersion([
        101 => new PromotionSet([
            new PromotionContext(type: 1, sort: 2, eventId: 12, attributes: ['updated_at_timestamp' => 200]),
            new PromotionContext(type: 6, sort: 1, eventId: 10, attributes: ['updated_at_timestamp' => 100]),
        ]),
    ]);

    $second = $fingerprint->promotionVersion([
        101 => new PromotionSet([
            new PromotionContext(type: 6, sort: 1, eventId: 10, attributes: ['updated_at_timestamp' => 100]),
            new PromotionContext(type: 1, sort: 2, eventId: 12, attributes: ['updated_at_timestamp' => 200]),
        ]),
    ]);

    $changed = $fingerprint->promotionVersion([
        101 => new PromotionSet([
            new PromotionContext(type: 6, sort: 1, eventId: 10, attributes: ['updated_at_timestamp' => 101]),
            new PromotionContext(type: 1, sort: 2, eventId: 12, attributes: ['updated_at_timestamp' => 200]),
        ]),
    ]);

    expect($first)->toBe($second)
        ->and($changed)->not->toBe($first);
});

it('includes only whitelisted line attributes in refresh signatures', function (): void {
    $fingerprint = new PromotionRefreshFingerprint();
    $version = 'promotion-version';

    $base = $fingerprint->promotionRefreshSignature(
        lines: [
            new CartLineContext(
                id: 101,
                productId: 101,
                quantity: 1,
                unitPrice: 100,
                attributes: ['additionalPurchases' => 'yes', 'picture_url' => 'a.jpg'],
            ),
        ],
        giftFulfillment: 'add_item',
        promotionVersion: $version,
        lineAttributeKeys: ['additionalPurchases'],
    );

    $changedUntrackedAttribute = $fingerprint->promotionRefreshSignature(
        lines: [
            new CartLineContext(
                id: 101,
                productId: 101,
                quantity: 1,
                unitPrice: 100,
                attributes: ['additionalPurchases' => 'yes', 'picture_url' => 'b.jpg'],
            ),
        ],
        giftFulfillment: 'add_item',
        promotionVersion: $version,
        lineAttributeKeys: ['additionalPurchases'],
    );

    $changedTrackedAttribute = $fingerprint->promotionRefreshSignature(
        lines: [
            new CartLineContext(
                id: 101,
                productId: 101,
                quantity: 1,
                unitPrice: 100,
                attributes: ['additionalPurchases' => 'no', 'picture_url' => 'a.jpg'],
            ),
        ],
        giftFulfillment: 'add_item',
        promotionVersion: $version,
        lineAttributeKeys: ['additionalPurchases'],
    );

    expect($changedUntrackedAttribute)->toBe($base)
        ->and($changedTrackedAttribute)->not->toBe($base);
});

it('builds an input signature from refresh input data', function (): void {
    $input = new CartPromotionRefreshInput(
        cartContext: new CartContext(
            orderTotal: 0,
            allAmount: 0,
            bookAmount: 0,
            ebookAmount: 0,
            specificProductsAmount: 0,
            hasBook: false,
            hasEbook: false,
            hasSpecificProducts: false,
        ),
        lines: [
            new CartLineContext(id: 101, productId: 101, quantity: 2, unitPrice: 500),
        ],
        promotionSetsByProductId: [
            101 => new PromotionSet([
                new PromotionContext(type: 4, sort: 1, eventId: 400, attributes: ['updated_at_timestamp' => 123]),
            ]),
        ],
        giftFulfillment: 'condition_only',
    );

    expect((new PromotionRefreshFingerprint())->inputSignature($input))->toBeString()
        ->not->toBe('');
});
