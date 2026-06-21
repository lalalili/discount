<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\CouponContext;
use Lalalili\Discount\DTOs\CouponDiscountResult;

interface CouponDiscountEngineInterface
{
    public function discount(float $orderTotal, CouponContext $coupon): CouponDiscountResult;
}
