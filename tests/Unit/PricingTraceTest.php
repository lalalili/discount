<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Discount\Kernel\DTOs\PricingTraceEntry;
use Discount\Kernel\Support\PricingTraceFormatter;

it('normalizes single and list pricing trace entries', function (): void {
    $entry = new PricingTraceEntry(
        stage: 'coupon_apply',
        source: 'coupon',
        status: 'applied',
        scope: '0',
        kind: 'member',
        code: 'MEM-TRACE',
    );

    expect(PricingTraceFormatter::normalize($entry))->toHaveCount(1)
        ->and(PricingTraceFormatter::normalize($entry->toArray()))->toHaveCount(1)
        ->and(PricingTraceFormatter::normalize([$entry->toArray()]))->toHaveCount(1)
        ->and(PricingTraceFormatter::normalize([['ignored'], $entry->toArray()]))->toHaveCount(1);
});

it('merges pricing trace entries by identity and keeps latest entries bounded', function (): void {
    $existing = [
        [
            'stage'       => 'coupon_validate',
            'source'      => 'coupon',
            'status'      => 'skipped',
            'kind'        => 'member',
            'code'        => 'MEM-TRACE',
            'id'          => 123,
            'reason_code' => 'ELIGIBILITY_FAILED',
            'metadata'    => [],
        ],
    ];

    $incoming = [
        [
            'stage'       => 'coupon_validate',
            'source'      => 'coupon',
            'status'      => 'applied',
            'kind'        => 'member',
            'code'        => 'MEM-TRACE',
            'id'          => 123,
            'amount'      => 50,
            'final_total' => 450.0,
            'metadata'    => [],
        ],
        [
            'stage'       => 'coupon_apply',
            'source'      => 'coupon',
            'status'      => 'applied',
            'kind'        => 'member',
            'code'        => 'MEM-TRACE',
            'id'          => 123,
            'amount'      => 50,
            'final_total' => 450.0,
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
            'kind'        => 'discount',
            'reason_code' => null,
        ],
        [
            'stage'       => 'promotion_refresh',
            'source'      => 'promotion',
            'status'      => 'skipped',
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
