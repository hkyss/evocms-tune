<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

use hkyss\Tune\Schema\SchemaReader;

final class Guard
{
    public function __construct(
        public readonly string $table,
        public readonly string $index,
        public readonly bool $present,
    ) {
    }

    public function holds(SchemaReader $reader): bool
    {
        return ($reader->indexNamed($this->table, $this->index) !== null) === $this->present;
    }

    public function explain(): string
    {
        return sprintf('%s is %s', $this->index, $this->present ? 'no longer there' : 'already back');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['table' => $this->table, 'index' => $this->index, 'present' => $this->present];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['table'], $data['index'])) {
            return null;
        }

        return new self((string) $data['table'], (string) $data['index'], (bool) ($data['present'] ?? true));
    }
}
