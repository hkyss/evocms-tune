<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

use hkyss\Tune\Apply\StatementBuilder;
use hkyss\Tune\Rules\Action;
use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Rules\Ruleset;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\Index;
use hkyss\Tune\Schema\SchemaReader;

class Planner
{
    public function __construct(
        private readonly SchemaReader $reader,
        private readonly RedundancyAnalyzer $analyzer = new RedundancyAnalyzer(),
    ) {
    }

    /** @param list<string> $only */
    public function plan(Tier $upTo = Tier::Core, array $only = []): Plan
    {
        $builder = new StatementBuilder(
            $this->reader->prefix(),
            $this->reader->platform()->supportsOnlineIndexChanges()
        );

        $curated = $this->select(Ruleset::evolutionCore(), $upTo, $only);
        $findings = array_map(fn (Rule $rule): Finding => $this->evaluate($rule, $builder), $curated);

        $projected = $this->project($findings);
        $derived = $this->select($this->analyzer->rulesFor($projected), $upTo, $only);

        foreach ($derived as $rule) {
            if ($this->alreadyCovered($rule, $findings)) {
                continue;
            }

            $findings[] = new Finding($rule, Status::Pending, $builder->forRule($rule));
        }

        return new Plan($findings);
    }

    private function evaluate(Rule $rule, StatementBuilder $builder): Finding
    {
        if (!$this->reader->hasTable($rule->table)) {
            return new Finding($rule, Status::Absent, [], 'table not present in this installation');
        }

        return match ($rule->action) {
            Action::AddIndex => $this->evaluateAddIndex($rule, $builder),
            Action::AddUnique => $this->evaluateAddUnique($rule, $builder),
            Action::DropIndex => $this->evaluateDropIndex($rule, $builder),
        };
    }

    private function evaluateAddIndex(Rule $rule, StatementBuilder $builder): Finding
    {
        $covering = $this->reader->coveringIndex($rule->table, $rule->columns);

        if ($covering !== null) {
            return new Finding($rule, Status::Satisfied, [], sprintf('covered by %s', $covering->name));
        }

        return new Finding($rule, Status::Pending, $builder->forRule($rule));
    }

    private function evaluateAddUnique(Rule $rule, StatementBuilder $builder): Finding
    {
        if ($this->reader->uniqueOver($rule->table, $rule->columns) !== null) {
            return new Finding($rule, Status::Satisfied);
        }

        $duplicates = $this->reader->duplicateGroups($rule->table, $rule->columns);

        if ($duplicates > 0) {
            return new Finding($rule, Status::Blocked, [], sprintf(
                '%d duplicate group(s) on (%s) — resolve the data before the constraint',
                $duplicates,
                implode(', ', $rule->columns)
            ));
        }

        $stillThere = $rule->replaces !== null && $this->reader->indexNamed($rule->table, $rule->replaces) !== null;
        $effective = $stillThere
            ? $rule
            : new Rule($rule->id, $rule->table, $rule->action, $rule->index, $rule->columns, $rule->tier, $rule->reason);

        return new Finding($rule, Status::Pending, $builder->forRule($effective));
    }

    private function evaluateDropIndex(Rule $rule, StatementBuilder $builder): Finding
    {
        if ($this->reader->indexNamed($rule->table, $rule->index) === null) {
            return new Finding($rule, Status::Satisfied, [], 'index not present');
        }

        return new Finding($rule, Status::Pending, $builder->forRule($rule));
    }

    /**
     * @param  list<Finding>              $findings
     * @return array<string, list<Index>>
     */
    private function project(array $findings): array
    {
        $projected = $this->reader->all();

        foreach ($findings as $finding) {
            if (!$finding->status->isActionable()) {
                continue;
            }

            $rule = $finding->rule;
            $dropped = $rule->action === Action::DropIndex ? [$rule->index] : array_filter([$rule->replaces]);

            $projected[$rule->table] = array_values(array_filter(
                $projected[$rule->table] ?? [],
                static fn (Index $index): bool => !in_array($index->name, $dropped, true)
            ));

            if ($rule->action->isAdditive()) {
                $projected[$rule->table][] = new Index(
                    $rule->index,
                    $rule->columns,
                    $rule->action === Action::AddUnique
                );
            }
        }

        return $projected;
    }

    /** @param list<Finding> $findings */
    private function alreadyCovered(Rule $rule, array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding->rule->table === $rule->table && $finding->rule->index === $rule->index) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Rule>   $rules
     * @param  list<string> $only
     * @return list<Rule>
     */
    private function select(array $rules, Tier $upTo, array $only): array
    {
        return array_values(array_filter($rules, static function (Rule $rule) use ($upTo, $only): bool {
            if ($only !== [] && !in_array($rule->id, $only, true) && !in_array($rule->table, $only, true)) {
                return false;
            }

            return $upTo->includes($rule->tier);
        }));
    }
}
