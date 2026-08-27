<?php

declare(strict_types=1);

namespace hkyss\Tune\Schema;

use Illuminate\Database\Connection;

class SchemaReader
{
    /** @var array<string, list<Index>>|null */
    private ?array $indexes = null;

    /** @var array<string, array<string, string>>|null */
    private ?array $columns = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function prefix(): string
    {
        return (string) $this->connection->getTablePrefix();
    }

    public function qualify(string $table): string
    {
        return $this->prefix() . $table;
    }

    public function platform(): Platform
    {
        $version = $this->connection->selectOne('SELECT VERSION() AS `server_version`');

        return new Platform(
            (string) $this->connection->getDriverName(),
            is_object($version) ? (string) ($version->server_version ?? '') : ''
        );
    }

    public function hasTable(string $table): bool
    {
        return array_key_exists($table, $this->all());
    }

    /** @return list<Index> */
    public function indexesOf(string $table): array
    {
        return $this->all()[$table] ?? [];
    }

    public function indexNamed(string $table, string $name): ?Index
    {
        foreach ($this->indexesOf($table) as $index) {
            if (strcasecmp($index->name, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<string> $columns */
    public function uniqueOver(string $table, array $columns): ?Index
    {
        foreach ($this->indexesOf($table) as $index) {
            if ($index->unique && $index->columns === $columns) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<string> $columns */
    public function coveringIndex(string $table, array $columns): ?Index
    {
        $wanted = new Index('', $columns, false);

        foreach ($this->indexesOf($table) as $index) {
            if ($index->type === Index::BTREE && $wanted->isPrefixOf($index)) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<string> $columns */
    public function duplicateGroups(string $table, array $columns): int
    {
        $quoted = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));

        $row = $this->connection->selectOne(sprintf(
            'SELECT COUNT(*) AS `duplicate_groups` FROM (SELECT 1 FROM `%s` GROUP BY %s HAVING COUNT(*) > 1) AS `d`',
            $this->qualify($table),
            $quoted
        ));

        return is_object($row) ? (int) ($row->duplicate_groups ?? 0) : 0;
    }

    public function rowCount(string $table): int
    {
        $row = $this->connection->selectOne(sprintf('SELECT COUNT(*) AS `rows_total` FROM `%s`', $this->qualify($table)));

        return is_object($row) ? (int) ($row->rows_total ?? 0) : 0;
    }

    public function columnType(string $table, string $column): ?string
    {
        return $this->allColumns()[$table][$column] ?? null;
    }

    /** @return array<string, array<string, string>> */
    public function allColumns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $prefix = $this->prefix();
        $this->columns = [];

        $rows = $this->connection->select(
            'SELECT TABLE_NAME AS `table_name`, COLUMN_NAME AS `column_name`, COLUMN_TYPE AS `column_type`'
            . ' FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
        );

        foreach ($rows as $row) {
            $table = (string) $row->table_name;

            if ($prefix !== '' && !str_starts_with($table, $prefix)) {
                continue;
            }

            $key = $prefix !== '' ? substr($table, strlen($prefix)) : $table;
            $this->columns[$key][(string) $row->column_name] = (string) $row->column_type;
        }

        return $this->columns;
    }

    public function prevailingCollation(): ?string
    {
        $row = $this->connection->selectOne(
            'SELECT TABLE_COLLATION AS `found`, COUNT(*) AS `tables` FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_COLLATION IS NOT NULL'
            . ' GROUP BY TABLE_COLLATION ORDER BY `tables` DESC, `found` ASC LIMIT 1'
        );

        return is_object($row) && is_string($row->found ?? null) ? $row->found : null;
    }

    public function collationOf(string $table): ?string
    {
        $row = $this->connection->selectOne(
            'SELECT TABLE_COLLATION AS `found` FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$this->qualify($table)]
        );

        return is_object($row) && is_string($row->found ?? null) ? $row->found : null;
    }

    public function forget(): void
    {
        $this->indexes = null;
        $this->columns = null;
    }

    /** @return array<string, list<Index>> */
    public function all(): array
    {
        if ($this->indexes !== null) {
            return $this->indexes;
        }

        $prefix = $this->prefix();
        $rows = $this->connection->select(
            'SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name, COLUMN_NAME AS column_name,'
            . ' SEQ_IN_INDEX AS seq, SUB_PART AS sub_part, NON_UNIQUE AS non_unique, INDEX_TYPE AS index_type'
            . ' FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()'
            . ' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
        );

        $grouped = [];

        foreach ($rows as $row) {
            $table = (string) $row->table_name;

            if ($prefix !== '' && !str_starts_with($table, $prefix)) {
                continue;
            }

            $key = $prefix !== '' ? substr($table, strlen($prefix)) : $table;
            $name = (string) $row->index_name;
            $column = (string) $row->column_name;
            $part = $row->sub_part === null ? null : (int) $row->sub_part;

            $grouped[$key][$name]['columns'][] = $part === null ? $column : $column . '(' . $part . ')';
            $grouped[$key][$name]['unique'] = ((int) $row->non_unique) === 0;
            $grouped[$key][$name]['type'] = (string) $row->index_type;
        }

        $this->indexes = [];

        foreach ($grouped as $table => $definitions) {
            foreach ($definitions as $name => $definition) {
                $this->indexes[$table][] = new Index(
                    (string) $name,
                    $definition['columns'],
                    (bool) $definition['unique'],
                    (string) $definition['type']
                );
            }
        }

        return $this->indexes;
    }
}
