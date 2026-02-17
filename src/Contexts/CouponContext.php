<?php

declare(strict_types=1);

namespace Discount\Kernel\Contexts;

use Discount\Kernel\Enums\CouponAmountMode;

final class CouponContext
{
    public function __construct(
        public readonly int $scope,
        public readonly float|int|null $triggerAmount,
        public readonly float|int $amount,
        public readonly CouponAmountMode|string|null $amountMode = null,
    ) {
    }
}
