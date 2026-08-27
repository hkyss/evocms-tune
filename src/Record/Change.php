<?php

declare(strict_types=1);

namespace hkyss\Tune\Record;

use hkyss\Tune\Apply\Statement;

final class Change
{
    /** @param list<Statement> $undo */
    public function __construct(
        public readonly int $id,
        public readonly string $ruleId,
        public readonly string $table,
        public readonly string $appliedSql,
        public readonly array $undo,
        public readonly int $appliedAt,
    ) {
    }

    public function requiresRebuild(): bool
    {
        foreach ($this->undo as $statement) {
            if (!$statement->online) {
                return true;
            }
        }

        return false;
    }
}
