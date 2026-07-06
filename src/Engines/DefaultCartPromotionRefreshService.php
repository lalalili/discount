<?php

declare(strict_types=1);

namespace Lalalili\Discount\Engines;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CartLineContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\Contracts\CartPromotionEngineInterface;
use Lalalili\Discount\Contracts\CartPromotionRefreshServiceInterface;
use Lalalili\Discount\DTOs\CartPromotionRefreshInput;
use Lalalili\Discount\DTOs\CartPromotionRefreshResult;
use Lalalili\Discount\DTOs\PromotionDecisionReason;
use Lalalili\Discount\Support\DiscountConfig;

final class DefaultCartPromotionRefreshService implements CartPromotionRefreshServiceInterface
{
    public function __construct(
        private ?CartPromotionEngineInterface $cartPromotionEngine = null,
    ) {
    }

    public function refresh(CartPromotionRefreshInput $input): CartPromotionRefreshResult
    {
        $selectedGroupRebateEventIds = $this->resolveSelectedGroupRebateEventIds(
            $input->lines,
            $input->promotionSetsByProductId,
        );

        $itemAdjustmentsByLineId = [];
        $appliedPromotions = [];
        $skippedPromotions = [];

        foreach ($input->lines as $line) {
            $promotionSet = $this->promotionSetForProductId($line->productId, $input->promotionSetsByProductId);

            if (! $promotionSet instanceof PromotionSet || $promotionSet->promotions === []) {
                continue;
            }

            $selectedEventId = $selectedGroupRebateEventIds[$line->productId] ?? null;
            $result = $this->resolveCartPromotionEngine()->apply(
                $this->cartContextForLine($input->cartContext, $line, $selectedEventId),
                $promotionSet,
            );

            if ($result->adjustments !== []) {
                $itemAdjustmentsByLineId[$line->id] = $result->adjustments;
                $appliedPromotions = array_merge(
                    $appliedPromotions,
                    $this->appliedPromotionsForLine($line, $result->adjustments),
                );
            }

            $skippedPromotions = array_merge(
                $skippedPromotions,
                $this->skippedPromotionsForLine($line, $promotionSet, $result->adjustments, $selectedEventId),
            );
        }

        // 每 line 折後金額只算一次,供 buildCartAdjustments 與 buildTotals 共用
        $lineNetAmounts = $this->lineNetAmounts($input->lines, $itemAdjustmentsByLineId);

        $cartAdjustments = $this->buildCartAdjustments(
            $input->lines,
            $itemAdjustmentsByLineId,
            $input->giftFulfillment,
            $skippedPromotions,
            $lineNetAmounts,
        );
        $appliedPromotions = array_merge(
            $appliedPromotions,
            $this->appliedPromotionsForCart($cartAdjustments),
        );
        $totals = $this->buildTotals($input->lines, $cartAdjustments, $lineNetAmounts);

        return new CartPromotionRefreshResult(
            itemAdjustmentsByLineId: $itemAdjustmentsByLineId,
            cartAdjustments: $cartAdjustments,
            selectedGroupRebateEventIds: $selectedGroupRebateEventIds,
            metadata: [
                'line_count'                  => count($input->lines),
                'selected_group_rebate_count' => count($selectedGroupRebateEventIds),
                'applied_count'               => count($appliedPromotions),
                'skipped_count'               => count($skippedPromotions),
            ],
            appliedPromotions: $appliedPromotions,
            skippedPromotions: $skippedPromotions,
            totals: $totals,
        );
    }

