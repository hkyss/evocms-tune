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
            foreach ($indexes as $candidate) {
                $covering = $this->coveringIndexFor($candidate, $indexes);

                if ($covering === null) {
                    continue;
                }

                $rules[] = Rule::dropIndex(
                    sprintf('redundant.%s.%s', $table, $candidate->name),
                    (string) $table,
                    $candidate->name,
                    Tier::Core,
                    sprintf(
                        'Every entry of (%s) is already the leading part of %s (%s), so this index '
                        . 'answers no query the wider one cannot and costs a write on every row.',
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
    private function coveringIndexFor(Index $candidate, array $indexes): ?Index
    {
        if ($candidate->isPrimary()) {
            return null;
        }

        foreach ($indexes as $other) {
            if ($other->name === $candidate->name || !$candidate->isPrefixOf($other)) {
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

    private function losesTieBreak(Index $candidate, Index $other): bool
    {
        if (!$candidate->coversSameColumnsAs($other) || $candidate->unique !== $other->unique) {
            return false;
        }

        return $other->isPrimary() ? false : strcmp($candidate->name, $other->name) < 0;
    }
}
