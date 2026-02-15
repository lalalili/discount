<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\DTOs\CartAdjustmentResult;

interface CartPromotionEngineInterface
{
    public function apply(CartContext $cart, PromotionSet $promotions): CartAdjustmentResult;
}
