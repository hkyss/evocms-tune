<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

use Illuminate\Database\Connection;

final class Statistics
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param list<Statement> $statements */
    public function refreshFor(array $statements): int
    {
        $tables = self::tablesOf($statements);

        if ($tables === []) {
            return 0;
        }

        $this->connection->select(self::statement($tables));

        return count($tables);
    }

    /**
     * @param  list<Statement> $statements
     * @return list<string>
     */
    public static function tablesOf(array $statements): array
    {
        $tables = [];

        foreach ($statements as $statement) {
            $tables[$statement->table] = true;
        }

        $names = array_keys($tables);
        sort($names);

        return $names;
    }

    /** @param list<string> $tables */
    public static function statement(array $tables): string
    {
        return 'ANALYZE TABLE ' . implode(', ', array_map(
            static fn (string $table): string => '`' . str_replace('`', '``', $table) . '`',
            $tables
        ));
    }
}
