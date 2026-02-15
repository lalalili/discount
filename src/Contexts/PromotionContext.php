<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Contexts;

final class PromotionContext
{
    public function __construct(
        public readonly int $type,
        public readonly ?int $sort = null,
        public readonly float|int|null $discountAmount = null,
        public readonly float|int|null $rebateGetAmount = null,
    ) {
    }
}
