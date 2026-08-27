<?php

declare(strict_types=1);

namespace hkyss\Tune\Apply;

use hkyss\Tune\Analysis\Finding;
use Illuminate\Database\Connection;

class Applier
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<string> */
    public function apply(Finding $finding, bool $allowRebuild): array
    {
        foreach ($finding->statements as $statement) {
            if (!$statement->online && !$allowRebuild) {
                throw new RebuildRefused($statement);
            }
        }

        $executed = [];

        foreach ($finding->statements as $statement) {
            $this->connection->statement($statement->sql);
            $executed[] = $statement->sql;
        }

        return $executed;
    }
}
