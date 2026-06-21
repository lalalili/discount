<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CouponContext;
use Lalalili\Discount\Contexts\UserContext;
use Lalalili\Discount\DTOs\EligibilityResult;

interface CouponEligibilityInterface
{
    public function validate(CouponContext $coupon, CartContext $cart, UserContext $user): EligibilityResult;
}
