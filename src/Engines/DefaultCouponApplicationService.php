<?php

declare(strict_types=1);

namespace Lalalili\Discount\Engines;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CouponContext;
use Lalalili\Discount\Contexts\UserContext;
use Lalalili\Discount\Contracts\CouponApplicationServiceInterface;
use Lalalili\Discount\Contracts\CouponDiscountEngineInterface;
use Lalalili\Discount\Contracts\CouponEligibilityInterface;
use Lalalili\Discount\Contracts\CouponRepositoryInterface;
use Lalalili\Discount\DTOs\CouponValidationResult;
use Lalalili\Discount\DTOs\PricingTrace;
use Lalalili\Discount\DTOs\PricingTraceEntry;
use Lalalili\Discount\Enums\CouponKind;
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
                pricingTrace: $this->couponValidationTrace(
                    kind: $kind,
                    code: $code,
                    status: 'failed',
                    reason: 'Coupon not found.',
                    reasonCode: 'COUPON_NOT_FOUND',
                ),
            );
        }

        if ($kind === CouponKind::Member) {
            if ($user->userId === null || $coupon->userId !== $user->userId) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Coupon not found.',
                    reasonCode: 'COUPON_NOT_FOUND',
                    pricingTrace: $this->couponValidationTrace(
                        kind: $kind,
                        code: $code,
                        status: 'failed',
                        coupon: $coupon,
                        reason: 'Coupon not found.',
                        reasonCode: 'COUPON_NOT_FOUND',
                    ),
                );
            }
        }

        if ($kind === CouponKind::Promotion || $kind === CouponKind::FreeShipping) {
            if ($user->userId === null) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Authentication required.',
                    reasonCode: 'AUTH_REQUIRED',
                    pricingTrace: $this->couponValidationTrace(
                        kind: $kind,
                        code: $code,
                        status: 'failed',
                        coupon: $coupon,
                        reason: 'Authentication required.',
                        reasonCode: 'AUTH_REQUIRED',
                    ),
                );
            }

            if ($this->resolveCouponRepository()->hasUserUsed($coupon->code, $user->userId)) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Coupon already used.',
                    reasonCode: 'COUPON_ALREADY_USED',
                    pricingTrace: $this->couponValidationTrace(
                        kind: $kind,
                        code: $code,
                        status: 'skipped',
                        coupon: $coupon,
                        reason: 'Coupon already used.',
                        reasonCode: 'COUPON_ALREADY_USED',
                    ),
                );
            }

            if ($coupon->limitQty !== null && (int) $coupon->leftQty < 1) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: 'Coupon out of stock.',
                    reasonCode: 'COUPON_OUT_OF_STOCK',
                    pricingTrace: $this->couponValidationTrace(
                        kind: $kind,
                        code: $code,
                        status: 'skipped',
                        coupon: $coupon,
                        reason: 'Coupon out of stock.',
                        reasonCode: 'COUPON_OUT_OF_STOCK',
                    ),
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
                pricingTrace: $this->couponValidationTrace(
                    kind: $kind,
                    code: $code,
                    status: 'skipped',
                    coupon: $coupon,
                    reason: $eligibility->reason,
                    reasonCode: $reasonCode,
                ),
            );
        }

        // 免運券:全額折抵當筆運費(host 經 cart meta.shipping_fee 傳入);
        // 已符合免運資格(運費 0)時擋下不可用,不走一般面額折抵引擎
        if ($kind === CouponKind::FreeShipping) {
            $shippingFee = (float) ($cart->meta['shipping_fee'] ?? 0);

            if ($shippingFee <= 0) {
                return new CouponValidationResult(
                    eligible: false,
                    reason: '目前訂單已符合免運資格，無需使用免運券。',
                    reasonCode: 'FREE_SHIPPING_NOT_APPLICABLE',
                    pricingTrace: $this->couponValidationTrace(
                        kind: $kind,
                        code: $code,
                        status: 'skipped',
                        coupon: $coupon,
                        reason: '目前訂單已符合免運資格，無需使用免運券。',
                        reasonCode: 'FREE_SHIPPING_NOT_APPLICABLE',
                    ),
                );
            }

            return new CouponValidationResult(
                eligible: true,
                coupon: $coupon,
                discount: $shippingFee,
                finalTotal: max(0.0, $cart->orderTotal - $shippingFee),
                pricingTrace: $this->couponValidationTrace(
                    kind: $kind,
                    code: $code,
                    status: 'applied',
                    coupon: $coupon,
                    amount: $shippingFee,
                    finalTotal: max(0.0, $cart->orderTotal - $shippingFee),
                ),
            );
        }

        $discountResult = $this->resolveCouponDiscountEngine()->discount($cart->orderTotal, $couponContext);
        if (! $discountResult->valid) {
            return new CouponValidationResult(
                eligible: false,
                reason: $discountResult->reason,
                reasonCode: $discountResult->reasonCode ?? 'DISCOUNT_INVALID',
                pricingTrace: $this->couponValidationTrace(
                    kind: $kind,
                    code: $code,
                    status: 'failed',
                    coupon: $coupon,
                    reason: $discountResult->reason,
                    reasonCode: $discountResult->reasonCode ?? 'DISCOUNT_INVALID',
                ),
            );
        }

        return new CouponValidationResult(
            eligible: true,
            coupon: $coupon,
            discount: $discountResult->discount,
            finalTotal: $discountResult->finalTotal,
            pricingTrace: $this->couponValidationTrace(
                kind: $kind,
                code: $code,
                status: 'applied',
                coupon: $coupon,
                amount: $discountResult->discount,
                finalTotal: $discountResult->finalTotal,
            ),
        );
    }

    private function couponValidationTrace(
        CouponKind $kind,
        string $code,
        string $status,
        ?\Lalalili\Discount\DTOs\CouponData $coupon = null,
        int|float|string|null $amount = null,
        ?float $finalTotal = null,
        ?string $reason = null,
        ?string $reasonCode = null,
    ): PricingTrace {
        return PricingTrace::fromEntry(PricingTraceEntry::couponValidation(
            kind: $kind,
            code: $code,
            status: $status,
            coupon: $coupon,
            amount: $amount,
            finalTotal: $finalTotal,
            reasonCode: $reasonCode,
            reason: $reason,
        ));
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
