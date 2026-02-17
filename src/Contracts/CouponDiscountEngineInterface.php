<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\DTOs\CouponDiscountResult;

interface CouponDiscountEngineInterface
{
    public function discount(float $orderTotal, CouponContext $coupon): CouponDiscountResult;
}
