<?php

declare(strict_types=1);

namespace Discount\Kernel\Engines;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\Contexts\PromotionContext;
use Discount\Kernel\Contexts\PromotionSet;
use Discount\Kernel\Contracts\CartPromotionEngineInterface;
use Discount\Kernel\Contracts\GiftResolverInterface;
use Discount\Kernel\DTOs\CartAdjustmentResult;
use Discount\Kernel\Resolvers\NullGiftResolver;
use Discount\Kernel\Support\DiscountConfig;

final class DefaultCartPromotionEngine implements CartPromotionEngineInterface
{
    private ?GiftResolverInterface $giftResolver = null;

    public function apply(CartContext $cart, PromotionSet $promotions): CartAdjustmentResult
    {
        $adjustments = [];
        $promotionItems = $promotions->promotions;

        if ($promotionItems === []) {
            return new CartAdjustmentResult([]);
        }

        $selectedEventId = $cart->selectedGroupRebateEventId;

        if ($selectedEventId !== null) {
            $selectedGroupRebate = $this->findSelectedGroupRebate($promotionItems, $selectedEventId);

            if ($selectedGroupRebate instanceof PromotionContext) {
                $adjustments[] = $this->buildGroupRebateDiscount($selectedGroupRebate);

                foreach ($promotionItems as $promotion) {
                    if (! $this->isGiftType($promotion->type)) {
                        continue;
                    }

                    $giftAdjustment = $this->buildGiftAdjustment($promotion);

                    if ($giftAdjustment !== null) {
                        $adjustments[] = $giftAdjustment;
                    }
                }

                return new CartAdjustmentResult($adjustments);
            }
        }

        foreach ($promotionItems as $promotion) {
            if ($this->isDiscountType($promotion->type)) {
                $adjustments[] = $this->buildDiscountAdjustment($promotion, $cart->productPrice);

                continue;
            }

            if ($this->isGiftType($promotion->type)) {
                $giftAdjustment = $this->buildGiftAdjustment($promotion);

                if ($giftAdjustment !== null) {
                    $adjustments[] = $giftAdjustment;
                }

                continue;
            }

            if ($this->isRebateType($promotion->type)) {
                $adjustments[] = $this->buildRebateAdjustment($promotion);
            }
        }

        return new CartAdjustmentResult($adjustments);
    }

    /**
     * @param list<PromotionContext> $promotions
     */
    private function findSelectedGroupRebate(array $promotions, int $selectedEventId): ?PromotionContext
    {
        foreach ($promotions as $promotion) {
            if (! $this->isGroupRebateType($promotion->type)) {
                continue;
            }

            if ($promotion->eventId !== $selectedEventId) {
                continue;
            }

            return $promotion;
        }

        return null;
    }

    /**
     * @return array{name:string,type:'discount',target:'item',value:string|float|int,order:int,attributes:array<string,mixed>}
     */
    private function buildDiscountAdjustment(PromotionContext $promotion, float $productPrice): array
    {
        return [
            'name'       => $promotion->name ?? '',
            'type'       => 'discount',
            'target'     => 'item',
            'value'      => $this->resolveDiscountValue($promotion, $productPrice),
            'order'      => $this->resolveTypeOrder($promotion->type),
            'attributes' => array_merge([
                'event_id'    => $promotion->eventId,
                'event_title' => $promotion->name,
                'sort'        => $promotion->sort,
            ], $promotion->attributes),
        ];
    }

    /**
     * @return array{name:string,type:'discount',target:'item',value:string|float|int,order:int,attributes:array<string,mixed>}
     */
    private function buildGroupRebateDiscount(PromotionContext $promotion): array
    {
        return [
            'name'       => $promotion->name ?? '',
            'type'       => 'discount',
            'target'     => 'item',
            'value'      => $this->resolveDiscountValue($promotion, 0),
            'order'      => $this->resolveTypeOrder($promotion->type),
            'attributes' => array_merge([
                'event_id'              => $promotion->eventId,
                'event_title'           => $promotion->name,
                'rebate_trigger_amount' => $promotion->rebateTriggerAmount,
                'rebate_get_amount'     => $promotion->rebateGetAmount,
                'type'                  => $promotion->type,
                'sort'                  => $promotion->sort,
            ], $promotion->attributes),
        ];
    }

