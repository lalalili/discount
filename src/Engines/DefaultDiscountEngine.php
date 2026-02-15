<?php

declare(strict_types=1);

namespace Cptw\DiscountKernel\Engines;

use Cptw\DiscountKernel\Contexts\ProductContext;
use Cptw\DiscountKernel\Contexts\PromotionContext;
use Cptw\DiscountKernel\Contexts\PromotionSet;
use Cptw\DiscountKernel\Contracts\DiscountEngineInterface;
use Cptw\DiscountKernel\DTOs\PriceResult;

final class DefaultDiscountEngine implements DiscountEngineInterface
{
    public function price(ProductContext $product, PromotionSet $promotions): PriceResult
    {
        $price = $product->listPrice;

        $type8 = $this->firstByType($promotions->promotions, 8);
        if ($type8 instanceof PromotionContext) {
            $candidate = (float) ($type8->discountAmount ?? 0);

            return new PriceResult($candidate > 0 ? $candidate : $price);
        }

        $type7 = $this->firstByType($promotions->promotions, 7);
        if ($type7 instanceof PromotionContext) {
            return new PriceResult($this->applyDiscountValue($price, $type7->discountAmount));
        }

        $type6 = $this->firstByType($promotions->promotions, 6);
        if ($type6 instanceof PromotionContext) {
            $value = $type6->rebateGetAmount ?? $type6->discountAmount;

            return new PriceResult($this->applyDiscountValue($price, $value));
        }

        $type1 = $this->firstByType($promotions->promotions, 1);
        if ($type1 instanceof PromotionContext) {
            return new PriceResult($this->applyDiscountValue($price, $type1->discountAmount));
        }

        foreach ($this->allByType($promotions->promotions, 2) as $promotion) {
            $price = $this->applyDiscountValue($price, $promotion->discountAmount);
        }

        return new PriceResult($price);
    }

    /**
     * @param list<PromotionContext> $promotions
     */
    private function firstByType(array $promotions, int $type): ?PromotionContext
    {
        $candidates = array_values(array_filter(
            $promotions,
            static fn (PromotionContext $promotion): bool => $promotion->type === $type
        ));

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static fn (PromotionContext $a, PromotionContext $b): int => ($a->sort ?? PHP_INT_MAX) <=> ($b->sort ?? PHP_INT_MAX)
        );

        return $candidates[0] ?? null;
    }

    /**
     * @param list<PromotionContext> $promotions
     * @return list<PromotionContext>
     */
    private function allByType(array $promotions, int $type): array
    {
        return array_values(array_filter(
            $promotions,
            static fn (PromotionContext $promotion): bool => $promotion->type === $type
        ));
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
