<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\UserContext;
use Lalalili\Discount\Contracts\CouponRepositoryInterface;
use Lalalili\Discount\DTOs\CouponData;
use Lalalili\Discount\DTOs\PricingTraceEntry;
use Lalalili\Discount\Engines\DefaultCouponApplicationService;
use Lalalili\Discount\Enums\CouponKind;
use Lalalili\Discount\Support\CouponConditionPayloadFactory;

final class FreeShippingCouponRepository implements CouponRepositoryInterface
{
    /**
     * @param array<string, CouponData> $coupons
     * @param array<string, bool> $usedMap
     */
    public function __construct(
        private array $coupons = [],
        private array $usedMap = [],
    ) {
    }

    public function findActiveByCode(string $code, CouponKind $kind): ?CouponData
    {
        return $kind === CouponKind::FreeShipping ? ($this->coupons[$code] ?? null) : null;
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

function freeShippingCoupon(float|int|null $triggerAmount = null): CouponData
{
    return new CouponData(
        code: 'SC-FREE',
        kind: CouponKind::FreeShipping,
        scope: 0,
        triggerAmount: $triggerAmount,
        amount: 0,
        amountMode: null,
        limitQty: 10,
        leftQty: 5,
    );
}

function freeShippingCartContext(float $orderTotal, float $shippingFee): CartContext
{
    return new CartContext(
        orderTotal: $orderTotal,
        allAmount: $orderTotal,
        bookAmount: $orderTotal,
        ebookAmount: 0,
        specificProductsAmount: 0,
        hasBook: true,
        hasEbook: false,
        hasSpecificProducts: false,
        meta: ['shipping_fee' => $shippingFee],
    );
}

it('免運券全額折抵當筆運費(meta.shipping_fee)', function (): void {
    $service = new DefaultCouponApplicationService(
        couponRepository: new FreeShippingCouponRepository(['SC-FREE' => freeShippingCoupon()]),
    );

    $result = $service->validate(
        CouponKind::FreeShipping,
        'SC-FREE',
        freeShippingCartContext(orderTotal: 860, shippingFee: 60.0),
        new UserContext(userId: 7),
    );

    expect($result->eligible)->toBeTrue()
        ->and($result->discount)->toBe(60.0)
        ->and($result->finalTotal)->toBe(800.0);
});

it('已符合免運資格(運費 0)時擋下不可用', function (): void {
    $service = new DefaultCouponApplicationService(
        couponRepository: new FreeShippingCouponRepository(['SC-FREE' => freeShippingCoupon()]),
    );

    $result = $service->validate(
        CouponKind::FreeShipping,
        'SC-FREE',
        freeShippingCartContext(orderTotal: 1200, shippingFee: 0.0),
        new UserContext(userId: 7),
    );

    expect($result->eligible)->toBeFalse()
        ->and($result->reasonCode)->toBe('FREE_SHIPPING_NOT_APPLICABLE');
});

it('免運券沿用 promotion 式驗證:未登入擋下、已用過擋下、門檻未達擋下', function (): void {
    $repository = new FreeShippingCouponRepository(
        ['SC-FREE' => freeShippingCoupon(triggerAmount: 500)],
        ['7:SC-FREE' => true],
    );
    $service = new DefaultCouponApplicationService(couponRepository: $repository);

    $guest = $service->validate(CouponKind::FreeShipping, 'SC-FREE', freeShippingCartContext(860, 60.0), new UserContext(userId: null));
    $used = $service->validate(CouponKind::FreeShipping, 'SC-FREE', freeShippingCartContext(860, 60.0), new UserContext(userId: 7));
    $belowThreshold = $service->validate(CouponKind::FreeShipping, 'SC-FREE', freeShippingCartContext(300, 60.0), new UserContext(userId: 8));

    expect($guest->reasonCode)->toBe('AUTH_REQUIRED')
        ->and($used->reasonCode)->toBe('COUPON_ALREADY_USED')
        ->and($belowThreshold->reasonCode)->toBe('ELIGIBILITY_FAILED');
});

it('免運券 condition payload:type=shipping_coupon、target=subtotal、order 讀 config', function (): void {
    resetDiscountConfig();

    $factory = new CouponConditionPayloadFactory();
    $payload = $factory->make(
        CouponKind::FreeShipping,
        60,
        new PricingTraceEntry(stage: 'coupon_validate', source: 'test', status: 'applied', scope: 'cart', kind: 'free_shipping'),
    );

    expect($payload->type)->toBe('shipping_coupon')
        ->and($payload->target)->toBe('subtotal')
        ->and($payload->order)->toBe(2)
        ->and($payload->value)->toBe(-60);

    setDiscountConfig(['ordering' => ['coupon' => ['free_shipping' => 3]]]);

    expect($factory->orderFor(CouponKind::FreeShipping))->toBe(3);
});
