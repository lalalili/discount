<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Engines;

use Cptw\DiscountKernel\Contexts\CartContext;
use Cptw\DiscountKernel\Contexts\PromotionSet;
use Cptw\DiscountKernel\Contracts\CartPromotionEngineInterface;
use Cptw\DiscountKernel\DTOs\CartAdjustmentResult;

/**
 * @internal Cart promotion aggregation is still handled in app-level adapters.
 */
final class DefaultCartPromotionEngine implements CartPromotionEngineInterface
{
    public function apply(CartContext $cart, PromotionSet $promotions): CartAdjustmentResult
    {
        return new CartAdjustmentResult([]);
    }
}
