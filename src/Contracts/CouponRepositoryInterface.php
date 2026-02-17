<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\DTOs\CouponData;
use Discount\Kernel\Enums\CouponKind;

interface CouponRepositoryInterface
{
    public function findActiveByCode(string $code, CouponKind $kind): ?CouponData;

    public function hasUserUsed(string $code, int $userId): bool;

    public function decrementInventory(string $code): bool;
}
