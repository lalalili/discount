<?php

declare(strict_types=1);

namespace Lalalili\Discount\Contracts;

use Lalalili\Discount\Contexts\CartContext;
use Lalalili\Discount\DTOs\CartPromotionRefreshInput;

interface CartPromotionInputBuilderInterface
{
    /**
     * @return list<\Lalalili\Discount\Contexts\CartLineContext>
     */
    public function lines(): array;

    /**
     * @return array<int|string, \Lalalili\Discount\Contexts\PromotionSet>
     */
    public function promotionSetsByProductId(): array;

    public function cartContext(): CartContext;

    public function giftFulfillment(): string;

    public function build(): CartPromotionRefreshInput;
}
