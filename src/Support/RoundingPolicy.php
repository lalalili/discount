<?php

declare(strict_types=1);

namespace Lalalili\Discount\Support;

/**
 * Config 驅動的金額收斂政策。
 *
 * 規則定義於 `discount.rounding.<key>`,格式與 lalalili/laravelshoppingcart 一致:
 * - null / 未設定:不收斂(向後相容,維持各引擎原有行為)
 * - int:round 精度
 * - array{precision?: int, mode?: string}:mode 支援
 *   'half_up'|'half_down'|'half_even'|'half_odd'|'floor'|'ceil'
 */
final class RoundingPolicy
{
    public static function hasRule(string $key): bool
    {
        return DiscountConfig::get('rounding.' . $key) !== null;
    }

    public static function apply(float $value, string $key): float
    {
        $rule = DiscountConfig::get('rounding.' . $key);

        if ($rule === null || $rule === false) {
            return $value;
        }

        if (is_int($rule)) {
            return round($value, $rule);
        }

        if (! is_array($rule)) {
            return $value;
        }

        $precision = (int) ($rule['precision'] ?? 0);
        $mode = $rule['mode'] ?? 'half_up';

        if ($mode === 'floor' || $mode === 'ceil') {
            $factor = 10 ** $precision;
            $scaled = $value * $factor;

            return ($mode === 'floor' ? floor($scaled) : ceil($scaled)) / $factor;
        }

        $phpMode = match ($mode) {
            'half_down' => PHP_ROUND_HALF_DOWN,
            'half_even' => PHP_ROUND_HALF_EVEN,
            'half_odd'  => PHP_ROUND_HALF_ODD,
            default     => PHP_ROUND_HALF_UP,
        };

        return round($value, $precision, $phpMode);
    }
}
