<?php

declare(strict_types=1);

use Discount\Kernel\Tests\Support\StandaloneConfig;
use Illuminate\Container\Container;

require_once __DIR__ . '/Support/StandaloneConfig.php';

if (! function_exists('config')) {
    /**
     * @param array<string, mixed>|string|null $key
     */
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        if (is_array($key)) {
            foreach ($key as $itemKey => $value) {
                StandaloneConfig::set($itemKey, $value);
            }

            return null;
        }

        if (is_string($key)) {
            return StandaloneConfig::get($key, $default);
        }

        return StandaloneConfig::get();
    }
}

function hasLaravelConfigRepository(): bool
{
    if (! function_exists('config')) {
        return false;
    }

    try {
        $repository = config();
    } catch (Throwable) {
        return false;
    }

    return is_object($repository) && method_exists($repository, 'set');
}

function ensureConfigRepository(): void
{
    if (! function_exists('config')) {
        return;
    }

    if (hasLaravelConfigRepository()) {
        return;
    }

    $defaults = require dirname(__DIR__) . '/config/discount.php';

    if (! class_exists(Container::class)) {
        return;
    }

    $container = Container::getInstance();
    if (! $container instanceof Container) {
        $container = new Container();
        Container::setInstance($container);
    }

    $container->instance('config', new class ($defaults) {
        /**
         * @var array<string, mixed>
         */
        private array $items;

        /**
         * @param array<string, mixed> $defaults
         */
        public function __construct(array $defaults)
        {
            $this->items = ['discount' => $defaults];
        }

        public function get(?string $key = null, mixed $default = null): mixed
        {
            if ($key === null || $key === '') {
                return $this->items;
            }

            $segments = explode('.', $key);
            $value = $this->items;

            foreach ($segments as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return $default;
                }

                $value = $value[$segment];
            }

            return $value;
        }

        /**
         * @param array<string, mixed>|string $key
         */
        public function set(array|string $key, mixed $value = null): void
        {
            if (is_array($key)) {
                foreach ($key as $itemKey => $itemValue) {
                    $this->set($itemKey, $itemValue);
                }

                return;
            }

            $segments = explode('.', $key);
            $target = &$this->items;

            foreach ($segments as $segment) {
                if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                    $target[$segment] = [];
                }

                $target = &$target[$segment];
            }

            $target = $value;
        }
    });
}

/**
 * @param array<string, mixed> $overrides
 */
function setDiscountConfig(array $overrides): void
{
    ensureConfigRepository();

    if (hasLaravelConfigRepository()) {
        $current = config('discount');
        if (! is_array($current)) {
            $current = [];
        }

        config()->set('discount', array_replace_recursive($current, $overrides));

        return;
    }

    StandaloneConfig::mergeDiscount($overrides);
}

function resetDiscountConfig(): void
{
    ensureConfigRepository();

    $defaults = require dirname(__DIR__) . '/config/discount.php';

    if (hasLaravelConfigRepository()) {
        config()->set('discount', $defaults);

        return;
    }

    StandaloneConfig::reset();
}

beforeEach(function (): void {
    resetDiscountConfig();
});
