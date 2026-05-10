<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\DTOs\CartPromotionRefreshInput;
use Discount\Kernel\DTOs\CartPromotionRefreshResult;

interface CartPromotionRefreshServiceInterface
{
    public function refresh(CartPromotionRefreshInput $input): CartPromotionRefreshResult;
}
