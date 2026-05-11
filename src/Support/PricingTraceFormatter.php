<?php

declare(strict_types=1);

namespace Discount\Kernel\Support;

use Discount\Kernel\DTOs\PricingTrace;
use Discount\Kernel\DTOs\PricingTraceEntry;

final class PricingTraceFormatter
{
    /**
     * @param PricingTrace|PricingTraceEntry|array<string, mixed>|list<array<string, mixed>>|null $trace
     * @return list<array<string, mixed>>
     */
    public static function normalize(PricingTrace|PricingTraceEntry|array|null $trace): array
    {
        if ($trace instanceof PricingTrace) {
            return $trace->toArray();
        }

        if ($trace instanceof PricingTraceEntry) {
            return [$trace->toArray()];
        }

        if (! is_array($trace)) {
            return [];
        }

        if (array_key_exists('stage', $trace)) {
            $entry = self::stringKeyedEntry($trace);

            return $entry === [] ? [] : [$entry];
        }

        $entries = [];
        foreach ($trace as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalizedEntry = self::stringKeyedEntry($entry);
            if ($normalizedEntry !== []) {
                $entries[] = $normalizedEntry;
            }
        }

        return $entries;
    }

    /**
     * @param PricingTrace|PricingTraceEntry|array<string, mixed>|list<array<string, mixed>>|null $existing
     * @param PricingTrace|PricingTraceEntry|array<string, mixed>|list<array<string, mixed>>|null $incoming
     * @return list<array<string, mixed>>
     */
    public static function mergeLatestByIdentity(
        PricingTrace|PricingTraceEntry|array|null $existing,
        PricingTrace|PricingTraceEntry|array|null $incoming,
        int $maxEntries = 20,
    ): array {
        $mergedTrace = [];

        foreach (array_merge(self::normalize($existing), self::normalize($incoming)) as $entry) {
            $traceKey = self::identityKey($entry);
            unset($mergedTrace[$traceKey]);
            $mergedTrace[$traceKey] = $entry;
        }

        return array_values(array_slice($mergedTrace, -max(1, $maxEntries), null, true));
    }

    /**
     * @param PricingTrace|PricingTraceEntry|array<string, mixed>|list<array<string, mixed>>|null $trace
     * @return array{
     *   total:int,
     *   by_stage:array<string, int>,
     *   by_source:array<string, int>,
     *   by_status:array<string, int>,
     *   reason_codes:array<string, int>
     * }
     */
    public static function summarize(PricingTrace|PricingTraceEntry|array|null $trace): array
    {
        $summary = [
            'total'        => 0,
            'by_stage'     => [],
            'by_source'    => [],
            'by_status'    => [],
            'reason_codes' => [],
        ];

        foreach (self::normalize($trace) as $entry) {
            $summary['total']++;
            self::increment($summary['by_stage'], self::stringValue($entry['stage'] ?? null));
            self::increment($summary['by_source'], self::stringValue($entry['source'] ?? null));
            self::increment($summary['by_status'], self::stringValue($entry['status'] ?? null));
            self::increment($summary['reason_codes'], self::stringValue($entry['reason_code'] ?? null));
        }

        return $summary;
    }

    /**
     * @param array<int|string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function stringKeyedEntry(array $entry): array
    {
        $normalized = [];

        foreach ($entry as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function identityKey(array $entry): string
    {
        $identifier = $entry['id'] ?? null;

        if ($identifier === null || $identifier === '') {
            $identifier = $entry['code'] ?? '';
        }

        return implode('|', [
            self::stringValue($entry['stage'] ?? null),
            self::stringValue($entry['source'] ?? null),
            self::stringValue($entry['kind'] ?? null),
            self::stringValue($identifier),
        ]);
    }

    /**
     * @param array<string, int> $counts
     */
    private static function increment(array &$counts, string $key): void
    {
        if ($key === '') {
            return;
        }

        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    private static function stringValue(mixed $value): string
    {
        return is_int($value) || is_float($value) || is_string($value) ? (string) $value : '';
    }
}
