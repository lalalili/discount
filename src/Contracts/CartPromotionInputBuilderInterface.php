<?php

declare(strict_types=1);

namespace Discount\Kernel\Contracts;

use Discount\Kernel\Contexts\CartContext;
use Discount\Kernel\DTOs\CartPromotionRefreshInput;

interface CartPromotionInputBuilderInterface
{
    /**
     * @return list<\Discount\Kernel\Contexts\CartLineContext>
     */
    public function lines(): array;

    /**
     * @return array<int|string, \Discount\Kernel\Contexts\PromotionSet>
     */
    public function promotionSetsByProductId(): array;

    public function cartContext(): CartContext;

    public function giftFulfillment(): string;

    public function build(): CartPromotionRefreshInput;
}
