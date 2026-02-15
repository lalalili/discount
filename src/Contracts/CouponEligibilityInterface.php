<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contracts;

use Cptw\DiscountKernel\Contexts\CartContext;
use Cptw\DiscountKernel\Contexts\CouponContext;
use Cptw\DiscountKernel\Contexts\UserContext;
use Cptw\DiscountKernel\DTOs\EligibilityResult;

interface CouponEligibilityInterface
{
    public function validate(CouponContext $coupon, CartContext $cart, UserContext $user): EligibilityResult;
}
