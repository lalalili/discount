<?php

declare(strict_types=1);

namespace Lalalili\Discount\Enums;

enum CouponKind: string
{
    case Member = 'member';
    case Promotion = 'promotion';
}
