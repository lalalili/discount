<?php

declare(strict_types=1);

namespace Discount\Kernel\DTOs;

final class PricingTrace
{
    /**
     * @var list<PricingTraceEntry>
     */
    public readonly array $entries;

    /**
     * @param list<PricingTraceEntry|array<string, mixed>> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = array_map(
            static fn (PricingTraceEntry|array $entry): PricingTraceEntry => $entry instanceof PricingTraceEntry
                ? $entry
                : PricingTraceEntry::fromArray($entry),
            $entries,
        );
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function fromEntry(PricingTraceEntry $entry): self
    {
        return new self([$entry]);
    }

    /**
     * @param list<PromotionDecision|array<string, mixed>> $decisions
     */
    public static function fromPromotionDecisions(array $decisions): self
    {
        return new self(array_map(
            static fn (PromotionDecision|array $decision): PricingTraceEntry => PricingTraceEntry::fromPromotionDecision($decision),
            $decisions,
        ));
    }

    public function withEntry(PricingTraceEntry $entry): self
    {
        return new self([...$this->entries, $entry]);
    }

    public function merge(self $trace): self
    {
        return new self([...$this->entries, ...$trace->entries]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (PricingTraceEntry $entry): array => $entry->toArray(),
            $this->entries,
        );
    }
}
