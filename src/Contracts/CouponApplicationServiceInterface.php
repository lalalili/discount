<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\UserContext;
use Discount\Kernel\DTOs\CouponValidationResult;
use Discount\Kernel\Enums\CouponKind;

interface CouponApplicationServiceInterface
{
    public function validate(CouponKind $kind, string $code, CartContext $cart, UserContext $user): CouponValidationResult;
}
