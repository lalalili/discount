<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Engines;

use Cptw\DiscountKernel\Contexts\CartContext;
use Cptw\DiscountKernel\Contexts\CouponContext;
use Cptw\DiscountKernel\Contexts\UserContext;
use Cptw\DiscountKernel\Contracts\CouponEligibilityInterface;
use Cptw\DiscountKernel\DTOs\EligibilityResult;

final class DefaultCouponEligibilityEngine implements CouponEligibilityInterface
{
    public function validate(CouponContext $coupon, CartContext $cart, UserContext $user): EligibilityResult
    {
        if ((float) $coupon->amount >= $cart->orderTotal) {
            return new EligibilityResult(false, '折扣金額超過結帳金額，請加購商品後使用');
        }

        [$amount, $fallback] = $this->resolveScopeAmountAndFallback($coupon->scope, $cart);

        if ($coupon->triggerAmount === null) {
            return new EligibilityResult($fallback, $fallback ? null : '未達使用條件，請檢查折扣金額或使用門檻');
        }

        if ($amount >= (float) $coupon->triggerAmount) {
            return new EligibilityResult(true);
        }

        return new EligibilityResult(false, '未達使用條件，請檢查折扣金額或使用門檻');
    }

    /**
     * @return array{0: float, 1: bool}
     */
    private function resolveScopeAmountAndFallback(int $scope, CartContext $cart): array
    {
        return match ($scope) {
            0       => [$cart->allAmount, true],
            1       => [$cart->bookAmount, $cart->hasBook],
            2       => [$cart->ebookAmount, $cart->hasEbook],
            3       => [$cart->specificProductsAmount, $cart->hasSpecificProducts],
            default => [0.0, false],
        };
    }
}
