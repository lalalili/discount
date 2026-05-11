<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\Contracts\CouponRepositoryInterface;
use Discount\Kernel\DTOs\CouponData;
use Discount\Kernel\Engines\DefaultCouponApplicationService;
use Discount\Kernel\Enums\CouponAmountMode;
use Discount\Kernel\Enums\CouponKind;

final class InMemoryCouponRepositoryForPackageTest implements CouponRepositoryInterface
{
    /**
     * @param array<string, CouponData> $memberCoupons
     * @param array<string, CouponData> $promotionCoupons
     * @param array<string, bool> $usedMap
     */
    public function __construct(
        private array $memberCoupons = [],
        private array $promotionCoupons = [],
        private array $usedMap = [],
    ) {
    }

    public function findActiveByCode(string $code, CouponKind $kind): ?CouponData
    {
        return match ($kind) {
            CouponKind::Member    => $this->memberCoupons[$code] ?? null,
            CouponKind::Promotion => $this->promotionCoupons[$code] ?? null,
        };
    }

    public function hasUserUsed(string $code, int $userId): bool
    {
        return (bool) ($this->usedMap[$userId . ':' . $code] ?? false);
    }

    public function decrementInventory(string $code): bool
    {
        return true;
    }
}

function makePackageCouponCartContext(float $orderTotal): CartContext
{
    return new CartContext(
        orderTotal: $orderTotal,
        allAmount: $orderTotal,
        bookAmount: $orderTotal,
        ebookAmount: 0,
        specificProductsAmount: $orderTotal,
        hasBook: true,
        hasEbook: false,
        hasSpecificProducts: true,
    );
}

