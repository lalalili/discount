<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Lalalili\Discount\Contexts\CodeContext;
use Lalalili\Discount\Engines\DefaultCouponCodeGenerator;

it('generates code by template and prefix configuration', function (): void {
    $generator = new DefaultCouponCodeGenerator();

    $code = $generator->generate(new CodeContext(
        typeValue: 13,
        userId: 52,
        count: 1,
        now: '2026-03-01 08:00:00',
        existsChecker: static fn (string $generated): bool => false,
    ));

    expect($code)->toMatch('/^FC[A-F0-9]{11}[A-Z]{2}$/');
});

it('supports custom template tokens and fallback user coordinate', function (): void {
    setDiscountConfig([
        'coupon' => [
            'code' => [
                'prefixes' => [
                    99 => 'ZZ',
                ],
                'templates' => [
                    99 => '{prefix}{yy}{user_coord_or:xy}{count_alpha}{count}',
                ],
            ],
        ],
    ]);

    $generator = new DefaultCouponCodeGenerator();

    $code = $generator->generate(new CodeContext(
        typeValue: 99,
        userId: null,
        count: 3,
        now: '2026-03-01 08:00:00',
        existsChecker: static fn (string $generated): bool => false,
    ));

    expect($code)->toBe('ZZ26XYD3');
});

it('retries on duplicate code until available', function (): void {
    setDiscountConfig([
        'coupon' => [
            'code' => [
                'prefixes' => [
                    88 => 'RT',
                ],
                'templates' => [
                    88 => '{prefix}{count}',
                ],
            ],
        ],
    ]);

    $attempt = 0;
    $generator = new DefaultCouponCodeGenerator();

    $code = $generator->generate(new CodeContext(
        typeValue: 88,
        count: 9,
        maxAttempts: 3,
        existsChecker: static function (string $generated) use (&$attempt): bool {
            $attempt++;

            return $attempt === 1;
        },
    ));

    expect($code)->toBe('RT9')
        ->and($attempt)->toBe(3);
});

it('throws when all generated codes collide', function (): void {
    setDiscountConfig([
        'coupon' => [
            'code' => [
                'prefixes' => [
                    77 => 'COL',
                ],
                'templates' => [
                    77 => '{prefix}{count}',
                ],
            ],
        ],
    ]);

    $generator = new DefaultCouponCodeGenerator();

    $action = static fn () => $generator->generate(new CodeContext(
        typeValue: 77,
        count: 1,
        maxAttempts: 2,
        existsChecker: static fn (string $generated): bool => true,
    ));

    expect($action)->toThrow(\RuntimeException::class);
});
