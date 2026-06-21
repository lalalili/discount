<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contexts;

use Lalalili\Discount\Enums\CouponAmountMode;

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
