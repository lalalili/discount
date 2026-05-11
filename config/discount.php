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
];
