<?php

declare(strict_types=1);

namespace Lalalili\Discount\DTOs;

final class EligibilityResult
{
    public function __construct(
        public readonly bool $eligible,
        public readonly ?string $reason = null,
    ) {
    }
}
