<?php

declare(strict_types=1);

namespace Discount\Kernel;

use Discount\Kernel\Contracts\CartPromotionEngineInterface;
use Discount\Kernel\Contracts\CartPromotionRefreshServiceInterface;
use Discount\Kernel\Contracts\CouponApplicationServiceInterface;
use Discount\Kernel\Contracts\CouponCodeGeneratorInterface;
use Discount\Kernel\Contracts\CouponDiscountEngineInterface;
use Discount\Kernel\Contracts\CouponEligibilityInterface;
use Discount\Kernel\Contracts\DiscountEngineInterface;
use Discount\Kernel\Engines\DefaultCartPromotionEngine;
use Discount\Kernel\Engines\DefaultCartPromotionRefreshService;
use Discount\Kernel\Engines\DefaultCouponApplicationService;
use Discount\Kernel\Engines\DefaultCouponCodeGenerator;
use Discount\Kernel\Engines\DefaultCouponDiscountEngine;
use Discount\Kernel\Engines\DefaultCouponEligibilityEngine;
use Discount\Kernel\Engines\DefaultDiscountEngine;
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
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/discount.php' => config_path('discount.php'),
        ], 'discount-config');
    }
}
