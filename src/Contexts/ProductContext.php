<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contexts;

final class ProductContext
{
    public function __construct(
        public readonly float $listPrice,
    ) {
    }
}
