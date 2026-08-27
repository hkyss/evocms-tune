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
        return $this->run($finding->statements, $allowRebuild);
    }

    /**
     * @param  list<Statement> $statements
     * @return list<string>
     */
    public function run(array $statements, bool $allowRebuild): array
    {
        foreach ($statements as $statement) {
            if (!$statement->online && !$allowRebuild) {
                throw new RebuildRefused($statement);
            }
        }

        $executed = [];

        foreach ($statements as $statement) {
            $this->connection->statement($statement->sql);
            $executed[] = $statement->sql;
        }

        return $executed;
    }
}
