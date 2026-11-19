<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Mysql;

use hkyss\Tune\Analysis\Planner;
use hkyss\Tune\Console\Commands\TuneCommand;
use hkyss\Tune\Rules\Rule;

final class TuneWithRules extends TuneCommand
{
    /** @param list<Rule> $rules */
    public function __construct(private readonly array $rules)
    {
        parent::__construct();
    }

    protected function planner(): Planner
    {
        return new Planner($this->reader(), rules: $this->rules);
    }
}
