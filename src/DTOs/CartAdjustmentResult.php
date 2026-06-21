<?php

declare(strict_types=1);

namespace Lalalili\Discount\DTOs;

final class CartAdjustmentResult
{
    /**
     * @param list<array{
     *   name:string,
     *   type:'discount'|'rebate'|'gift',
     *   target:'item'|'total',
     *   value:string|float|int,
     *   order:int,
     *   attributes:array<string, mixed>
     * }> $adjustments
     */
    public function __construct(
        public readonly array $adjustments,
    ) {
    }
}
