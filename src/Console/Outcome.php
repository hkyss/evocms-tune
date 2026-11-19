<?php

declare(strict_types=1);

namespace hkyss\Tune\Console;

use hkyss\Tune\Apply\Statement;

final class Outcome
{
    public int $applied = 0;

    public int $failed = 0;

    public int $skipped = 0;

    /** @var list<Statement> */
    public array $touched = [];
}
