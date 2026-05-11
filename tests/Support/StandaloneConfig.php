<?php

declare(strict_types=1);

namespace Discount\Kernel\Tests\Support;

final class StandaloneConfig
{
    /**
     * @var array<string, mixed>
     */
    private static array $values = [];

    public static function reset(): void
    {
        self::$values = [
            'discount' => require dirname(__DIR__, 2) . '/config/discount.php',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function mergeDiscount(array $overrides): void
    {
        $discount = self::get('discount', []);

        if (! is_array($discount)) {
            $discount = [];
        }

        self::set('discount', array_replace_recursive($discount, $overrides));
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return self::$values;
        }

        $segments = explode('.', $key);
        $value = self::$values;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &self::$values;

        foreach ($segments as $segment) {
            if (! is_array($target)) {
                $target = [];
            }

            if (! array_key_exists($segment, $target) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }
}
