<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\DTOs\CartPromotionRefreshResult;
use Discount\Kernel\DTOs\PricingTrace;
use Discount\Kernel\DTOs\PricingTraceEntry;
use Discount\Kernel\DTOs\PromotionDecision;
use Discount\Kernel\Support\PricingTraceFormatter;

it('keeps pricing trace entry output stable', function (): void {
    $entry = new PricingTraceEntry(
        stage: 'coupon_apply',
        source: 'coupon',
        status: 'applied',
        scope: '0',
        kind: 'member',
        code: 'MEM-TRACE',
        id: 123,
        amount: 50.0,
        finalTotal: 450.0,
        reasonCode: null,
        reason: null,
        metadata: ['coupon_kind' => 'member'],
    );

    expect(array_keys($entry->toArray()))->toBe([
        'stage',
        'source',
        'status',
        'scope',
        'kind',
        'code',
        'id',
        'amount',
        'final_total',
        'reason_code',
        'reason',
        'metadata',
    ]);
});

it('normalizes promotion decisions into pricing trace', function (): void {
    $result = new CartPromotionRefreshResult(
        itemAdjustmentsByLineId: [],
        cartAdjustments: [],
        selectedGroupRebateEventIds: [],
        promotionDecisions: [
            new PromotionDecision(
                status: 'applied',
                scope: 'item',
                lineId: '10',
                productId: '10',
                eventId: 77,
                type: 1,
                name: 'Item discount',
                target: 'item',
                adjustmentType: 'discount',
                value: '-100',
            ),
            [
                'status'   => 'skipped',
                'scope'    => 'cart',
                'event_id' => 88,
                'type'     => 6,
                'name'     => 'Group rebate',
                'reason'   => 'quantity_not_met',
            ],
        ],
    );

    $trace = $result->pricingTrace->toArray();

    expect($trace)->toHaveCount(2)
        ->and($trace[0]['stage'])->toBe('promotion_refresh')
        ->and($trace[0]['source'])->toBe('promotion')
        ->and($trace[0]['status'])->toBe('applied')
        ->and($trace[0]['scope'])->toBe('item')
        ->and($trace[0]['kind'])->toBe('discount')
        ->and($trace[0]['id'])->toBe(77)
        ->and($trace[0]['amount'])->toBe('-100')
        ->and($trace[1]['status'])->toBe('skipped')
        ->and($trace[1]['reason_code'])->toBe('quantity_not_met');
});

it('accepts explicit pricing trace on refresh result', function (): void {
    $trace = new PricingTrace([
        new PricingTraceEntry(
            stage: 'promotion_refresh',
            source: 'promotion',
            status: 'applied',
            scope: 'cart',
            kind: 'rebate',
        ),
    ]);

    $result = new CartPromotionRefreshResult(
        itemAdjustmentsByLineId: [],
        cartAdjustments: [],
        selectedGroupRebateEventIds: [],
        pricingTrace: $trace,
    );

    expect($result->pricingTrace)->toBe($trace);
});

it('merges pricing trace entries by identity and keeps latest entries bounded', function (): void {
    $existing = [
        [
            'stage'       => 'coupon_validate',
            'source'      => 'coupon',
            'status'      => 'skipped',
            'scope'       => '0',
            'kind'        => 'member',
            'code'        => 'MEM-TRACE',
            'id'          => 123,
            'amount'      => null,
            'final_total' => null,
            'reason_code' => 'ELIGIBILITY_FAILED',
            'reason'      => 'not eligible',
            'metadata'    => [],
        ],
    ];

    $incoming = [
        [
            'stage'       => 'coupon_validate',
            'source'      => 'coupon',
            'status'      => 'applied',
            'scope'       => '0',
            'kind'        => 'member',
            'code'        => 'MEM-TRACE',
            'id'          => 123,
            'amount'      => 50,
            'final_total' => 450.0,
            'reason_code' => null,
            'reason'      => null,
            'metadata'    => [],
        ],
        [
            'stage'       => 'coupon_apply',
            'source'      => 'coupon',
            'status'      => 'applied',
            'scope'       => '0',
            'kind'        => 'member',
            'code'        => 'MEM-TRACE',
            'id'          => 123,
            'amount'      => 50,
            'final_total' => 450.0,
            'reason_code' => null,
            'reason'      => null,
            'metadata'    => [],
        ],
    ];

    $merged = PricingTraceFormatter::mergeLatestByIdentity($existing, $incoming, 2);

    expect($merged)->toHaveCount(2)
        ->and($merged[0]['stage'])->toBe('coupon_validate')
        ->and($merged[0]['status'])->toBe('applied')
        ->and($merged[1]['stage'])->toBe('coupon_apply');
});

it('summarizes pricing trace entries for compact observability', function (): void {
    $summary = PricingTraceFormatter::summarize([
        [
            'stage'       => 'promotion_refresh',
            'source'      => 'promotion',
            'status'      => 'applied',
            'scope'       => 'item',
            'kind'        => 'discount',
            'reason_code' => null,
        ],
        [
            'stage'       => 'promotion_refresh',
            'source'      => 'promotion',
            'status'      => 'skipped',
            'scope'       => 'cart',
            'kind'        => 'gift',
            'reason_code' => 'gift_out_of_stock',
        ],
    ]);

    expect($summary['total'])->toBe(2)
        ->and($summary['by_stage'])->toBe(['promotion_refresh' => 2])
        ->and($summary['by_source'])->toBe(['promotion' => 2])
        ->and($summary['by_status'])->toBe(['applied' => 1, 'skipped' => 1])
        ->and($summary['reason_codes'])->toBe(['gift_out_of_stock' => 1]);
});
