<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\UserContext;
use Lalalili\Discount\DTOs\CouponValidationResult;
use Lalalili\Discount\Enums\CouponKind;

interface CouponApplicationServiceInterface
{
    public function validate(CouponKind $kind, string $code, CartContext $cart, UserContext $user): CouponValidationResult;
}
