<?php

declare(strict_types=1);

namespace Discount\Kernel;

use Illuminate\Support\ServiceProvider;

final class DiscountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/discount.php', 'discount');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/discount.php' => config_path('discount.php'),
        ], 'discount-config');
    }
}
