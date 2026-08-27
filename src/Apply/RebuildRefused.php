<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

use RuntimeException;

final class RebuildRefused extends RuntimeException
{
    public function __construct(public readonly Statement $statement)
    {
        parent::__construct(sprintf(
            'Refusing to run a statement that rebuilds %s and blocks writes while it does: %s',
            $statement->table,
            $statement->sql
        ));
    }
}
