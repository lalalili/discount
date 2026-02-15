<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contexts;

final class CartContext
{
    public function __construct(
        public readonly float $orderTotal,
        public readonly float $allAmount,
        public readonly float $bookAmount,
        public readonly float $ebookAmount,
        public readonly float $specificProductsAmount,
        public readonly bool $hasBook,
        public readonly bool $hasEbook,
        public readonly bool $hasSpecificProducts,
    ) {
    }
}