    /**
     * @param list<CartLineContext> $lines
     * @param array<int|string, PromotionSet> $promotionSetsByProductId
     * @return array<int|string, int>
     */
    private function resolveSelectedGroupRebateEventIds(array $lines, array $promotionSetsByProductId): array
    {
        $rebateTriggerItems = [];

        foreach ($lines as $line) {
            if ($line->associatedModel !== 'Product') {
                continue;
            }

            $promotionSet = $this->promotionSetForProductId($line->productId, $promotionSetsByProductId);
            if (! $promotionSet instanceof PromotionSet) {
                continue;
            }

            $groupPromotions = $this->sortPromotions(array_values(array_filter(
                $promotionSet->promotions,
                fn (PromotionContext $promotion): bool => $this->isGroupRebateType($promotion->type)
                    && $promotion->eventId !== null,
            )));

            if ($groupPromotions === []) {
                continue;
            }

            $meet = $this->firstPromotionMatchingQuantity($groupPromotions, $line->quantity);
            $eligiblePromotions = $meet instanceof PromotionContext
                ? array_values(array_filter(
                    $groupPromotions,
                    fn (PromotionContext $promotion): bool => $this->promotionSort($promotion) <= $this->promotionSort($meet),
                ))
                : $groupPromotions;

            $rebateTriggerItems[$line->productId] = [
                'promotions' => $groupPromotions,
                'meet'       => $meet,
                'event_ids'  => array_values(array_filter(
                    array_map(static fn (PromotionContext $promotion): ?int => $promotion->eventId, $eligiblePromotions),
                    static fn (?int $eventId): bool => $eventId !== null,
                )),
                'quantity' => $line->quantity,
                'max'      => $meet instanceof PromotionContext
                    ? $this->maxRebateTriggerAmount($eligiblePromotions)
                    : 0,
            ];
        }

        if ($rebateTriggerItems === []) {
            return [];
        }

        uasort(
            $rebateTriggerItems,
            static fn (array $first, array $second): int => (int) $second['max'] <=> (int) $first['max'],
        );

        $eventIdsByProductId = [];
        $quantitySum = 0;
        foreach ($rebateTriggerItems as $productId => $item) {
            $eventIdsByProductId[$productId] = $item['event_ids'];
            $quantitySum += (int) $item['quantity'];
        }

        // 交集與數量總和以 dirty-flag 增量維護:集合縮減(unset)才重算,
        // 一般路徑整段選擇迴圈只算一次交集(counting 版,O(L×E))。
        $selected = [];
        $sharedEventIds = $this->intersectEventIds($eventIdsByProductId);
        $intersectionDirty = false;

        foreach ($rebateTriggerItems as $productId => $item) {
            if ($intersectionDirty) {
                $sharedEventIds = $this->intersectEventIds($eventIdsByProductId);
                $intersectionDirty = false;
            }

            $groupEvent = $this->firstSharedGroupPromotion(
                $item['promotions'],
                $sharedEventIds,
                $quantitySum,
            );

            $meet = $item['meet'];
            if ($meet instanceof PromotionContext) {
                $eventId = $meet->eventId;

                if ($groupEvent instanceof PromotionContext && $this->promotionSort($groupEvent) < $this->promotionSort($meet)) {
                    $eventId = $groupEvent->eventId;
                } elseif ($groupEvent instanceof PromotionContext && $eventId !== $groupEvent->eventId) {
                    unset($eventIdsByProductId[$productId]);
                    $quantitySum -= (int) $item['quantity'];
                    $intersectionDirty = true;
                }
            } else {
                $eventId = $groupEvent?->eventId;
            }

            if ($eventId !== null) {
                $selected[$productId] = $eventId;
            }
        }

        return $selected;
    }

    /**
     * @param list<PromotionContext> $promotions
     */
    private function firstPromotionMatchingQuantity(array $promotions, int $quantity): ?PromotionContext
    {
        foreach ($promotions as $promotion) {
            if ((float) ($promotion->rebateTriggerAmount ?? 0) <= $quantity) {
                return $promotion;
            }
        }

        return null;
    }

    /**
     * @param list<PromotionContext> $promotions
     * @param list<int> $sharedEventIds
     */
    private function firstSharedGroupPromotion(array $promotions, array $sharedEventIds, int $quantity): ?PromotionContext
    {
        foreach ($promotions as $promotion) {
            if ($promotion->eventId === null || ! in_array($promotion->eventId, $sharedEventIds, true)) {
                continue;
            }

            if ((float) ($promotion->rebateTriggerAmount ?? 0) <= $quantity) {
                return $promotion;
            }
        }

        return null;
    }

