<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\CartLineContext;
use Discount\Kernel\Contexts\PromotionSet;

final class CartPromotionRefreshInput
{
    /**
     * @param list<CartLineContext> $lines
     * @param array<int|string, PromotionSet> $promotionSetsByProductId
     */
    public function __construct(
        public readonly CartContext $cartContext,
        public readonly array $lines,
        public readonly array $promotionSetsByProductId,
    ) {
    }
}
