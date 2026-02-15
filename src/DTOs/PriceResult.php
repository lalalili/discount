<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\DTOs;

final class PriceResult
{
    public function __construct(
        public readonly float $price,
    ) {
    }
}
