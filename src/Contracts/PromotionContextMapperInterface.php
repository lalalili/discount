<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\PromotionContext;

interface PromotionContextMapperInterface
{
    /**
     * @param object|array<string, mixed> $promotion
     */
    public function map(object|array $promotion): ?PromotionContext;
}
