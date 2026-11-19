<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

use hkyss\Tune\Rules\Tier;

final class Plan
{
    /** @var list<Finding> */
    public readonly array $findings;

    /**
     * @param list<Finding> $curated
     * @param list<Finding> $derived
     */
    public function __construct(
        public readonly array $curated,
        public readonly array $derived = [],
    ) {
        $this->findings = [...$curated, ...$derived];
    }

    /** @return list<Finding> */
    public function pending(): array
    {
        return self::actionable($this->findings);
    }

    /** @return list<Finding> */
    public function pendingCurated(): array
    {
        return self::actionable($this->curated);
    }

    /** @return list<Finding> */
    public function pendingDerived(): array
    {
        return self::actionable($this->derived);
    }

    /** @return list<Finding> */
    public function blocked(): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $f): bool => $f->status === Status::Blocked));
    }

    /** @return list<Finding> */
    public function withStatus(Status $status): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $f): bool => $f->status === $status));
    }

    /** @return array<string, list<Finding>> */
    public function byTable(): array
    {
        $grouped = [];

        foreach ($this->findings as $finding) {
            $grouped[$finding->rule->table][] = $finding;
        }

        ksort($grouped);

        return $grouped;
    }

    public function countOfTier(Tier $tier): int
    {
        return count(array_filter(
            $this->pending(),
            static fn (Finding $f): bool => $f->rule->tier === $tier
        ));
    }

    public function isClean(): bool
    {
        return $this->pending() === [];
    }

    /**
     * @param  list<Finding> $findings
     * @return list<Finding>
     */
    private static function actionable(array $findings): array
    {
        return array_values(array_filter($findings, static fn (Finding $f): bool => $f->status->isActionable()));
    }
}
