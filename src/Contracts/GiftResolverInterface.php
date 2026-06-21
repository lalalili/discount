<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

interface GiftResolverInterface
{
    public function resolveIdByCode(string $giftCode): int|string|null;
}
