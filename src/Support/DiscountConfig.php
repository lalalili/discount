<?php

declare(strict_types=1);

namespace Discount\Kernel\Support;

use Throwable;

final class DiscountConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (function_exists('config')) {
            try {
                $configured = config('discount');

                if (is_array($configured)) {
                    return array_replace_recursive(self::defaults(), $configured);
                }
            } catch (Throwable) {
                // Fallback to defaults when Laravel config repository is not bootstrapped.
            }
        }

        return self::defaults();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::all();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require __DIR__ . '/../../config/discount.php';

        return $defaults;
    }
}
