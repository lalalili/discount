<?php

declare(strict_types=1);

namespace Lalalili\Discount\Enums;

enum CouponAmountMode: string
{
    case Auto = 'auto';
    case Fixed = 'fixed';
    case Rate = 'rate';
}
