<?php

declare(strict_types=1);

namespace Discount\Kernel\Engines;

use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Contracts\CouponDiscountEngineInterface;
use Discount\Kernel\DTOs\CouponDiscountResult;
use Discount\Kernel\Enums\CouponAmountMode;

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
            CouponAmountMode::Fixed => $amount,
            CouponAmountMode::Rate  => round($orderTotal * (1 - $amount)),
            CouponAmountMode::Auto  => $amount > 0 && $amount < 1
                ? round($orderTotal * (1 - $amount))
                : $amount,
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
