<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

use hkyss\Tune\Rules\Action;
use hkyss\Tune\Rules\Rule;

final class StatementBuilder
{
    public function __construct(
        private readonly string $prefix = '',
        private readonly bool $supportsOnlineChanges = true,
    ) {
    }

    /** @return list<Statement> */
    public function forRule(Rule $rule): array
    {
        $table = $this->prefix . $rule->table;
        $online = $this->supportsOnlineChanges && !$rule->rebuild;
        $clauses = [];

        if ($rule->action === Action::AddUnique && $rule->replaces !== null) {
            $clauses[] = sprintf('DROP INDEX %s', $this->quote($rule->replaces));
        }

        $clauses[] = match ($rule->action) {
            Action::AddIndex => sprintf('ADD INDEX %s (%s)', $this->quote($rule->index), $this->columnList($rule->columns)),
            Action::AddUnique => sprintf('ADD UNIQUE INDEX %s (%s)', $this->quote($rule->index), $this->columnList($rule->columns)),
            Action::DropIndex => sprintf('DROP INDEX %s', $this->quote($rule->index)),
        };

        $sql = sprintf('ALTER TABLE %s %s', $this->quote($table), implode(', ', $clauses));

        if ($online) {
            $sql .= ', ALGORITHM=INPLACE, LOCK=NONE';
        }

        return [new Statement($sql, $online, $table)];
    }

    /** @return list<Statement> */
    public function reverseOf(Rule $rule): array
    {
        $table = $this->prefix . $rule->table;

        if ($rule->action === Action::DropIndex) {
            return [];
        }

        $clauses = [sprintf('DROP INDEX %s', $this->quote($rule->index))];

        if ($rule->replaces !== null) {
            $clauses[] = sprintf('ADD INDEX %s (%s)', $this->quote($rule->replaces), $this->columnList($rule->columns));
        }

        return [new Statement(
            sprintf('ALTER TABLE %s %s', $this->quote($table), implode(', ', $clauses)),
            $this->supportsOnlineChanges,
            $table
        )];
    }

    /** @param list<string> $columns */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map($this->column(...), $columns));
    }

    private function column(string $column): string
    {
        if (preg_match('/^(.+)\((\d+)\)$/', $column, $matches) === 1) {
            return $this->quote($matches[1]) . '(' . $matches[2] . ')';
        }

        return $this->quote($column);
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
