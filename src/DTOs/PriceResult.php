<?php

declare(strict_types=1);

namespace Lalalili\Discount\DTOs;

final class PriceResult
{
    public function __construct(
        public readonly float $price,
    ) {
    }
}
