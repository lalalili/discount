<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contracts;

use Cptw\DiscountKernel\Contexts\ProductContext;
use Cptw\DiscountKernel\Contexts\PromotionSet;
use Cptw\DiscountKernel\DTOs\PriceResult;

interface DiscountEngineInterface
{
    public function price(ProductContext $product, PromotionSet $promotions): PriceResult;
}
