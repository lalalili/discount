<?php

declare(strict_types=1);

namespace Discount\Kernel\Contexts;

use DateTimeInterface;

final class CodeContext
{
    /**
     * @param null|callable(string):bool $existsChecker
     */
    public function __construct(
        public readonly int $typeValue,
        public readonly ?int $userId = null,
        public readonly int $count = 1,
        public readonly int $maxAttempts = 5,
        public readonly DateTimeInterface|string|null $now = null,
        public readonly mixed $existsChecker = null,
    ) {
    }
}
