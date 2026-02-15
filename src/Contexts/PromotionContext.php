<?php

declare(strict_types=1);

namespace Discount\Kernel\Contexts;

final class PromotionContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly int $type,
        public readonly ?int $sort = null,
        public readonly float|int|null $discountAmount = null,
        public readonly float|int|null $rebateGetAmount = null,
        public readonly ?int $eventId = null,
        public readonly ?string $name = null,
        public readonly float|int|null $rebateTriggerAmount = null,
        public readonly float|int|null $giftTriggerAmount = null,
        public readonly float|int|null $giftTriggerQuantity = null,
        public readonly ?string $giftProductCode = null,
        public readonly bool|int|null $repeatable = null,
        public readonly array $attributes = [],
    ) {
    }
}
