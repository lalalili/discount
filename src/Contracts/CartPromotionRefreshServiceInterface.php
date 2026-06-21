<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\DTOs\CartPromotionRefreshInput;
use Lalalili\Discount\DTOs\CartPromotionRefreshResult;

interface CartPromotionRefreshServiceInterface
{
    public function refresh(CartPromotionRefreshInput $input): CartPromotionRefreshResult;
}
