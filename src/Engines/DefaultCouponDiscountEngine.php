<?php

declare(strict_types=1);

namespace Lalalili\Discount\Engines;

use Lalalili\Discount\Contexts\CouponContext;
use Lalalili\Discount\Contracts\CouponDiscountEngineInterface;
use Lalalili\Discount\DTOs\CouponDiscountResult;
use Lalalili\Discount\Enums\CouponAmountMode;
use Lalalili\Discount\Support\RoundingPolicy;

final class DefaultCouponDiscountEngine implements CouponDiscountEngineInterface
{
    public function discount(float $orderTotal, CouponContext $coupon): CouponDiscountResult
    {
        if ($orderTotal <= 0) {
            return new CouponDiscountResult(
                valid: false,
                reason: 'Invalid order total.',
                reasonCode: 'DISCOUNT_INVALID'
            );
        }

        $amount = (float) $coupon->amount;
        $mode = $this->resolveAmountMode($coupon->amountMode, $amount);

        $discount = match ($mode) {
            CouponAmountMode::Fixed => $this->convergeDiscount($amount, isRate: false),
            CouponAmountMode::Rate  => $this->convergeDiscount($orderTotal * (1 - $amount), isRate: true),
            CouponAmountMode::Auto  => $amount > 0 && $amount < 1
                ? $this->convergeDiscount($orderTotal * (1 - $amount), isRate: true)
                : $this->convergeDiscount($amount, isRate: false),
        };

        if ($discount <= 0 || $discount >= $orderTotal) {
            return new CouponDiscountResult(
                valid: false,
                reason: 'Discount amount is invalid for current order total.',
                reasonCode: 'DISCOUNT_INVALID'
            );
        }

        return new CouponDiscountResult(
            valid: true,
            discount: (float) $discount,
            finalTotal: max(0, $orderTotal - (float) $discount),
        );
    }

    /**
     * 折抵額收斂:設定 `discount.rounding.coupon_discount` 時統一走 policy
     * (Fixed/Rate 一致);未設定時維持原行為(Rate 裸 round 取整、Fixed 不收斂)。
     */
    private function convergeDiscount(float $discount, bool $isRate): float
    {
        if (RoundingPolicy::hasRule('coupon_discount')) {
            return RoundingPolicy::apply($discount, 'coupon_discount');
        }

        return $isRate ? round($discount) : $discount;
    }

    private function resolveAmountMode(CouponAmountMode|string|null $amountMode, float $amount): CouponAmountMode
    {
        if ($amountMode instanceof CouponAmountMode) {
            return $amountMode;
        }

        if (is_string($amountMode) && $amountMode !== '') {
            return CouponAmountMode::tryFrom(strtolower($amountMode)) ?? CouponAmountMode::Auto;
        }

        return $amount > 0 && $amount < 1
            ? CouponAmountMode::Rate
            : CouponAmountMode::Fixed;
    }
}