    /**
     * counting 版跨集合交集(O(總 id 數),取代逐對 array_intersect):
     * 以「出現於幾個集合」計數,保留第一個集合的元素順序(與 array_intersect 語意一致)。
     *
     * @param array<int|string, list<int>> $eventIdsByProductId
     * @return list<int>
     */
    private function intersectEventIds(array $eventIdsByProductId): array
    {
        $eventIdSets = array_values($eventIdsByProductId);
        $first = array_shift($eventIdSets);

        if ($first === null) {
            return [];
        }

        if ($eventIdSets === []) {
            return array_map(static fn (int|string $eventId): int => (int) $eventId, $first);
        }

        $presenceCounts = [];
        foreach ($eventIdSets as $eventIds) {
            foreach (array_unique($eventIds) as $eventId) {
                $presenceCounts[$eventId] = ($presenceCounts[$eventId] ?? 0) + 1;
            }
        }

        $requiredCount = count($eventIdSets);
        $intersection = [];

        foreach ($first as $eventId) {
            if (($presenceCounts[$eventId] ?? 0) === $requiredCount) {
                $intersection[] = (int) $eventId;
            }
        }

        return $intersection;
    }

    /**
     * @param list<PromotionContext> $promotions
     */
    private function maxRebateTriggerAmount(array $promotions): int
    {
        $max = 0;

        foreach ($promotions as $promotion) {
            $max = max($max, (int) ($promotion->rebateTriggerAmount ?? 0));
        }

        return $max;
    }

    /**
     * @param list<PromotionContext> $promotions
     * @return list<PromotionContext>
     */
    private function sortPromotions(array $promotions): array
    {
        usort(
            $promotions,
            fn (PromotionContext $first, PromotionContext $second): int => $this->promotionSort($first) <=> $this->promotionSort($second),
        );

        return $promotions;
    }

    private function promotionSort(PromotionContext $promotion): int
    {
        return (int) ($promotion->sort ?? 0);
    }

    /**
     * @param array<int|string, PromotionSet> $promotionSetsByProductId
     */
    private function promotionSetForProductId(int|string $productId, array $promotionSetsByProductId): ?PromotionSet
    {
        if (array_key_exists($productId, $promotionSetsByProductId)) {
            return $promotionSetsByProductId[$productId];
        }

        $key = (string) $productId;

        return $promotionSetsByProductId[$key] ?? null;
    }

    private function cartContextForLine(CartContext $cartContext, CartLineContext $line, ?int $selectedGroupRebateEventId): CartContext
    {
        return new CartContext(
            orderTotal: $cartContext->orderTotal,
            allAmount: $cartContext->allAmount,
            bookAmount: $cartContext->bookAmount,
            ebookAmount: $cartContext->ebookAmount,
            specificProductsAmount: $cartContext->specificProductsAmount,
            hasBook: $cartContext->hasBook,
            hasEbook: $cartContext->hasEbook,
            hasSpecificProducts: $cartContext->hasSpecificProducts,
            productId: is_numeric((string) $line->productId) ? (int) $line->productId : null,
            productPrice: $line->unitPrice,
            selectedGroupRebateEventId: $selectedGroupRebateEventId,
            meta: $cartContext->meta,
        );
    }

