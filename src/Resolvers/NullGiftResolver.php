<?php

declare(strict_types=1);

namespace Lalalili\Discount\Resolvers;

use Lalalili\Discount\Contracts\GiftResolverInterface;

final class NullGiftResolver implements GiftResolverInterface
{
    public function resolveIdByCode(string $giftCode): int|string|null
    {
        return null;
    }
}
