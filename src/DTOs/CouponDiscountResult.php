<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class CouponDiscountResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly float $discount = 0.0,
        public readonly float $finalTotal = 0.0,
        public readonly ?string $reason = null,
        public readonly ?string $reasonCode = null,
    ) {
    }
}
