<?php

declare(strict_types=1);

namespace hkyss\Tune\Schema;

final class Index
{
    public const BTREE = 'BTREE';
    public const FULLTEXT = 'FULLTEXT';

    /** @param list<string> $columns */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly bool $unique,
        public readonly string $type = self::BTREE,
    ) {
    }

    public function isPrimary(): bool
    {
        return $this->name === 'PRIMARY';
    }

    public function signature(): string
    {
        return implode(', ', $this->columns);
    }

    public function coversSameColumnsAs(self $other): bool
    {
        return $this->columns === $other->columns;
    }

    public function isPrefixOf(self $other): bool
    {
        if ($this->type !== $other->type || count($this->columns) > count($other->columns)) {
            return false;
        }

        return array_slice($other->columns, 0, count($this->columns)) === $this->columns;
    }
}
