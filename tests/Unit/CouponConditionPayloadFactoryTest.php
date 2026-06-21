<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\DTOs\PricingTraceEntry;
use Lalalili\Discount\Enums\CouponKind;
use Lalalili\Discount\Support\CouponConditionPayloadFactory;

it('builds stable member coupon condition payloads for app adapters', function (): void {
    $entry = new PricingTraceEntry(
        stage: 'coupon_apply',
        source: 'coupon',
        status: 'applied',
        scope: '0',
        kind: 'member',
        code: 'MEM-100',
        amount: 100,
        finalTotal: 900.0,
        metadata: ['coupon_kind' => 'member'],
    );

    $payload = (new CouponConditionPayloadFactory())->make(CouponKind::Member, 100, $entry);

    expect($payload->toArray(['name' => '會員優惠券']))->toMatchArray([
        'name'   => '會員優惠券',
        'type'   => 'member_coupon',
        'target' => 'total',
        'value'  => -100,
        'order'  => 10,
    ])->and(data_get($payload->attributes, 'pricing_trace_entry.stage'))->toBe('coupon_apply')
        ->and(data_get($payload->attributes, 'pricing_trace_entry.metadata.coupon_kind'))->toBe('member');
});

it('builds stable promotion coupon condition payloads for app adapters', function (): void {
    $entry = new PricingTraceEntry(
        stage: 'coupon_apply',
        source: 'coupon',
        status: 'applied',
        scope: '0',
        kind: 'promotion',
        code: 'PROMO-10',
        amount: 50.0,
        finalTotal: 450.0,
        metadata: ['coupon_kind' => 'promotion'],
    );

    $factory = new CouponConditionPayloadFactory();
    $payload = $factory->make(CouponKind::Promotion, 50.0, $entry);

    expect($factory->typeFor(CouponKind::Promotion))->toBe('promotion_coupon')
        ->and($factory->orderFor(CouponKind::Promotion))->toBe(11)
        ->and($payload->toArray())->toMatchArray([
            'type'   => 'promotion_coupon',
            'target' => 'total',
            'value'  => -50.0,
            'order'  => 11,
        ])
        ->and(data_get($payload->attributes, 'pricing_trace_entry.code'))->toBe('PROMO-10');
});
