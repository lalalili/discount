<?php

declare(strict_types=1);

namespace Discount\Kernel\Support;

use BackedEnum;
use Discount\Kernel\Contexts\CartLineContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\DTOs\CartPromotionRefreshInput;

final class PromotionRefreshFingerprint
{
    /**
     * @param array<int|string, PromotionSet> $promotionSetsByProductId
     */
    public function promotionVersion(array $promotionSetsByProductId): string
    {
        $versions = [];

        foreach ($promotionSetsByProductId as $promotionSet) {
            foreach ($promotionSet->promotions as $promotion) {
                $versions[] = implode(':', [
                    (string) ($promotion->eventId ?? ''),
                    (string) ($promotion->sort ?? ''),
                    $this->promotionUpdatedAt($promotion),
                ]);
            }
        }

        sort($versions);

        return hash('xxh128', implode('|', $versions));
    }

    /**
     * @param list<string> $lineAttributeKeys
     */
    public function inputSignature(CartPromotionRefreshInput $input, array $lineAttributeKeys = []): string
    {
        $promotionVersion = $this->promotionVersion($input->promotionSetsByProductId);

        return $this->promotionRefreshSignature(
            lines: $input->lines,
            giftFulfillment: $input->giftFulfillment,
            promotionVersion: $promotionVersion,
            lineAttributeKeys: $lineAttributeKeys,
        );
    }

    /**
     * @param list<CartLineContext> $lines
     * @param list<string> $lineAttributeKeys
     */
    public function promotionRefreshSignature(
        array $lines,
        string $giftFulfillment,
        string $promotionVersion,
        array $lineAttributeKeys = [],
    ): string {
        $linePayload = [];

        foreach ($lines as $line) {
            $linePayload[] = implode(':', [
                (string) $line->productId,
                (string) $line->quantity,
                (string) $line->unitPrice,
                $line->associatedModel,
                $this->lineAttributesSignature($line, $lineAttributeKeys),
            ]);
        }

        sort($linePayload);

        return hash('xxh128', implode('|', [
            $giftFulfillment,
            $promotionVersion,
            implode(',', $linePayload),
        ]));
    }

    private function promotionUpdatedAt(PromotionContext $promotion): string
    {
        $value = $promotion->attributes['updated_at_timestamp']
            ?? $promotion->attributes['updated_at']
            ?? '';

        if ($value instanceof \DateTimeInterface) {
            return (string) $value->getTimestamp();
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param list<string> $lineAttributeKeys
     */
    private function lineAttributesSignature(CartLineContext $line, array $lineAttributeKeys): string
    {
        if ($lineAttributeKeys === []) {
            return '';
        }

        $attributes = [];

        foreach ($lineAttributeKeys as $key) {
            $attributes[$key] = $line->attributes[$key] ?? null;
        }

        return $this->stableEncode($attributes);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stableEncode(array $value): string
    {
        ksort($value);

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }
}
