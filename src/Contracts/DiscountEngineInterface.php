<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\ProductContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\DTOs\PriceResult;

interface DiscountEngineInterface
{
    public function price(ProductContext $product, PromotionSet $promotions): PriceResult;
}
