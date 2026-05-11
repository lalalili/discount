<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class CouponConditionPayload
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $type,
        public readonly string $target,
        public readonly int|float $value,
        public readonly int $order,
        public readonly array $attributes,
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function toArray(array $extra = []): array
    {
        return array_replace($extra, [
            'type'       => $this->type,
            'target'     => $this->target,
            'value'      => $this->value,
            'order'      => $this->order,
            'attributes' => $this->attributes,
        ]);
    }
}
