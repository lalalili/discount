<?php

declare(strict_types=1);

namespace Lalalili\Discount\DTOs;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CartLineContext;
use Lalalili\Discount\Contexts\PromotionSet;

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
        public readonly string $giftFulfillment = 'condition_only',
    ) {
    }
}
