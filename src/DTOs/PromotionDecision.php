<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class PromotionDecision
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $status,
        public readonly string $scope,
        public readonly int|string|null $lineId = null,
        public readonly int|string|null $productId = null,
        public readonly ?int $eventId = null,
        public readonly ?int $type = null,
        public readonly string $name = '',
        public readonly string $target = '',
        public readonly string $adjustmentType = '',
        public readonly mixed $value = null,
        public readonly ?string $reason = null,
        public readonly array $details = [],
    ) {
    }

    /**
     * @param array<string, mixed> $promotion
     */
    public static function fromAppliedPromotion(array $promotion): self
    {
        return new self(
            status: 'applied',
            scope: (string) ($promotion['scope'] ?? ''),
            lineId: self::scalarOrNull($promotion['line_id'] ?? null),
            productId: self::scalarOrNull($promotion['product_id'] ?? null),
            eventId: self::nullableInt($promotion['event_id'] ?? null),
            type: self::nullableInt($promotion['type'] ?? null),
            name: (string) ($promotion['name'] ?? ''),
            target: (string) ($promotion['target'] ?? ''),
            adjustmentType: (string) ($promotion['adjustment_type'] ?? ''),
            value: $promotion['value'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $promotion
     */
    public static function fromSkippedPromotion(array $promotion): self
    {
        $details = $promotion;
        unset(
            $details['scope'],
            $details['line_id'],
            $details['product_id'],
            $details['event_id'],
            $details['type'],
            $details['name'],
            $details['reason'],
        );

        return new self(
            status: 'skipped',
            scope: (string) ($promotion['scope'] ?? ''),
            lineId: self::scalarOrNull($promotion['line_id'] ?? null),
            productId: self::scalarOrNull($promotion['product_id'] ?? null),
            eventId: self::nullableInt($promotion['event_id'] ?? null),
            type: self::nullableInt($promotion['type'] ?? null),
            name: (string) ($promotion['name'] ?? ''),
            reason: PromotionDecisionReason::normalize($promotion['reason'] ?? null),
            details: $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'scope' => $this->scope,
            'line_id' => $this->lineId,
            'product_id' => $this->productId,
            'event_id' => $this->eventId,
            'type' => $this->type,
            'name' => $this->name,
            'target' => $this->target,
            'adjustment_type' => $this->adjustmentType,
            'value' => $this->value,
            'reason' => $this->reason,
            'details' => $this->details,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function scalarOrNull(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) ? $value : null;
    }
}
