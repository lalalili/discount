<?php

declare(strict_types=1);

namespace Discount\Kernel\Contexts;

final class UserContext
{
    public function __construct(
        public readonly ?int $userId,
    ) {
    }
}
