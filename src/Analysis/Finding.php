<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

use hkyss\Tune\Apply\Statement;
use hkyss\Tune\Rules\Rule;

final class Finding
{
    /**
     * @param list<Statement> $statements
     * @param list<Statement> $undo
     */
    public function __construct(
        public readonly Rule $rule,
        public readonly Status $status,
        public readonly array $statements = [],
        public readonly array $undo = [],
        public readonly ?string $detail = null,
    ) {
    }

    public function requiresRebuild(): bool
    {
        foreach ($this->statements as $statement) {
            if (!$statement->online) {
                return true;
            }
        }

        return false;
    }
}
