<?php

declare(strict_types=1);

namespace hkyss\Tune\Record;

use hkyss\Tune\Apply\Guard;
use hkyss\Tune\Apply\Statement;
use hkyss\Tune\Rules\Rule;
use Illuminate\Database\Connection;

class Journal
{
    public const TABLE = 'tune_changes';

    private bool $ensured = false;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function table(): string
    {
        return $this->connection->getTablePrefix() . self::TABLE;
    }

    public function exists(): bool
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS `present` FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$this->table()]
        );

        return is_object($row) && ((int) ($row->present ?? 0)) > 0;
    }

    public function ensure(): void
    {
        if ($this->ensured) {
            return;
        }

        $this->connection->statement(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` ('
            . '`id` int unsigned NOT NULL AUTO_INCREMENT,'
            . '`rule_id` varchar(191) NOT NULL,'
            . '`table_name` varchar(191) NOT NULL,'
            . '`applied_sql` text NOT NULL,'
            . '`undo_sql` text NOT NULL,'
            . '`applied_at` int unsigned NOT NULL,'
            . 'PRIMARY KEY (`id`)'
            . ') ENGINE=InnoDB',
            $this->table()
        ));

        $this->ensured = true;
    }

    /**
     * @param list<string>    $applied
     * @param list<Statement> $undo
     */
    public function record(Rule $rule, array $applied, array $undo): void
    {
        $this->ensure();

        $this->connection->table(self::TABLE)->insert([
            'rule_id' => $rule->id,
            'table_name' => $rule->table,
            'applied_sql' => implode(";\n", $applied),
            'undo_sql' => (string) json_encode(array_map(
                static fn (Statement $statement): array => [
                    'sql' => $statement->sql,
                    'online' => $statement->online,
                    'guard' => $statement->guard?->toArray(),
                ],
                $undo
            )),
            'applied_at' => time(),
        ]);
    }

    /**
     * @param  list<string> $only
     * @return list<Change>
     */
    public function entries(array $only = []): array
    {
        if (!$this->exists()) {
            return [];
        }

        $changes = [];

        foreach ($this->connection->table(self::TABLE)->orderByDesc('id')->get() as $row) {
            $change = $this->hydrate((array) $row);

            if ($only === [] || in_array($change->ruleId, $only, true) || in_array($change->table, $only, true)) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    public function count(): int
    {
        return $this->exists() ? (int) $this->connection->table(self::TABLE)->count() : 0;
    }

    public function forget(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function discard(): void
    {
        $this->connection->statement(sprintf('DROP TABLE IF EXISTS `%s`', $this->table()));
        $this->ensured = false;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Change
    {
        $decoded = json_decode((string) ($row['undo_sql'] ?? '[]'), true);
        $undo = [];

        foreach (is_array($decoded) ? $decoded : [] as $statement) {
            if (is_array($statement) && isset($statement['sql'])) {
                $guard = $statement['guard'] ?? null;

                $undo[] = new Statement(
                    (string) $statement['sql'],
                    (bool) ($statement['online'] ?? true),
                    (string) ($row['table_name'] ?? ''),
                    is_array($guard) ? Guard::fromArray($guard) : null
                );
            }
        }

        return new Change(
            (int) ($row['id'] ?? 0),
            (string) ($row['rule_id'] ?? ''),
            (string) ($row['table_name'] ?? ''),
            (string) ($row['applied_sql'] ?? ''),
            $undo,
            (int) ($row['applied_at'] ?? 0)
        );
    }
}
