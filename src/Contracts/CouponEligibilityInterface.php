<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\CouponContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\DTOs\EligibilityResult;

interface CouponEligibilityInterface
{
    public function validate(CouponContext $coupon, CartContext $cart, UserContext $user): EligibilityResult;
}
