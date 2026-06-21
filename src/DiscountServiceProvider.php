<?php

declare(strict_types=1);

namespace Lalalili\Discount;

use Lalalili\Discount\Contracts\CartPromotionEngineInterface;
use Lalalili\Discount\Contracts\CartPromotionRefreshServiceInterface;
use Lalalili\Discount\Contracts\CouponApplicationServiceInterface;
use Lalalili\Discount\Contracts\CouponCodeGeneratorInterface;
use Lalalili\Discount\Contracts\CouponDiscountEngineInterface;
use Lalalili\Discount\Contracts\CouponEligibilityInterface;
use Lalalili\Discount\Contracts\DiscountEngineInterface;
use Lalalili\Discount\Engines\DefaultCartPromotionEngine;
use Lalalili\Discount\Engines\DefaultCartPromotionRefreshService;
use Lalalili\Discount\Engines\DefaultCouponApplicationService;
use Lalalili\Discount\Engines\DefaultCouponCodeGenerator;
use Lalalili\Discount\Engines\DefaultCouponDiscountEngine;
use Lalalili\Discount\Engines\DefaultCouponEligibilityEngine;
use Lalalili\Discount\Engines\DefaultDiscountEngine;
use Lalalili\Discount\Support\CouponConditionPayloadFactory;
use Lalalili\Discount\Support\PromotionRefreshFingerprint;
use Illuminate\Support\ServiceProvider;

final class DiscountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/discount.php', 'discount');

        $this->app->singleton(DiscountEngineInterface::class, DefaultDiscountEngine::class);
        $this->app->singleton(CartPromotionEngineInterface::class, DefaultCartPromotionEngine::class);
        $this->app->singleton(CartPromotionRefreshServiceInterface::class, DefaultCartPromotionRefreshService::class);
        $this->app->singleton(CouponEligibilityInterface::class, DefaultCouponEligibilityEngine::class);
        $this->app->singleton(CouponCodeGeneratorInterface::class, DefaultCouponCodeGenerator::class);
        $this->app->singleton(CouponDiscountEngineInterface::class, DefaultCouponDiscountEngine::class);
        $this->app->singleton(CouponApplicationServiceInterface::class, DefaultCouponApplicationService::class);
        $this->app->singleton(CouponConditionPayloadFactory::class, CouponConditionPayloadFactory::class);
        $this->app->singleton(PromotionRefreshFingerprint::class, PromotionRefreshFingerprint::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/discount.php' => config_path('discount.php'),
        ], 'discount-config');
    }
}
