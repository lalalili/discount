<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

interface GiftResolverInterface
{
    public function resolveIdByCode(string $giftCode): int|string|null;
}
