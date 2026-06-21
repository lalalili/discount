<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contexts;

final class UserContext
{
    public function __construct(
        public readonly ?int $userId,
    ) {
    }
}