    /**
     * @param list<CartLineContext> $lines
     * @param array<int|string, list<array<string, mixed>>> $itemAdjustmentsByLineId
     * @param list<array<string, mixed>> $skippedPromotions
     * @param array<int|string, float> $lineNetAmounts
     * @return list<array{name:string,type:'rebate'|'gift',target:'total',value:string|float|int,order:int,attributes:array<string, mixed>}>
     */
    private function buildCartAdjustments(
        array $lines,
        array $itemAdjustmentsByLineId,
        string $giftFulfillment,
        array &$skippedPromotions,
        array $lineNetAmounts,
    ): array {
        $linesById = [];
        foreach ($lines as $line) {
            $linesById[$line->id] = $line;
        }

        $rebates = [];
        $gifts = [];

        foreach ($itemAdjustmentsByLineId as $lineId => $adjustments) {
            $line = $linesById[$lineId] ?? null;
            if (! $line instanceof CartLineContext) {
                continue;
            }

            $lineAmount = $lineNetAmounts[$lineId] ?? $this->lineAmountWithDiscounts($line, $adjustments);

            foreach ($adjustments as $adjustment) {
                $type = (string) ($adjustment['type'] ?? '');
                $attributes = is_array($adjustment['attributes'] ?? null) ? $adjustment['attributes'] : [];
                $eventId = $this->nullableInt($attributes['event_id'] ?? null);

                if ($eventId === null) {
                    continue;
                }

                if ($type === 'rebate') {
                    $rebates[$eventId] = $this->appendAggregate($rebates[$eventId] ?? null, $adjustment, $attributes, $line, $lineAmount);
                }

                if ($type === 'gift') {
                    $gifts[$eventId] = $this->appendAggregate($gifts[$eventId] ?? null, $adjustment, $attributes, $line, $lineAmount);
                }
            }
        }

        return array_merge(
            $this->buildRebateCartAdjustments($rebates, $skippedPromotions),
            $this->buildGiftCartAdjustments($gifts, $giftFulfillment, $skippedPromotions),
        );
    }

    /**
     * @param array<string, mixed>|null $entry
     * @param array<string, mixed> $adjustment
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function appendAggregate(
        ?array $entry,
        array $adjustment,
        array $attributes,
        CartLineContext $line,
        float $lineAmount,
    ): array {
        $entry ??= [
            'name'         => (string) ($adjustment['name'] ?? ''),
            'type'         => (string) ($adjustment['type'] ?? ''),
            'order'        => (int) ($adjustment['order'] ?? 0),
            'attributes'   => $attributes,
            'sum_amount'   => 0.0,
            'sum_quantity' => 0,
            'products'     => [],
            'sort'         => (int) ($attributes['sort'] ?? 0),
        ];

        $entry['sum_amount'] = (float) $entry['sum_amount'] + $lineAmount;
        $entry['sum_quantity'] = (int) $entry['sum_quantity'] + $line->quantity;

        $productCode = $this->productCode($line);
        if ($productCode !== null) {
            $entry['products'][] = $productCode;
        }

        return $entry;
    }

    /**
     * @param array<int, array<string, mixed>> $rebates
     * @param list<array<string, mixed>> $skippedPromotions
     * @return list<array{name:string,type:'rebate',target:'total',value:string|float|int,order:int,attributes:array<string, mixed>}>
     */
    private function buildRebateCartAdjustments(array $rebates, array &$skippedPromotions): array
    {
        usort($rebates, static fn (array $first, array $second): int => (int) $first['sort'] <=> (int) $second['sort']);

        $adjustments = [];
        foreach ($rebates as $rebate) {
            $attributes = is_array($rebate['attributes'] ?? null) ? $rebate['attributes'] : [];
            $triggerAmount = (float) ($attributes['rebate_trigger_amount'] ?? 0);

            if ($triggerAmount <= 0 || (float) $rebate['sum_amount'] < $triggerAmount) {
                $skippedPromotions[] = $this->cartSkippedPromotion($rebate, PromotionDecisionReason::THRESHOLD_NOT_MET);
                continue;
            }

            $times = ! empty($attributes['repeatable'])
                ? max(1, (int) floor((float) $rebate['sum_amount'] / $triggerAmount))
                : 1;
            $rebateAmount = (float) ($attributes['rebate_get_amount'] ?? 0) * $times;

            $attributes['sum_amount'] = $rebate['sum_amount'];
            $attributes['sum_quantity'] = $rebate['sum_quantity'];
            $attributes['products'] = $rebate['products'];

            $adjustments[] = [
                'name'       => (string) $rebate['name'],
                'type'       => 'rebate',
                'target'     => 'total',
                'value'      => '-' . $this->formatNumber($rebateAmount),
                'order'      => $this->resolveTypeOrder($this->nullableInt($attributes['type'] ?? null) ?? (int) $rebate['order']),
                'attributes' => $attributes,
            ];
        }

        return $adjustments;
    }

