<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class CartPromotionRefreshResult
{
    /**
     * @param array<int|string, list<array{
     *   name:string,
     *   type:'discount'|'rebate'|'gift',
     *   target:'item'|'total',
     *   value:string|float|int,
     *   order:int,
     *   attributes:array<string, mixed>
     * }>> $itemAdjustmentsByLineId
     * @param list<array{
     *   name:string,
     *   type:'rebate'|'gift',
     *   target:'total',
     *   value:string|float|int,
     *   order:int,
     *   attributes:array<string, mixed>
     * }> $cartAdjustments
     * @param array<int|string, int> $selectedGroupRebateEventIds
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $appliedPromotions
     * @param list<array<string, mixed>> $skippedPromotions
     * @param array<string, mixed> $totals
     */
    public function __construct(
        public readonly array $itemAdjustmentsByLineId,
        public readonly array $cartAdjustments,
        public readonly array $selectedGroupRebateEventIds,
        public readonly array $metadata = [],
        public readonly array $appliedPromotions = [],
        public readonly array $skippedPromotions = [],
        public readonly array $totals = [],
    ) {
    }
}
