<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class CartPromotionRefreshResult
{
    /**
     * @var list<array<string, mixed>>
     */
    public readonly array $promotionDecisions;

    public readonly PricingTrace $pricingTrace;

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
     * @param list<PromotionDecision|array<string, mixed>>|null $promotionDecisions
     * @param PricingTrace|list<PricingTraceEntry|array<string, mixed>>|null $pricingTrace
     */
    public function __construct(
        public readonly array $itemAdjustmentsByLineId,
        public readonly array $cartAdjustments,
        public readonly array $selectedGroupRebateEventIds,
        public readonly array $metadata = [],
        public readonly array $appliedPromotions = [],
        public readonly array $skippedPromotions = [],
        public readonly array $totals = [],
        ?array $promotionDecisions = null,
        PricingTrace|array|null $pricingTrace = null,
    ) {
        $this->promotionDecisions = $this->normalizePromotionDecisions($promotionDecisions);
        $this->pricingTrace = $pricingTrace instanceof PricingTrace
            ? $pricingTrace
            : new PricingTrace($pricingTrace ?? PricingTrace::fromPromotionDecisions($this->promotionDecisions)->entries);
    }

    /**
     * @param list<PromotionDecision|array<string, mixed>>|null $promotionDecisions
     * @return list<array<string, mixed>>
     */
    private function normalizePromotionDecisions(?array $promotionDecisions): array
    {
        $decisions = $promotionDecisions;

        if ($decisions === null) {
            $decisions = array_merge(
                array_map(
                    static fn (array $promotion): PromotionDecision => PromotionDecision::fromAppliedPromotion($promotion),
                    $this->appliedPromotions,
                ),
                array_map(
                    static fn (array $promotion): PromotionDecision => PromotionDecision::fromSkippedPromotion($promotion),
                    $this->skippedPromotions,
                ),
            );
        }

        return array_map(
            static fn (PromotionDecision|array $decision): array => $decision instanceof PromotionDecision
                ? $decision->toArray()
                : $decision,
            $decisions,
        );
    }
}
