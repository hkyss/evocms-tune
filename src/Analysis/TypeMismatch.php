<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

final class TypeMismatch
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
        public readonly string $type,
        public readonly string $target,
        public readonly string $targetType,
    ) {
    }

    public function describe(): string
    {
        return sprintf(
            '%s.%s is %s, and %s it points at is %s',
            $this->table,
            $this->column,
            $this->type,
            $this->target,
            $this->targetType
        );
    }
}
