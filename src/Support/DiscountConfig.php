<?php

declare(strict_types=1);

namespace Lalalili\Discount\Support;

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

    /**
     * 驗證 ordering 配置的一致性(per-target:僅檢查 total 層的排序空間)。
     * 回傳警告訊息清單,空陣列 = 通過;host 以 architecture 測試在 CI 落地。
     *
     * 檢查項:
     * 1. coupon order 必須落在 ordering.layers.coupon.range 區間
     * 2. coupon order 不得與 total 層 promotion(rebate/gift types 的 type_order)相撞
     *    (item/subtotal 層條件不同 target,不在檢查範圍)
     *
     * @return list<string>
     */
    public static function validateOrdering(): array
    {
        $warnings = [];

        $couponOrders = [];
        foreach (['member', 'promotion'] as $kind) {
            $order = self::get('ordering.coupon.' . $kind);
            if (is_numeric($order)) {
                $couponOrders[$kind] = (int) $order;
            }
        }

        $couponRange = self::get('ordering.layers.coupon.range');
        if (is_array($couponRange) && count($couponRange) === 2) {
            [$min, $max] = [(int) $couponRange[0], (int) $couponRange[1]];
            foreach ($couponOrders as $kind => $order) {
                if ($order < $min || $order > $max) {
                    $warnings[] = "ordering.coupon.{$kind} ({$order}) 超出 coupon layer 區間 [{$min}, {$max}]。";
                }
            }
        }

        $totalLayerTypes = [];
        foreach (['rebate_types', 'gift_types'] as $roleKey) {
            $types = self::get('cart.roles.' . $roleKey, []);
            if (is_array($types)) {
                $totalLayerTypes = array_merge($totalLayerTypes, array_map('intval', $types));
            }
        }

        $typeOrderMap = self::get('event.priorities.type_order', []);
        $typeOrderMap = is_array($typeOrderMap) ? $typeOrderMap : [];

        foreach (array_unique($totalLayerTypes) as $type) {
            $promotionOrder = (int) ($typeOrderMap[$type] ?? $typeOrderMap[(string) $type] ?? $type);

            foreach ($couponOrders as $kind => $order) {
                if ($promotionOrder === $order) {
                    $warnings[] = "total 層 type {$type} 的 order ({$promotionOrder}) 與 ordering.coupon.{$kind} 相撞。";
                }
            }
        }

        return $warnings;
    }
}
