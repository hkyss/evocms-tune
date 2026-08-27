<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit\Fake;

use hkyss\Tune\Schema\Index;
use hkyss\Tune\Schema\Platform;
use hkyss\Tune\Schema\SchemaReader;

final class SchemaFake extends SchemaReader
{
    /**
     * @param array<string, list<Index>>            $tables
     * @param array<string, int>                    $duplicates
     * @param array<string, array<string, string>>  $columns
     */
    public function __construct(
        private readonly array $tables,
        private readonly array $duplicates = [],
        private readonly string $prefix = 'evo_',
        private readonly array $columns = [],
    ) {
    }

    /** @return array<string, array<string, string>> */
    public function allColumns(): array
    {
        return $this->columns;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function platform(): Platform
    {
        return new Platform('mysql', '8.0.33');
    }

    /** @return array<string, list<Index>> */
    public function all(): array
    {
        return $this->tables;
    }

    /** @param list<string> $columns */
    public function duplicateGroups(string $table, array $columns): int
    {
        return $this->duplicates[$table] ?? 0;
    }

    public function rowCount(string $table): int
    {
        return 0;
    }

    public function forget(): void
    {
    }
}