    /**
     * @param array<int, array<string, mixed>> $gifts
     * @param list<array<string, mixed>> $skippedPromotions
     * @return list<array{name:string,type:'gift',target:'total',value:string|float|int,order:int,attributes:array<string, mixed>}>
     */
    private function buildGiftCartAdjustments(array $gifts, string $giftFulfillment, array &$skippedPromotions): array
    {
        usort($gifts, static fn (array $first, array $second): int => (int) $first['sort'] <=> (int) $second['sort']);

        $adjustments = [];
        foreach ($gifts as $gift) {
            $attributes = is_array($gift['attributes'] ?? null) ? $gift['attributes'] : [];
            $needAmount = (float) ($attributes['gift_trigger_amount'] ?? 0);
            $needQuantity = (int) ($attributes['gift_trigger_quantity'] ?? 0);
            $sumAmount = (float) $gift['sum_amount'];
            $sumQuantity = (int) $gift['sum_quantity'];

            if (($needAmount > 0 && $sumAmount < $needAmount) || ($needQuantity > 0 && $sumQuantity < $needQuantity)) {
                $skippedPromotions[] = $this->cartSkippedPromotion($gift, PromotionDecisionReason::THRESHOLD_NOT_MET);
                continue;
            }

            $giftQuantity = 1;
            if (! empty($attributes['repeatable'])) {
                $amountTimes = $needAmount > 0 ? (int) floor($sumAmount / $needAmount) : 0;
                $quantityTimes = $needQuantity > 0 ? (int) floor($sumQuantity / $needQuantity) : 0;
                $giftQuantity = max($amountTimes, $quantityTimes);
            }

            if ($giftQuantity <= 0) {
                continue;
            }

            $attributes['sum_amount'] = $sumAmount;
            $attributes['sum_quantity'] = $sumQuantity;
            $attributes['gift_quantity'] = $giftQuantity;
            $attributes['products'] = $gift['products'];
            $attributes['gift_code'] = $attributes['gift_code'] ?? $attributes['gift_prod_no'] ?? null;
            $attributes['fulfillment'] = $giftFulfillment === 'add_item' ? 'add_item' : 'condition_only';

            $adjustments[] = [
                'name'       => (string) $gift['name'],
                'type'       => 'gift',
                'target'     => 'total',
                'value'      => 0,
                'order'      => $this->resolveTypeOrder($this->firstConfiguredType('gift_types', (int) $gift['order'])),
                'attributes' => $attributes,
            ];
        }

        return $adjustments;
    }

    /**
     * @param list<array<string, mixed>> $adjustments
     * @return list<array<string, mixed>>
     */
    private function appliedPromotionsForLine(CartLineContext $line, array $adjustments): array
    {
        $applied = [];

        foreach ($adjustments as $adjustment) {
            $attributes = is_array($adjustment['attributes'] ?? null) ? $adjustment['attributes'] : [];
            $eventId = $this->nullableInt($attributes['event_id'] ?? null);

            if ($eventId === null) {
                continue;
            }

            $applied[] = [
                'scope'           => 'line',
                'line_id'         => $line->id,
                'product_id'      => $line->productId,
                'event_id'        => $eventId,
                'type'            => $this->nullableInt($attributes['type'] ?? null),
                'target'          => (string) ($adjustment['target'] ?? 'item'),
                'adjustment_type' => (string) ($adjustment['type'] ?? ''),
                'name'            => (string) ($adjustment['name'] ?? ''),
                'value'           => $adjustment['value'] ?? 0,
            ];
        }

        return $applied;
    }

    /**
     * @param list<array{name:string,type:'rebate'|'gift',target:'total',value:string|float|int,order:int,attributes:array<string, mixed>}> $cartAdjustments
     * @return list<array<string, mixed>>
     */
    private function appliedPromotionsForCart(array $cartAdjustments): array
    {
        $applied = [];

        foreach ($cartAdjustments as $adjustment) {
            $attributes = $adjustment['attributes'];
            $eventId = $this->nullableInt($attributes['event_id'] ?? null);

            if ($eventId === null) {
                continue;
            }

            $applied[] = [
                'scope'           => 'cart',
                'event_id'        => $eventId,
                'type'            => $this->nullableInt($attributes['type'] ?? null),
                'target'          => $adjustment['target'],
                'adjustment_type' => $adjustment['type'],
                'name'            => $adjustment['name'],
                'value'           => $adjustment['value'],
            ];
        }

        return $applied;
    }

