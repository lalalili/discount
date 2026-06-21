<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\DTOs\CartAdjustmentResult;

interface CartPromotionEngineInterface
{
    public function apply(CartContext $cart, PromotionSet $promotions): CartAdjustmentResult;
}
