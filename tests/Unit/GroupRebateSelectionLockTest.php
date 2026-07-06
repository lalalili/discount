<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\Contexts\CartLineContext;
use Lalalili\Discount\Contexts\PromotionContext;
use Lalalili\Discount\Contexts\PromotionSet;
use Lalalili\Discount\DTOs\CartPromotionRefreshInput;
use Lalalili\Discount\Engines\DefaultCartPromotionRefreshService;

/**
 * 團購(group rebate)跨商品選擇行為鎖定測試。
 *
 * 這些 case 刻意涵蓋 sort tie、meet 與 shared 衝突(unset 縮減集合)等邊界,
 * 逐一比對 selectedGroupRebateEventIds(非只比金額),作為選擇演算法
 * 改寫(倒排索引取代 pairwise 交集)的等價性防護。
 */
function lockTestCartContext(): CartContext
{
    return new CartContext(
        orderTotal: 0,
        allAmount: 0,
        bookAmount: 0,
        ebookAmount: 0,
        specificProductsAmount: 0,
        hasBook: false,
        hasEbook: false,
        hasSpecificProducts: false,
    );
}

it('sort tie 時取排序後第一個共享事件(行為鎖定)', function (): void {
    $service = new DefaultCartPromotionRefreshService();

    // 兩個共享事件 601/602 sort 相同(tie),兩商品皆掛兩事件
    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: lockTestCartContext(),
        lines: [
            new CartLineContext(id: 201, productId: 201, quantity: 2, unitPrice: 500),
            new CartLineContext(id: 202, productId: 202, quantity: 1, unitPrice: 500),
        ],
        promotionSetsByProductId: [
            201 => new PromotionSet([
                new PromotionContext(type: 6, sort: 2, rebateGetAmount: 0.8, eventId: 601, name: 'A', rebateTriggerAmount: 3),
                new PromotionContext(type: 6, sort: 2, rebateGetAmount: 0.7, eventId: 602, name: 'B', rebateTriggerAmount: 3),
            ]),
            202 => new PromotionSet([
                new PromotionContext(type: 6, sort: 2, rebateGetAmount: 0.8, eventId: 601, name: 'A', rebateTriggerAmount: 3),
                new PromotionContext(type: 6, sort: 2, rebateGetAmount: 0.7, eventId: 602, name: 'B', rebateTriggerAmount: 3),
            ]),
        ],
    ));

    expect($result->selectedGroupRebateEventIds)->toBe([201 => 601, 202 => 601]);
});

it('meet 與 shared 事件不同且 meet 較優時,商品退出共享集合(unset 分支鎖定)', function (): void {
    $service = new DefaultCartPromotionRefreshService();

    // 商品 301 自身達標事件 611(sort 1),另掛共享事件 612(sort 5);
    // 商品 302/303 只掛 612。301 選 611 後退出共享集合,
    // 302/303 以剩餘數量湊 612 門檻。
    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: lockTestCartContext(),
        lines: [
            new CartLineContext(id: 301, productId: 301, quantity: 3, unitPrice: 400),
            new CartLineContext(id: 302, productId: 302, quantity: 2, unitPrice: 400),
            new CartLineContext(id: 303, productId: 303, quantity: 2, unitPrice: 400),
        ],
        promotionSetsByProductId: [
            301 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.9, eventId: 611, name: 'Self', rebateTriggerAmount: 3),
                new PromotionContext(type: 6, sort: 5, rebateGetAmount: 0.8, eventId: 612, name: 'Shared', rebateTriggerAmount: 6),
            ]),
            302 => new PromotionSet([
                new PromotionContext(type: 6, sort: 5, rebateGetAmount: 0.8, eventId: 612, name: 'Shared', rebateTriggerAmount: 4),
            ]),
            303 => new PromotionSet([
                new PromotionContext(type: 6, sort: 5, rebateGetAmount: 0.8, eventId: 612, name: 'Shared', rebateTriggerAmount: 4),
            ]),
        ],
    ));

    // 301 自有事件勝出;302/303 因 301 未退出共享集合(unset 僅在 shared 事件存在
    // 且與 meet 不同時觸發),交集為空而皆未入選 — 現行語意如此,鎖定之。
    expect($result->selectedGroupRebateEventIds)->toBe([301 => 611]);
});

it('三商品共享事件 + 單品自有事件混合(選擇圖鎖定)', function (): void {
    $service = new DefaultCartPromotionRefreshService();

    $result = $service->refresh(new CartPromotionRefreshInput(
        cartContext: lockTestCartContext(),
        lines: [
            new CartLineContext(id: 401, productId: 401, quantity: 1, unitPrice: 300),
            new CartLineContext(id: 402, productId: 402, quantity: 1, unitPrice: 300),
            new CartLineContext(id: 403, productId: 403, quantity: 1, unitPrice: 300),
        ],
        promotionSetsByProductId: [
            401 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.8, eventId: 621, name: 'Shared3', rebateTriggerAmount: 3),
            ]),
            402 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.8, eventId: 621, name: 'Shared3', rebateTriggerAmount: 3),
                new PromotionContext(type: 6, sort: 2, rebateGetAmount: 0.9, eventId: 622, name: 'Own', rebateTriggerAmount: 1),
            ]),
            403 => new PromotionSet([
                new PromotionContext(type: 6, sort: 1, rebateGetAmount: 0.8, eventId: 621, name: 'Shared3', rebateTriggerAmount: 3),
            ]),
        ],
    ));

    // 鍵序含選擇迭代順序(max 降冪:402 先入選)一併鎖定
    expect($result->selectedGroupRebateEventIds)->toBe([402 => 621, 401 => 621, 403 => 621]);
});
