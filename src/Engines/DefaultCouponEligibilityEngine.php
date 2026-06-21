<?php

declare(strict_types=1);

namespace Lalalili\Discount\Engines;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CouponContext;
use Lalalili\Discount\Contexts\UserContext;
use Lalalili\Discount\Contracts\CouponEligibilityInterface;
use Lalalili\Discount\DTOs\EligibilityResult;
use Lalalili\Discount\Support\DiscountConfig;

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
        $scopeMap = DiscountConfig::get('coupon.scope_map', []);

        if (! is_array($scopeMap)) {
            $scopeMap = [];
        }

        $allScope = (int) ($scopeMap['all'] ?? 0);
        $bookScope = (int) ($scopeMap['book'] ?? 1);
        $ebookScope = (int) ($scopeMap['ebook'] ?? 2);
        $specificScope = (int) ($scopeMap['specific_products'] ?? 3);

        return match ($scope) {
            $allScope      => [$cart->allAmount, true],
            $bookScope     => [$cart->bookAmount, $cart->hasBook],
            $ebookScope    => [$cart->ebookAmount, $cart->hasEbook],
            $specificScope => [$cart->specificProductsAmount, $cart->hasSpecificProducts],
            default        => [0.0, false],
        };
    }
}
