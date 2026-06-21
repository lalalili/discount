<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\ProductContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\DTOs\PriceResult;

interface DiscountEngineInterface
{
    public function price(ProductContext $product, PromotionSet $promotions): PriceResult;
}