    /**
     * @param list<array<string, mixed>> $adjustments
     * @return list<array<string, mixed>>
     */
    private function skippedPromotionsForLine(
        CartLineContext $line,
        PromotionSet $promotionSet,
        array $adjustments,
        ?int $selectedGroupRebateEventId,
    ): array {
        $appliedEventIds = array_values(array_filter(
            array_map(
                fn (array $adjustment): ?int => $this->nullableInt(
                    is_array($adjustment['attributes'] ?? null)
                        ? ($adjustment['attributes']['event_id'] ?? null)
                        : null,
                ),
                $adjustments,
            ),
            static fn (?int $eventId): bool => $eventId !== null,
        ));

        $skipped = [];
        foreach ($promotionSet->promotions as $promotion) {
            if ($promotion->eventId === null || in_array($promotion->eventId, $appliedEventIds, true)) {
                continue;
            }

            $reason = PromotionDecisionReason::NOT_SELECTED;
            if ($this->isGroupRebateType($promotion->type)) {
                $reason = $selectedGroupRebateEventId === null
                    ? PromotionDecisionReason::THRESHOLD_NOT_MET
                    : PromotionDecisionReason::NOT_SELECTED;
            } elseif ($this->isGiftType($promotion->type)) {
                $reason = PromotionDecisionReason::GIFT_UNRESOLVED;
            }

            $skipped[] = [
                'scope'      => 'line',
                'line_id'    => $line->id,
                'product_id' => $line->productId,
                'event_id'   => $promotion->eventId,
                'type'       => $promotion->type,
                'name'       => $promotion->name ?? '',
                'reason'     => $reason,
            ];
        }

        return $skipped;
    }

    /**
     * @param array<string, mixed> $promotion
     * @return array<string, mixed>
     */
    private function cartSkippedPromotion(array $promotion, string $reason): array
    {
        $attributes = is_array($promotion['attributes'] ?? null) ? $promotion['attributes'] : [];

        return [
            'scope'        => 'cart',
            'event_id'     => $this->nullableInt($attributes['event_id'] ?? null),
            'type'         => $this->nullableInt($attributes['type'] ?? null),
            'name'         => (string) ($promotion['name'] ?? ''),
            'reason'       => $reason,
            'sum_amount'   => (float) ($promotion['sum_amount'] ?? 0),
            'sum_quantity' => (int) ($promotion['sum_quantity'] ?? 0),
        ];
    }

    /**
     * @param list<CartLineContext> $lines
     * @param array<int|string, list<array<string, mixed>>> $itemAdjustmentsByLineId
     * @return array<int|string, float> line id → 折後金額(unitPrice 套 item 折扣 × 數量)
     */
    private function lineNetAmounts(array $lines, array $itemAdjustmentsByLineId): array
    {
        $amounts = [];

        foreach ($lines as $line) {
            $amounts[$line->id] = $this->lineAmountWithDiscounts($line, $itemAdjustmentsByLineId[$line->id] ?? []);
        }

        return $amounts;
    }

