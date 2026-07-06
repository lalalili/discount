<?php

return [
    'event' => [
        'type_role_map' => [
            1  => 'single_discount',
            3  => 'gift',
            4  => 'cart_rebate',
            6  => 'group_rebate',
            7  => 'exclusive_discount',
            8  => 'exclusive_price',
            20 => 'exclusive_discount',
            21 => 'exclusive_price',
            30 => 'custom',
        ],
        'priorities' => [
            'pricing' => [
                'exclusive_price',
                'exclusive_discount',
                'group_rebate',
                'single_discount',
            ],
            'type_order' => [
                1  => 1,
                3  => 3,
                4  => 4,
                6  => 6,
                7  => 7,
                8  => 8,
                20 => 20,
                21 => 21,
                30 => 99,
            ],
        ],
    ],
    'coupon' => [
        /*
         * scope 邏輯名稱 → host enum 值。注意:邏輯名稱的「商品類別語意」由 host 定義,
         * 跨 host 不可直接搬設定——cptw 的 book(1) 指實體書,aitehub 將 book(1)
         * 重載為 COURSE(課程)。eligibility 引擎只認邏輯名稱(all/book/ebook/
         * specific_products),對應的購物車小計由 host CartContext 提供。
         */
        'scope_map' => [
            'all'               => 0,
            'book'              => 1,
            'ebook'             => 2,
            'specific_products' => 3,
        ],
        'code' => [
            'prefixes' => [
                1         => 'MC',
                2         => 'PC',
                11        => 'RC',
                12        => 'BC',
                13        => 'FC',
                21        => 'LC',
                22        => 'LC',
                'default' => '',
            ],
            'templates' => [
                13        => '{prefix}{random:11}{user_coord_or:AA}',
                12        => '{prefix}{yy}{user_coord_or:AA}{user_id}{count_alpha}{count}',
                'default' => '{prefix}{random:11}{user_coord_or:AA}',
            ],
            'tokens' => [
                '{prefix}',
                '{yy}',
                '{user_id}',
                '{user_coord}',
                '{count}',
                '{count_alpha}',
                '{random:N}',
                '{user_coord_or:AA}',
            ],
        ],
    ],
    'cart' => [
        'roles' => [
            'discount_types'     => [1, 7, 8, 20, 21],
            'fixed_price_types'  => [8, 21],
            'group_rebate_types' => [6],
            'gift_types'         => [3],
            'rebate_types'       => [4],
        ],
        'gift_resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | 折扣順序組合(Ordering)
    |--------------------------------------------------------------------------
    | total 層 cart condition 依 order 升冪套用。layers 宣告各類條件的 slot 區間
    | (供 DiscountConfig::validateOrdering() 守衛與文件化,legacy_allow 為
    | type_order 中歷史上超出 promotion 區間的值);coupon 的 order 由此 config
    | 決定(取代 CouponConditionPayloadFactory 的寫死常數)。
    |
    | rebate.strategy:多個滿額折同時達標時的裁決策略
    |   - first(預設):取排序後第一個(等同既有 host 行為);其餘記入
    |     skippedPromotions(reason=rebate_strategy_dropped),trace 可解釋
    |   - max:取折抵金額最大者
    |   - all:全部套用
    |
    | exclusive.gift_coexists:團購/排他折扣選中時是否仍保留贈品(既有隱規則顯式化)
    */
    'ordering' => [
        'layers' => [
            'promotion' => ['range' => [1, 9], 'legacy_allow' => [20, 21, 99]],
            'coupon'    => ['range' => [10, 19]],
            'shipping'  => ['range' => [20, 29]],
        ],
        'coupon' => [
            'member'    => 10,
            'promotion' => 11,
            // 免運券為 target=subtotal(緊跟 host shipping_fee condition 之後),
            // 與 total 層 coupon 排序空間分離
            'free_shipping' => 2,
        ],
        'rebate' => [
            'strategy' => 'first',
        ],
        'exclusive' => [
            'gift_coexists' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 金額收斂政策(Rounding Policy)
    |--------------------------------------------------------------------------
    | null = 維持既有行為(unit_price 不收斂;coupon Rate 裸 round、Fixed 不收斂)。
    | 規則格式:int(round 精度)或 ['precision' => int, 'mode' => string],
    | mode 支援 'half_up'|'half_down'|'half_even'|'half_odd'|'floor'|'ceil'。
    |
    | 建議整數幣別(TWD)政策:
    |   'unit_price'      => ['precision' => 0, 'mode' => 'floor'],
    |   'coupon_discount' => ['precision' => 0, 'mode' => 'half_up'],
    */
    'rounding' => [
        'unit_price'      => null,
        'coupon_discount' => null,
    ],
];
