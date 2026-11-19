<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\Index;

final class RedundancyAnalyzer
{
    /**
     * @param  array<string, list<Index>> $indexesByTable
     * @return list<Rule>
     */
    public function rulesFor(array $indexesByTable): array
    {
        $rules = [];

        foreach ($indexesByTable as $table => $indexes) {
            $primary = $this->primaryOf($indexes);

            foreach ($indexes as $candidate) {
                $covering = $this->coveringIndexFor($candidate, $indexes, $primary);

                if ($covering === null) {
                    continue;
                }

                $rules[] = Rule::dropIndex(
                    sprintf('redundant.%s.%s', $table, $candidate->name),
                    (string) $table,
                    $candidate->name,
                    Tier::Core,
                    sprintf(
                        'Every entry of (%s), with the primary key InnoDB keeps after it, is already the '
                        . 'leading part of %s (%s), so this index answers no query the wider one cannot '
                        . 'and costs a write on every row.',
                        $candidate->signature(),
                        $covering->name,
                        $covering->signature()
                    )
                );
            }
        }

        usort($rules, static fn (Rule $a, Rule $b): int => [$a->table, $a->index] <=> [$b->table, $b->index]);

        return $rules;
    }

    /** @param list<Index> $indexes */
    private function coveringIndexFor(Index $candidate, array $indexes, ?Index $primary): ?Index
    {
        if ($candidate->isPrimary() || !$candidate->isComparableByColumns()) {
            return null;
        }

        foreach ($indexes as $other) {
            if ($other->name === $candidate->name || !$other->isComparableByColumns()) {
                continue;
            }

            if (!$this->readsInTheSameOrder($candidate, $other, $primary)) {
                continue;
            }

            if ($candidate->unique && !($other->unique && $candidate->coversSameColumnsAs($other))) {
                continue;
            }

            if ($this->losesTieBreak($candidate, $other)) {
                continue;
            }

            return $other;
        }

        return null;
    }

    // InnoDB keeps the primary key after every secondary index's columns, so (parent) reads a parent's rows in id order and (parent, menuindex) does not.
    private function readsInTheSameOrder(Index $candidate, Index $other, ?Index $primary): bool
    {
        if ($primary === null) {
            return $candidate->coversSameColumnsAs($other);
        }

        $short = $this->withPrimaryKey($candidate, $primary);
        $long = $this->withPrimaryKey($other, $primary);

        return count($short) <= count($long) && array_slice($long, 0, count($short)) === $short;
    }

    /** @return list<string> */
    private function withPrimaryKey(Index $index, Index $primary): array
    {
        $missing = array_diff($primary->columns, $index->columns);

        return [...$index->columns, ...array_values($missing)];
    }

    /** @param list<Index> $indexes */
    private function primaryOf(array $indexes): ?Index
    {
        foreach ($indexes as $index) {
            if ($index->isPrimary()) {
                return $index;
            }
        }

        return null;
    }

    private function losesTieBreak(Index $candidate, Index $other): bool
    {
        if (!$candidate->coversSameColumnsAs($other) || $candidate->unique !== $other->unique) {
            return false;
        }

        return $other->isPrimary() ? false : strcmp($candidate->name, $other->name) < 0;
    }
}
