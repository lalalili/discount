<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\PromotionContext;

interface PromotionContextMapperInterface
{
    /**
     * @param object|array<string, mixed> $promotion
     */
    public function map(object|array $promotion): ?PromotionContext;
}
