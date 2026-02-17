<?php

declare(strict_types=1);

namespace Discount\Kernel\Enums;

enum CouponKind: string
{
    case Member = 'member';
    case Promotion = 'promotion';
}
