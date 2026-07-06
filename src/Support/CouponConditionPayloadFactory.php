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

    /**
     * 免運券 condition 的 fallback order(target=subtotal,緊跟 host shipping_fee 之後);
     * 實際值由 config `discount.ordering.coupon.free_shipping` 決定。
     */
    public const FREE_SHIPPING_COUPON_CONDITION_ORDER = 2;

    public function make(CouponKind $kind, int|float $discount, PricingTraceEntry $pricingTraceEntry): CouponConditionPayload
    {
        return new CouponConditionPayload(
            type: $this->typeFor($kind),
            target: $this->targetFor($kind),
            value: -1 * $discount,
            order: $this->orderFor($kind),
            attributes: [
                'pricing_trace_entry' => $pricingTraceEntry->toArray(),
            ],
        );
    }

    public function typeFor(CouponKind $kind): string
    {
        return match ($kind) {
            CouponKind::Member       => 'member_coupon',
            CouponKind::Promotion    => 'promotion_coupon',
            CouponKind::FreeShipping => 'shipping_coupon',
        };
    }

    /**
     * 免運券掛在 subtotal 層(與 shipping_fee 同 target,折抵運費本身);
     * 其餘 coupon 掛 total 層。
     */
    public function targetFor(CouponKind $kind): string
    {
        return $kind === CouponKind::FreeShipping ? 'subtotal' : 'total';
    }

    public function orderFor(CouponKind $kind): int
    {
        return match ($kind) {
            CouponKind::Member       => (int) DiscountConfig::get('ordering.coupon.member', self::MEMBER_COUPON_CONDITION_ORDER),
            CouponKind::Promotion    => (int) DiscountConfig::get('ordering.coupon.promotion', self::PROMOTION_COUPON_CONDITION_ORDER),
            CouponKind::FreeShipping => (int) DiscountConfig::get('ordering.coupon.free_shipping', self::FREE_SHIPPING_COUPON_CONDITION_ORDER),
        };
    }
}
