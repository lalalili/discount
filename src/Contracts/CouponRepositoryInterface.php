<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\DTOs\CouponData;
use Lalalili\Discount\Enums\CouponKind;

interface CouponRepositoryInterface
{
    public function findActiveByCode(string $code, CouponKind $kind): ?CouponData;

    public function hasUserUsed(string $code, int $userId): bool;

    public function decrementInventory(string $code): bool;
}
