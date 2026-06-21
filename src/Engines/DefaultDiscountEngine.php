<?php

declare(strict_types=1);

namespace Lalalili\Discount\Engines;

use Lalalili\Discount\Contexts\ProductContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\Contracts\DiscountEngineInterface;
use Lalalili\Discount\DTOs\PriceResult;
use Lalalili\Discount\Support\DiscountConfig;

final class DefaultDiscountEngine implements DiscountEngineInterface
{
    public function price(ProductContext $product, PromotionSet $promotions): PriceResult
    {
        $price = $product->listPrice;
        $groupedPromotions = $this->groupByRole($promotions->promotions);

        foreach ($this->pricingPriorities() as $role) {
            $rolePromotions = $groupedPromotions[$role] ?? [];

            if ($rolePromotions === []) {
                continue;
            }

            if ($role === 'exclusive_price') {
                $promotion = $this->firstSorted($rolePromotions);
                if ($promotion instanceof PromotionContext) {
                    $candidate = (float) ($promotion->discountAmount ?? 0);

                    return new PriceResult($candidate > 0 ? $candidate : $price);
                }

                continue;
            }

            if ($role === 'exclusive_discount') {
                $promotion = $this->firstSorted($rolePromotions);
                if ($promotion instanceof PromotionContext) {
                    return new PriceResult($this->applyDiscountValue($price, $promotion->discountAmount));
                }

                continue;
            }

            if ($role === 'group_rebate') {
                $promotion = $this->firstSorted($rolePromotions);
                if ($promotion instanceof PromotionContext) {
                    $value = $promotion->rebateGetAmount ?? $promotion->discountAmount;

                    return new PriceResult($this->applyDiscountValue($price, $value));
                }

                continue;
            }

            if ($role === 'single_discount') {
                $promotion = $this->firstSorted($rolePromotions);
                if ($promotion instanceof PromotionContext) {
                    return new PriceResult($this->applyDiscountValue($price, $promotion->discountAmount));
                }

                continue;
            }

            if ($role === 'stackable_discount') {
                foreach ($this->sortPromotions($rolePromotions) as $promotion) {
                    $price = $this->applyDiscountValue($price, $promotion->discountAmount);
                }
            }
        }

        return new PriceResult($price);
    }

    /**
     * @param list<PromotionContext> $promotions
     * @return array<string, list<PromotionContext>>
     */
    private function groupByRole(array $promotions): array
    {
        $grouped = [];

        foreach ($promotions as $promotion) {
            $role = $this->resolveRole($promotion->type);

            if ($role === null || $role === '') {
                continue;
            }

            $grouped[$role] ??= [];
            $grouped[$role][] = $promotion;
        }

        return $grouped;
    }

    private function resolveRole(int $type): ?string
    {
        $typeRoleMap = DiscountConfig::get('event.type_role_map', []);

        if (! is_array($typeRoleMap)) {
            return null;
        }

        if (array_key_exists($type, $typeRoleMap) && is_string($typeRoleMap[$type])) {
            return $typeRoleMap[$type];
        }

        $stringType = (string) $type;

        if (array_key_exists($stringType, $typeRoleMap) && is_string($typeRoleMap[$stringType])) {
            return $typeRoleMap[$stringType];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pricingPriorities(): array
    {
        $priorities = DiscountConfig::get('event.priorities.pricing', []);

        if (! is_array($priorities) || $priorities === []) {
            return [
                'exclusive_price',
                'exclusive_discount',
                'group_rebate',
                'single_discount',
                'stackable_discount',
            ];
        }

        return array_values(array_filter(
            $priorities,
            static fn (mixed $priority): bool => is_string($priority) && $priority !== ''
        ));
    }

    /**
     * @param list<PromotionContext> $promotions
     */
    private function firstSorted(array $promotions): ?PromotionContext
    {
        $sorted = $this->sortPromotions($promotions);

        return $sorted[0] ?? null;
    }

    /**
     * @param list<PromotionContext> $promotions
     * @return list<PromotionContext>
     */
    private function sortPromotions(array $promotions): array
    {
        usort(
            $promotions,
            static fn (PromotionContext $a, PromotionContext $b): int => ($a->sort ?? PHP_INT_MAX) <=> ($b->sort ?? PHP_INT_MAX)
        );

        return $promotions;
    }

    private function applyDiscountValue(float $price, float|int|null $value): float
    {
        if ($value === null) {
            return $price;
        }

        $numeric = (float) $value;
        if ($numeric <= 0) {
            return $price;
        }

        if ($numeric < 1) {
            return $price * $numeric;
        }

        return $price - $numeric;
    }
}
