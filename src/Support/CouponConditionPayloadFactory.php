<?php

declare(strict_types=1);

namespace Lalalili\Discount\Support;

use Lalalili\Discount\DTOs\CouponConditionPayload;
use Lalalili\Discount\DTOs\PricingTraceEntry;
use Lalalili\Discount\Enums\CouponKind;

final class CouponConditionPayloadFactory
{
    /**
     * @deprecated v4 移除;order 改由 config `discount.ordering.coupon.member` 決定,
     *             常數僅作 config 未設定時的 fallback。
     */
    public const MEMBER_COUPON_CONDITION_ORDER = 10;

    /**
     * @deprecated v4 移除;order 改由 config `discount.ordering.coupon.promotion` 決定,
     *             常數僅作 config 未設定時的 fallback。
     */
    public const PROMOTION_COUPON_CONDITION_ORDER = 11;

    public function make(CouponKind $kind, int|float $discount, PricingTraceEntry $pricingTraceEntry): CouponConditionPayload
    {
        return new CouponConditionPayload(
            type: $this->typeFor($kind),
            target: 'total',
            value: -1 * $discount,
            order: $this->orderFor($kind),
            attributes: [
                'pricing_trace_entry' => $pricingTraceEntry->toArray(),
            ],
        );
    }

    public function typeFor(CouponKind $kind): string
    {
        return $kind === CouponKind::Member ? 'member_coupon' : 'promotion_coupon';
    }

    public function orderFor(CouponKind $kind): int
    {
        return $kind === CouponKind::Member
            ? (int) DiscountConfig::get('ordering.coupon.member', self::MEMBER_COUPON_CONDITION_ORDER)
            : (int) DiscountConfig::get('ordering.coupon.promotion', self::PROMOTION_COUPON_CONDITION_ORDER);
    }
}
