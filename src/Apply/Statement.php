<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

final class Statement
{
    public function __construct(
        public readonly string $sql,
        public readonly bool $online,
        public readonly string $table,
    ) {
    }
}
