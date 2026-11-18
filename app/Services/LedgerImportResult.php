<?php

namespace App\Services;

/**
 * Stable result contract shared by legacy redirects and the JSON API.
 */
final readonly class LedgerImportResult
{
    /** @param array<string, int> $warnings */
    public function __construct(
        public int $imported,
        public int $skipped,
        public int $duplicates,
        public array $warnings = [],
    ) {}

    /** @return array{imported:int, skipped:int, duplicates:int, warnings:array<string,int>, detail_available:bool} */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'duplicates' => $this->duplicates,
            'warnings' => $this->warnings,
            'detail_available' => $this->warnings !== [] || $this->skipped > 0 || $this->duplicates > 0,
        ];
    }

    public static function fromSession(mixed $value): ?self
    {
        if (! is_array($value) || ! isset($value['imported'], $value['skipped'])) {
            return null;
        }

        return new self(
            (int) $value['imported'],
            (int) $value['skipped'],
            (int) ($value['duplicates'] ?? 0),
            is_array($value['warnings'] ?? null) ? array_map('intval', $value['warnings']) : [],
        );
    }
}