it('validates member fixed and rate coupon discounts', function (): void {
    $repository = new InMemoryCouponRepositoryForPackageTest(
        memberCoupons: [
            'MEM-FIXED' => new CouponData(
                code: 'MEM-FIXED',
                kind: CouponKind::Member,
                scope: 0,
                triggerAmount: null,
                amount: 100,
                amountMode: CouponAmountMode::Fixed,
                userId: 7,
            ),
            'MEM-RATE' => new CouponData(
                code: 'MEM-RATE',
                kind: CouponKind::Member,
                scope: 0,
                triggerAmount: null,
                amount: 0.8,
                amountMode: CouponAmountMode::Rate,
                userId: 7,
            ),
        ],
    );

    $service = new DefaultCouponApplicationService($repository);

    $fixed = $service->validate(
        CouponKind::Member,
        'MEM-FIXED',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    $rate = $service->validate(
        CouponKind::Member,
        'MEM-RATE',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    expect($fixed->eligible)->toBeTrue()
        ->and($fixed->discount)->toBe(100.0)
        ->and($fixed->finalTotal)->toBe(900.0)
        ->and($fixed->pricingTrace?->toArray()[0]['stage'])->toBe('coupon_validate')
        ->and($fixed->pricingTrace?->toArray()[0]['source'])->toBe('coupon')
        ->and($fixed->pricingTrace?->toArray()[0]['status'])->toBe('applied')
        ->and($fixed->pricingTrace?->toArray()[0]['code'])->toBe('MEM-FIXED')
        ->and($fixed->pricingTrace?->toArray()[0]['amount'])->toBe(100.0)
        ->and($fixed->pricingTrace?->toArray()[0]['final_total'])->toBe(900.0)
        ->and($rate->eligible)->toBeTrue()
        ->and($rate->discount)->toBe(200.0)
        ->and($rate->finalTotal)->toBe(800.0);
});

it('validates promotion fixed and rate coupon discounts', function (): void {
    $repository = new InMemoryCouponRepositoryForPackageTest(
        promotionCoupons: [
            'PROMO-FIXED' => new CouponData(
                code: 'PROMO-FIXED',
                kind: CouponKind::Promotion,
                scope: 0,
                triggerAmount: null,
                amount: 150,
                amountMode: CouponAmountMode::Fixed,
                limitQty: null,
                leftQty: null,
            ),
            'PROMO-RATE' => new CouponData(
                code: 'PROMO-RATE',
                kind: CouponKind::Promotion,
                scope: 0,
                triggerAmount: null,
                amount: 0.9,
                amountMode: CouponAmountMode::Rate,
                limitQty: 2,
                leftQty: 2,
            ),
        ],
    );

    $service = new DefaultCouponApplicationService($repository);

    $fixed = $service->validate(
        CouponKind::Promotion,
        'PROMO-FIXED',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    $rate = $service->validate(
        CouponKind::Promotion,
        'PROMO-RATE',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    expect($fixed->eligible)->toBeTrue()
        ->and($fixed->discount)->toBe(150.0)
        ->and($fixed->finalTotal)->toBe(850.0)
        ->and($rate->eligible)->toBeTrue()
        ->and($rate->discount)->toBe(100.0)
        ->and($rate->finalTotal)->toBe(900.0);
});

it('returns promotion failure reason codes for auth required used and out of stock', function (): void {
    $repository = new InMemoryCouponRepositoryForPackageTest(
        promotionCoupons: [
            'PROMO-USED' => new CouponData(
                code: 'PROMO-USED',
                kind: CouponKind::Promotion,
                scope: 0,
                triggerAmount: null,
                amount: 100,
                amountMode: CouponAmountMode::Fixed,
                limitQty: 10,
                leftQty: 10,
            ),
            'PROMO-EMPTY' => new CouponData(
                code: 'PROMO-EMPTY',
                kind: CouponKind::Promotion,
                scope: 0,
                triggerAmount: null,
                amount: 100,
                amountMode: CouponAmountMode::Fixed,
                limitQty: 1,
                leftQty: 0,
            ),
        ],
        usedMap: [
            '7:PROMO-USED' => true,
        ],
    );

    $service = new DefaultCouponApplicationService($repository);

    $authRequired = $service->validate(
        CouponKind::Promotion,
        'PROMO-USED',
        makePackageCouponCartContext(1000),
        new UserContext(null),
    );

    $used = $service->validate(
        CouponKind::Promotion,
        'PROMO-USED',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    $outOfStock = $service->validate(
        CouponKind::Promotion,
        'PROMO-EMPTY',
        makePackageCouponCartContext(1000),
        new UserContext(8),
    );

    expect($authRequired->eligible)->toBeFalse()
        ->and($authRequired->reasonCode)->toBe('AUTH_REQUIRED')
        ->and($authRequired->pricingTrace?->toArray()[0]['reason_code'])->toBe('AUTH_REQUIRED')
        ->and($used->eligible)->toBeFalse()
        ->and($used->reasonCode)->toBe('COUPON_ALREADY_USED')
        ->and($used->pricingTrace?->toArray()[0]['reason_code'])->toBe('COUPON_ALREADY_USED')
        ->and($outOfStock->eligible)->toBeFalse()
        ->and($outOfStock->reasonCode)->toBe('COUPON_OUT_OF_STOCK')
        ->and($outOfStock->pricingTrace?->toArray()[0]['reason_code'])->toBe('COUPON_OUT_OF_STOCK');
});

it('returns discount invalid when discount is greater than or equal to order total', function (): void {
    $repository = new InMemoryCouponRepositoryForPackageTest(
        memberCoupons: [
            'MEM-OVER' => new CouponData(
                code: 'MEM-OVER',
                kind: CouponKind::Member,
                scope: 0,
                triggerAmount: null,
                amount: 1200,
                amountMode: CouponAmountMode::Fixed,
                userId: 7,
            ),
        ],
    );

    $service = new DefaultCouponApplicationService($repository);

    $result = $service->validate(
        CouponKind::Member,
        'MEM-OVER',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    expect($result->eligible)->toBeFalse()
        ->and($result->reasonCode)->toBe('DISCOUNT_INVALID')
        ->and($result->pricingTrace?->toArray()[0]['reason_code'])->toBe('DISCOUNT_INVALID');
});

it('returns coupon not found when code does not exist', function (): void {
    $service = new DefaultCouponApplicationService(new InMemoryCouponRepositoryForPackageTest());

    $result = $service->validate(
        CouponKind::Member,
        'NOT-FOUND',
        makePackageCouponCartContext(1000),
        new UserContext(7),
    );

    expect($result->eligible)->toBeFalse()
        ->and($result->reasonCode)->toBe('COUPON_NOT_FOUND')
        ->and($result->pricingTrace?->toArray()[0]['reason_code'])->toBe('COUPON_NOT_FOUND');
});
