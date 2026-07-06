<?php

declare(strict_types=1);

namespace Lalalili\Discount\Enums;

enum CouponKind: string
{
    case Member = 'member';
    case Promotion = 'promotion';

    /**
     * 免運門檻券:全額折抵當筆運費(host 經 CartContext meta.shipping_fee 傳入),
     * 驗證規則比照 Promotion(登入、每人一次、發行量庫存),可與 Member/Promotion 並用。
     */
    case FreeShipping = 'free_shipping';
}
