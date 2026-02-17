<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

use Discount\Kernel\Enums\CouponAmountMode;
use Discount\Kernel\Enums\CouponKind;

final class CouponData
{
    public readonly CouponAmountMode $amountMode;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $code,
        public readonly CouponKind $kind,
        public readonly int $scope,
        public readonly float|int|null $triggerAmount,
        public readonly float|int $amount,
        CouponAmountMode|string|null $amountMode = null,
        public readonly bool $status = true,
        public readonly ?int $limitQty = null,
        public readonly ?int $leftQty = null,
        public readonly ?int $userId = null,
        public readonly array $attributes = [],
    ) {
        $this->amountMode = $this->normalizeAmountMode($amountMode);
    }

    private function normalizeAmountMode(CouponAmountMode|string|null $amountMode): CouponAmountMode
    {
        if ($amountMode instanceof CouponAmountMode) {
            return $amountMode;
        }

        if (is_string($amountMode)) {
            return CouponAmountMode::tryFrom(strtolower($amountMode)) ?? CouponAmountMode::Auto;
        }

        return CouponAmountMode::Auto;
    }
}
