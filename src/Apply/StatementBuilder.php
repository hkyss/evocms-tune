<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

use hkyss\Tune\Rules\Action;
use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Schema\Index;

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
        $clauses = [];

        if ($rule->action === Action::AddUnique && $rule->replaces !== null) {
            $clauses[] = $this->dropClause($rule->replaces);
        }

        $clauses[] = match ($rule->action) {
            Action::AddIndex => $this->addClause($rule->index, $rule->columns, false, Index::BTREE),
            Action::AddUnique => $this->addClause($rule->index, $rule->columns, true, Index::BTREE),
            Action::DropIndex => $this->dropClause($rule->index),
        };

        return [$this->alter($rule->table, $clauses, !$rule->rebuild)];
    }

    /** @return list<Statement> */
    public function undoOfAddition(Rule $rule, ?Index $restored): array
    {
        $clauses = [$this->dropClause($rule->index)];

        if ($restored !== null) {
            $clauses[] = $this->addClause($restored->name, $restored->columns, $restored->unique, $restored->type);
        }

        return [$this->alter($rule->table, $clauses, $restored?->type !== Index::FULLTEXT)];
    }

    /** @return list<Statement> */
    public function undoOfDrop(string $table, Index $dropped): array
    {
        $clause = $this->addClause($dropped->name, $dropped->columns, $dropped->unique, $dropped->type);

        return [$this->alter($table, [$clause], $dropped->type !== Index::FULLTEXT)];
    }

    /** @param list<string> $clauses */
    private function alter(string $table, array $clauses, bool $online): Statement
    {
        $qualified = $this->prefix . $table;
        $online = $online && $this->supportsOnlineChanges;
        $sql = sprintf('ALTER TABLE %s %s', $this->quote($qualified), implode(', ', $clauses));

        return new Statement($online ? $sql . ', ALGORITHM=INPLACE, LOCK=NONE' : $sql, $online, $qualified);
    }

    /** @param list<string> $columns */
    private function addClause(string $name, array $columns, bool $unique, string $type): string
    {
        $kind = match (true) {
            $type === Index::FULLTEXT => 'FULLTEXT INDEX',
            $unique => 'UNIQUE INDEX',
            default => 'INDEX',
        };

        return sprintf('ADD %s %s (%s)', $kind, $this->quote($name), $this->columnList($columns));
    }

    private function dropClause(string $name): string
    {
        return sprintf('DROP INDEX %s', $this->quote($name));
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
