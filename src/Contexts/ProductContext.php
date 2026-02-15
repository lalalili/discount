<?php

declare(strict_types=1);

namespace Discount\Kernel\Contexts;

final class ProductContext
{
    public function __construct(
        public readonly float $listPrice,
    ) {
    }
}
