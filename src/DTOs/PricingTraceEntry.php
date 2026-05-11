<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

use Discount\Kernel\Enums\CouponKind;

final class PricingTraceEntry
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $stage,
        public readonly string $source,
        public readonly string $status,
        public readonly string $scope,
        public readonly string $kind,
        public readonly ?string $code = null,
        public readonly int|string|null $id = null,
        public readonly int|float|string|null $amount = null,
        public readonly ?float $finalTotal = null,
        public readonly ?string $reasonCode = null,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @param PromotionDecision|array<string, mixed> $decision
     */
    public static function fromPromotionDecision(PromotionDecision|array $decision): self
    {
        $payload = $decision instanceof PromotionDecision ? $decision->toArray() : $decision;
        $amount = $payload['value'] ?? null;

        return new self(
            stage: 'promotion_refresh',
            source: 'promotion',
            status: (string) ($payload['status'] ?? 'skipped'),
            scope: (string) ($payload['scope'] ?? ''),
            kind: self::firstNonEmptyString($payload['adjustment_type'] ?? null, $payload['target'] ?? null, $payload['type'] ?? null),
            id: self::scalarOrNull($payload['event_id'] ?? null),
            amount: is_int($amount) || is_float($amount) || is_string($amount) ? $amount : null,
            reasonCode: self::stringOrNull($payload['reason'] ?? null),
            reason: self::stringOrNull($payload['reason'] ?? null),
            metadata: self::metadataWithout($payload, [
                'status',
                'scope',
                'adjustment_type',
                'target',
                'type',
                'event_id',
                'value',
                'reason',
            ]),
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function couponValidation(
        CouponKind $kind,
        string $code,
        string $status,
        ?CouponData $coupon = null,
        int|float|string|null $amount = null,
        ?float $finalTotal = null,
        ?string $reasonCode = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        return new self(
            stage: 'coupon_validate',
            source: 'coupon',
            status: $status,
            scope: $coupon instanceof CouponData ? (string) $coupon->scope : '',
            kind: $kind->value,
            code: $coupon instanceof CouponData ? $coupon->code : $code,
            id: self::couponId($coupon),
            amount: $amount,
            finalTotal: $finalTotal,
            reasonCode: $reasonCode,
            reason: $reason,
            metadata: array_replace($metadata, [
                'coupon_kind' => $kind->value,
            ]),
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function fromArray(array $entry): self
    {
        return new self(
            stage: (string) ($entry['stage'] ?? ''),
            source: (string) ($entry['source'] ?? ''),
            status: (string) ($entry['status'] ?? ''),
            scope: (string) ($entry['scope'] ?? ''),
            kind: (string) ($entry['kind'] ?? ''),
            code: self::stringOrNull($entry['code'] ?? null),
            id: self::scalarOrNull($entry['id'] ?? null),
            amount: self::amountOrNull($entry['amount'] ?? null),
            finalTotal: is_numeric($entry['final_total'] ?? null) ? (float) $entry['final_total'] : null,
            reasonCode: self::stringOrNull($entry['reason_code'] ?? null),
            reason: self::stringOrNull($entry['reason'] ?? null),
            metadata: is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
        );
    }

    public function withStage(string $stage, ?string $status = null): self
    {
        return new self(
            stage: $stage,
            source: $this->source,
            status: $status ?? $this->status,
            scope: $this->scope,
            kind: $this->kind,
            code: $this->code,
            id: $this->id,
            amount: $this->amount,
            finalTotal: $this->finalTotal,
            reasonCode: $this->reasonCode,
            reason: $this->reason,
            metadata: $this->metadata,
        );
    }

    /**
     * @return array{
     *   stage:string,
     *   source:string,
     *   status:string,
     *   scope:string,
     *   kind:string,
     *   code:string|null,
     *   id:int|string|null,
     *   amount:int|float|string|null,
     *   final_total:float|null,
     *   reason_code:string|null,
     *   reason:string|null,
     *   metadata:array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'stage'       => $this->stage,
            'source'      => $this->source,
            'status'      => $this->status,
            'scope'       => $this->scope,
            'kind'        => $this->kind,
            'code'        => $this->code,
            'id'          => $this->id,
            'amount'      => $this->amount,
            'final_total' => $this->finalTotal,
            'reason_code' => $this->reasonCode,
            'reason'      => $this->reason,
            'metadata'    => $this->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private static function metadataWithout(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    private static function couponId(?CouponData $coupon): int|string|null
    {
        if (! $coupon instanceof CouponData) {
            return null;
        }

        return self::scalarOrNull($coupon->attributes['coupon_id'] ?? $coupon->attributes['id'] ?? null);
    }

    private static function firstNonEmptyString(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_int($value) || is_string($value)) {
                $string = (string) $value;
                if ($string !== '') {
                    return $string;
                }
            }
        }

        return '';
    }

    private static function amountOrNull(mixed $value): int|float|string|null
    {
        return is_int($value) || is_float($value) || is_string($value) ? $value : null;
    }

    private static function scalarOrNull(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) ? $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
