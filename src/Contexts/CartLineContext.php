<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contexts;

final class CartLineContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly int|string $id,
        public readonly int|string $productId,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly string $associatedModel = 'Product',
        public readonly array $attributes = [],
    ) {
    }
}
