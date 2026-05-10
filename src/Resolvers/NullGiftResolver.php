<?php

declare(strict_types=1);

namespace Discount\Kernel\Resolvers;

use Discount\Kernel\Contracts\GiftResolverInterface;

final class NullGiftResolver implements GiftResolverInterface
{
    public function resolveIdByCode(string $giftCode): int|string|null
    {
        return null;
    }
}
