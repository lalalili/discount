<?php

declare(strict_types=1);

namespace Discount\Kernel\Engines;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\Contracts\CouponApplicationServiceInterface;
use Discount\Kernel\Contracts\CouponDiscountEngineInterface;
use Discount\Kernel\Contracts\CouponEligibilityInterface;
use Discount\Kernel\Contracts\CouponRepositoryInterface;
use Discount\Kernel\DTOs\CouponValidationResult;
use Discount\Kernel\Enums\CouponKind;
use RuntimeException;

final class DefaultCouponApplicationService implements CouponApplicationServiceInterface
{
    public function __construct(
        private ?CouponRepositoryInterface $couponRepository = null,
        private ?CouponEligibilityInterface $couponEligibility = null,
        private ?CouponDiscountEngineInterface $couponDiscountEngine = null,
    ) {
    }

    public function validate(CouponKind $kind, string $code, CartContext $cart, UserContext $user): CouponValidationResult
    {
        $coupon = $this->resolveCouponRepository()->findActiveByCode($code, $kind);

        if ($coupon === null || ! $coupon->status) {
            return new CouponValidationResult(
                eligible: false,
                reason: 'Coupon not found.',
                reasonCode: 'COUPON_NOT_FOUND',
            );
        }

        if ($kind === CouponKind::Member) {
            if ($user->userId === null || $coupon->userId !== $user->userId) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Coupon not found.',
                    reasonCode: 'COUPON_NOT_FOUND',
                );
            }
        }

        if ($kind === CouponKind::Promotion) {
            if ($user->userId === null) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Authentication required.',
                    reasonCode: 'AUTH_REQUIRED',
                );
            }

            if ($this->resolveCouponRepository()->hasUserUsed($coupon->code, $user->userId)) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Coupon already used.',
                    reasonCode: 'COUPON_ALREADY_USED',
                );
            }

            if ($coupon->limitQty !== null && (int) $coupon->leftQty < 1) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Coupon out of stock.',
                    reasonCode: 'COUPON_OUT_OF_STOCK',
                );
            }
        }

        $couponContext = new CouponContext(
            scope: $coupon->scope,
            triggerAmount: $coupon->triggerAmount,
            amount: $coupon->amount,
            amountMode: $coupon->amountMode,
        );

        $eligibility = $this->resolveCouponEligibility()->validate($couponContext, $cart, $user);
        if (! $eligibility->eligible) {
            $reasonCode = $eligibility->reason === '折扣金額超過結帳金額，請加購商品後使用'
                ? 'DISCOUNT_INVALID'
                : 'ELIGIBILITY_FAILED';

            return new CouponValidationResult(
                eligible: false,
                reason: $eligibility->reason,
                reasonCode: $reasonCode,
            );
        }

        $discountResult = $this->resolveCouponDiscountEngine()->discount($cart->orderTotal, $couponContext);
        if (! $discountResult->valid) {
            return new CouponValidationResult(
                eligible: false,
                reason: $discountResult->reason,
                reasonCode: $discountResult->reasonCode ?? 'DISCOUNT_INVALID',
            );
        }

        return new CouponValidationResult(
            eligible: true,
            coupon: $coupon,
            discount: $discountResult->discount,
            finalTotal: $discountResult->finalTotal,
        );
    }

    private function resolveCouponRepository(): CouponRepositoryInterface
    {
        if ($this->couponRepository instanceof CouponRepositoryInterface) {
            return $this->couponRepository;
        }

        if (function_exists('app') && app()->bound(CouponRepositoryInterface::class)) {
            /** @var CouponRepositoryInterface $repository */
            $repository = app(CouponRepositoryInterface::class);
            $this->couponRepository = $repository;

            return $this->couponRepository;
        }

        throw new RuntimeException('CouponRepositoryInterface is not bound.');
    }

    private function resolveCouponEligibility(): CouponEligibilityInterface
    {
        if ($this->couponEligibility instanceof CouponEligibilityInterface) {
            return $this->couponEligibility;
        }

        if (function_exists('app') && app()->bound(CouponEligibilityInterface::class)) {
            /** @var CouponEligibilityInterface $engine */
            $engine = app(CouponEligibilityInterface::class);
            $this->couponEligibility = $engine;

            return $this->couponEligibility;
        }

        $this->couponEligibility = new DefaultCouponEligibilityEngine();

        return $this->couponEligibility;
    }

    private function resolveCouponDiscountEngine(): CouponDiscountEngineInterface
    {
        if ($this->couponDiscountEngine instanceof CouponDiscountEngineInterface) {
            return $this->couponDiscountEngine;
        }

        if (function_exists('app') && app()->bound(CouponDiscountEngineInterface::class)) {
            /** @var CouponDiscountEngineInterface $engine */
            $engine = app(CouponDiscountEngineInterface::class);
            $this->couponDiscountEngine = $engine;

            return $this->couponDiscountEngine;
        }

        $this->couponDiscountEngine = new DefaultCouponDiscountEngine();

        return $this->couponDiscountEngine;
    }
}
