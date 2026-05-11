<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class PromotionDecisionReason
{
    public const string THRESHOLD_NOT_MET = 'threshold_not_met';
    public const string EXCLUSIVE_CONFLICT = 'exclusive_conflict';
    public const string GIFT_UNRESOLVED = 'gift_unresolved';
    public const string GIFT_OUT_OF_STOCK = 'gift_out_of_stock';
    public const string NOT_SELECTED = 'not_selected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::THRESHOLD_NOT_MET,
            self::EXCLUSIVE_CONFLICT,
            self::GIFT_UNRESOLVED,
            self::GIFT_OUT_OF_STOCK,
            self::NOT_SELECTED,
        ];
    }

    public static function normalize(mixed $reason): string
    {
        $reason = is_scalar($reason) ? (string) $reason : '';

        return match ($reason) {
            self::THRESHOLD_NOT_MET,
            self::EXCLUSIVE_CONFLICT,
            self::GIFT_UNRESOLVED,
            self::GIFT_OUT_OF_STOCK,
            self::NOT_SELECTED => $reason,
            'cart_rebate_threshold_not_met',
            'gift_threshold_not_met',
            'group_rebate_threshold_not_met' => self::THRESHOLD_NOT_MET,
            'group_rebate_not_selected'      => self::NOT_SELECTED,
            default                          => self::NOT_SELECTED,
        };
    }
}