    /**
     * @param list<CartLineContext> $lines
     * @param list<array{name:string,type:'rebate'|'gift',target:'total',value:string|float|int,order:int,attributes:array<string, mixed>}> $cartAdjustments
     * @param array<int|string, float> $lineNetAmounts
     * @return array<string, mixed>
     */
    private function buildTotals(array $lines, array $cartAdjustments, array $lineNetAmounts): array
    {
        $lineTotals = [];
        $subtotalBefore = 0.0;
        $subtotalAfterItemAdjustments = 0.0;

        foreach ($lines as $line) {
            $lineSubtotalBefore = $line->unitPrice * $line->quantity;
            $lineSubtotalAfter = $lineNetAmounts[$line->id] ?? ($line->unitPrice * $line->quantity);

            $subtotalBefore += $lineSubtotalBefore;
            $subtotalAfterItemAdjustments += $lineSubtotalAfter;

            $lineTotals[] = [
                'line_id'                              => $line->id,
                'product_id'                           => $line->productId,
                'quantity'                             => $line->quantity,
                'unit_price'                           => $line->unitPrice,
                'line_subtotal_before'                 => $lineSubtotalBefore,
                'line_subtotal_after_item_adjustments' => $lineSubtotalAfter,
            ];
        }

        $cartRebateAmount = 0.0;
        foreach ($cartAdjustments as $adjustment) {
            if ($adjustment['type'] !== 'rebate') {
                continue;
            }

            $cartRebateAmount += abs((float) str_replace(['-', ',', ' '], '', (string) $adjustment['value']));
        }

        return [
            'lines'                           => $lineTotals,
            'subtotal_before'                 => $subtotalBefore,
            'subtotal_after_item_adjustments' => $subtotalAfterItemAdjustments,
            'cart_rebate_amount'              => $cartRebateAmount,
            'total_after_cart_rebates'        => max(0.0, $subtotalAfterItemAdjustments - $cartRebateAmount),
        ];
    }

    /**
     * @param list<array<string, mixed>> $adjustments
     */
    private function lineAmountWithDiscounts(CartLineContext $line, array $adjustments): float
    {
        $unitPrice = $line->unitPrice;

        foreach ($adjustments as $adjustment) {
            if (($adjustment['type'] ?? null) !== 'discount') {
                continue;
            }

            $unitPrice = $this->applyAdjustmentValue($unitPrice, $adjustment['value'] ?? 0);
        }

        return max(0.0, $unitPrice) * $line->quantity;
    }

    private function applyAdjustmentValue(float $amount, mixed $value): float
    {
        $raw = trim((string) $value);
        $subtracted = str_starts_with($raw, '-');
        $clean = str_replace(['+', '-', ',', ' '], '', $raw);

        if (str_contains($clean, '%')) {
            $percent = (float) str_replace('%', '', $clean);
            $delta = $amount * ($percent / 100);

            return max(0.0, $subtracted ? $amount - $delta : $amount + $delta);
        }

        $delta = (float) $clean;

        return max(0.0, $subtracted ? $amount - $delta : $amount + $delta);
    }

    private function productCode(CartLineContext $line): ?string
    {
        foreach (['product_code', 'prod_no', 'sku', 'number'] as $key) {
            $value = $line->attributes[$key] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function formatNumber(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function resolveTypeOrder(int $type): int
    {
        $typeOrderMap = DiscountConfig::get('event.priorities.type_order', []);

        if (! is_array($typeOrderMap)) {
            return $type;
        }

        return (int) ($typeOrderMap[$type] ?? $typeOrderMap[(string) $type] ?? $type);
    }

    private function isGroupRebateType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('group_rebate_types'), true);
    }

    private function isGiftType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('gift_types'), true);
    }

    private function firstConfiguredType(string $key, int $fallback): int
    {
        $types = $this->resolveTypeSet($key);

        return $types[0] ?? $fallback;
    }

    /**
     * @return list<int>
     */
    private function resolveTypeSet(string $key): array
    {
        $set = DiscountConfig::get('cart.roles.' . $key, []);

        if (! is_array($set)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): int => (int) $value, $set));
    }

    private function resolveCartPromotionEngine(): CartPromotionEngineInterface
    {
        if ($this->cartPromotionEngine instanceof CartPromotionEngineInterface) {
            return $this->cartPromotionEngine;
        }

        if (function_exists('app') && app()->bound(CartPromotionEngineInterface::class)) {
            /** @var CartPromotionEngineInterface $engine */
            $engine = app(CartPromotionEngineInterface::class);
            $this->cartPromotionEngine = $engine;

            return $this->cartPromotionEngine;
        }

        $this->cartPromotionEngine = new DefaultCartPromotionEngine();

        return $this->cartPromotionEngine;
    }
}