    /**
     * @return array{name:string,type:'gift',target:'item',value:int,order:int,attributes:array<string,mixed>}|null
     */
    private function buildGiftAdjustment(PromotionContext $promotion): ?array
    {
        $giftCode = $promotion->giftProductCode;

        if (! is_string($giftCode) || $giftCode === '') {
            return null;
        }

        $giftId = $this->resolveGiftResolver()->resolveIdByCode($giftCode);

        if ($giftId === null) {
            return null;
        }

        return [
            'name'       => $promotion->name ?? '',
            'type'       => 'gift',
            'target'     => 'item',
            'value'      => 0,
            'order'      => $this->resolveTypeOrder($promotion->type),
            'attributes' => array_merge([
                'event_id'              => $promotion->eventId,
                'event_title'           => $promotion->name,
                'gift_trigger_amount'   => $promotion->giftTriggerAmount,
                'gift_trigger_quantity' => $promotion->giftTriggerQuantity,
                'gift_id'               => $giftId,
                'gift_prod_no'          => $giftCode,
                'repeatable'            => $promotion->repeatable,
                'sort'                  => $promotion->sort,
            ], $promotion->attributes),
        ];
    }

    /**
     * @return array{name:string,type:'rebate',target:'item',value:int,order:int,attributes:array<string,mixed>}
     */
    private function buildRebateAdjustment(PromotionContext $promotion): array
    {
        return [
            'name'       => $promotion->name ?? '',
            'type'       => 'rebate',
            'target'     => 'item',
            'value'      => 0,
            'order'      => $this->resolveTypeOrder($promotion->type),
            'attributes' => array_merge([
                'event_id'              => $promotion->eventId,
                'event_title'           => $promotion->name,
                'rebate_trigger_amount' => $promotion->rebateTriggerAmount,
                'rebate_get_amount'     => $promotion->rebateGetAmount,
                'repeatable'            => $promotion->repeatable,
                'type'                  => $promotion->type,
                'sort'                  => $promotion->sort,
            ], $promotion->attributes),
        ];
    }

    private function resolveDiscountValue(PromotionContext $promotion, float $productPrice): string
    {
        if ($this->isGroupRebateType($promotion->type)) {
            $rebate = (float) ($promotion->rebateGetAmount ?? 0);

            return '-' . (100 - (int) round($rebate * 100)) . '%';
        }

        if ($this->isFixedPriceType($promotion->type)) {
            $targetPrice = (int) ($promotion->discountAmount ?? 0);

            return '-' . max(0, (int) $productPrice - $targetPrice);
        }

        return $this->formatDiscountAmount((float) ($promotion->discountAmount ?? 0));
    }

    private function formatDiscountAmount(float $amount): string
    {
        if ($amount > 0 && $amount < 1) {
            return '-' . (100 - (int) round($amount * 100)) . '%';
        }

        return '-' . (int) $amount;
    }

    private function resolveTypeOrder(int $type): int
    {
        $typeOrderMap = DiscountConfig::get('event.priorities.type_order', []);

        if (! is_array($typeOrderMap)) {
            return $type;
        }

        if (array_key_exists($type, $typeOrderMap)) {
            return (int) $typeOrderMap[$type];
        }

        $stringType = (string) $type;

        if (array_key_exists($stringType, $typeOrderMap)) {
            return (int) $typeOrderMap[$stringType];
        }

        return $type;
    }

    private function isDiscountType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('discount_types'), true);
    }

    private function isFixedPriceType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('fixed_price_types'), true);
    }

    private function isGroupRebateType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('group_rebate_types'), true);
    }

    private function isGiftType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('gift_types'), true);
    }

    private function isRebateType(int $type): bool
    {
        return in_array($type, $this->resolveTypeSet('rebate_types'), true);
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

    private function resolveGiftResolver(): GiftResolverInterface
    {
        if ($this->giftResolver instanceof GiftResolverInterface) {
            return $this->giftResolver;
        }

        $resolverClass = DiscountConfig::get('cart.gift_resolver');

        if (is_string($resolverClass)
            && $resolverClass !== ''
            && class_exists($resolverClass)
            && is_a($resolverClass, GiftResolverInterface::class, true)
        ) {
            if (function_exists('app')) {
                $resolver = app($resolverClass);

                if ($resolver instanceof GiftResolverInterface) {
                    $this->giftResolver = $resolver;

                    return $this->giftResolver;
                }
            }

            $resolver = new $resolverClass();
            $this->giftResolver = $resolver;

            return $this->giftResolver;
        }

        $this->giftResolver = new NullGiftResolver();

        return $this->giftResolver;
    }
}
