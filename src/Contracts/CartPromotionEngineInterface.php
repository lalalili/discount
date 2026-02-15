<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contracts;

use Cptw\DiscountKernel\Contexts\CartContext;
use Cptw\DiscountKernel\Contexts\PromotionSet;
use Cptw\DiscountKernel\DTOs\CartAdjustmentResult;

interface CartPromotionEngineInterface
{
    public function apply(CartContext $cart, PromotionSet $promotions): CartAdjustmentResult;
}
