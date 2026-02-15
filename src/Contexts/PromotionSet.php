<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contexts;

final class PromotionSet
{
    /**
     * @param list<PromotionContext> $promotions
     */
    public function __construct(
        public readonly array $promotions,
    ) {
    }
}
