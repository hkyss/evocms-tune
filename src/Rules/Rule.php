<?php

declare(strict_types=1);

namespace hkyss\Tune\Rules;

final class Rule
{
    /** @param list<string> $columns */
    public function __construct(
        public readonly string $id,
        public readonly string $table,
        public readonly Action $action,
        public readonly string $index,
        public readonly array $columns,
        public readonly Tier $tier,
        public readonly string $reason,
        public readonly ?string $replaces = null,
        public readonly bool $rebuild = false,
    ) {
    }

    /** @param list<string> $columns */
    public static function addIndex(
        string $id,
        string $table,
        string $index,
        array $columns,
        Tier $tier,
        string $reason,
    ): self {
        return new self($id, $table, Action::AddIndex, $index, $columns, $tier, $reason);
    }

    /** @param list<string> $columns */
    public static function addUnique(
        string $id,
        string $table,
        string $index,
        array $columns,
        Tier $tier,
        string $reason,
        ?string $replaces = null,
    ): self {
        return new self($id, $table, Action::AddUnique, $index, $columns, $tier, $reason, $replaces);
    }

    public static function dropIndex(
        string $id,
        string $table,
        string $index,
        Tier $tier,
        string $reason,
        bool $rebuild = false,
    ): self {
        return new self($id, $table, Action::DropIndex, $index, [], $tier, $reason, null, $rebuild);
    }

    public function describe(): string
    {
        return match ($this->action) {
            Action::AddIndex => sprintf('add index %s (%s)', $this->index, implode(', ', $this->columns)),
            Action::AddUnique => sprintf('add unique %s (%s)', $this->index, implode(', ', $this->columns)),
            Action::DropIndex => sprintf('drop index %s', $this->index),
        };
    }
}
