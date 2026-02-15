<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\DTOs;

final class CartAdjustmentResult
{
    /**
     * @param list<array{name:string,type:string,value:float|int}> $adjustments
     */
    public function __construct(
        public readonly array $adjustments,
    ) {
    }
}
