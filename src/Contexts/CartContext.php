<?php

declare(strict_types=1);

namespace Discount\Kernel\Contexts;

final class CartContext
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly float $orderTotal,
        public readonly float $allAmount,
        public readonly float $bookAmount,
        public readonly float $ebookAmount,
        public readonly float $specificProductsAmount,
        public readonly bool $hasBook,
        public readonly bool $hasEbook,
        public readonly bool $hasSpecificProducts,
        public readonly ?int $productId = null,
        public readonly float $productPrice = 0.0,
        public readonly ?int $selectedGroupRebateEventId = null,
        public readonly array $meta = [],
    ) {
    }
}
