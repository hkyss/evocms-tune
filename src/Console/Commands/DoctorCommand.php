<?php

declare(strict_types=1);

namespace hkyss\Tune\Console\Commands;

use hkyss\Tune\Analysis\Finding;
use hkyss\Tune\Analysis\Plan;
use hkyss\Tune\Analysis\Status;
use hkyss\Tune\Apply\Statement;
use hkyss\Tune\Console\DatabaseCommand;
use hkyss\Tune\Rules\Tier;

class DoctorCommand extends DatabaseCommand
{
    protected $signature = 'db:doctor
        {--tier=core : How far to look — core, extended or aggressive}
        {--only=* : Restrict to these rule ids or table names}
        {--database= : Connection to inspect}
        {--all : Show satisfied rules too}
        {--json : Emit the plan as JSON}';

    protected $description = 'Report what this installation\'s schema is missing and what it carries for nothing';

    public function handle(): int
    {
        $plan = $this->planner()->plan($this->tier(), $this->only());

        if ($this->option('json')) {
            $this->line((string) json_encode($this->asArray($plan), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->reportPlatform();
        $this->report($plan);

        return self::SUCCESS;
    }

    private function report(Plan $plan): void
    {
        $showAll = (bool) $this->option('all');

        foreach ($plan->byTable() as $table => $findings) {
            $visible = $showAll
                ? $findings
                : array_values(array_filter($findings, static fn (Finding $f): bool => $f->status !== Status::Satisfied && $f->status !== Status::Absent));

            if ($visible === []) {
                continue;
            }

            $this->line(sprintf('<options=bold>%s</>', $table));

            foreach ($visible as $finding) {
                $this->line(sprintf('  %s %s', $this->marker($finding), $finding->rule->describe()));
                $this->line(sprintf('    <fg=gray>%s</>', $this->wrap($finding->rule->reason)));

                if ($finding->detail !== null) {
                    $this->line(sprintf('    <fg=yellow>%s</>', $finding->detail));
                }
            }

            $this->newLine();
        }

        $this->summarise($plan);
    }

    private function summarise(Plan $plan): void
    {
        $pending = count($plan->pending());
        $blocked = count($plan->blocked());

        if ($pending === 0 && $blocked === 0) {
            $this->info('Nothing to do — the schema already carries every index this ruleset asks for.');

            return;
        }

        $this->line(sprintf(
            '<options=bold>%d change(s) pending</> — core %d, extended %d, aggressive %d.',
            $pending,
            $plan->countOfTier(Tier::Core),
            $plan->countOfTier(Tier::Extended),
            $plan->countOfTier(Tier::Aggressive)
        ));

        if ($blocked > 0) {
            $this->warn(sprintf('%d change(s) blocked by the data, not the schema. Read the detail above.', $blocked));
        }

        $rebuilds = count(array_filter($plan->pending(), static fn (Finding $f): bool => $f->requiresRebuild()));

        if ($rebuilds > 0) {
            $this->warn(sprintf('%d of them rebuild a table and block writes while they run.', $rebuilds));
        }

        $this->newLine();
        $this->line('Apply them with <comment>php artisan db:tune --dry-run</comment> first.');
    }

    private function marker(Finding $finding): string
    {
        return match ($finding->status) {
            Status::Pending => $finding->requiresRebuild() ? '<fg=red>!</>' : '<fg=yellow>+</>',
            Status::Blocked => '<fg=red>x</>',
            Status::Satisfied => '<fg=green>ok</>',
            Status::Absent => '<fg=gray>-</>',
        };
    }

    /** @return array<string, mixed> */
    private function asArray(Plan $plan): array
    {
        return [
            'platform' => $this->reader()->platform()->versionNumber(),
            'prefix' => $this->reader()->prefix(),
            'findings' => array_map(static fn (Finding $f): array => [
                'id' => $f->rule->id,
                'table' => $f->rule->table,
                'action' => $f->rule->action->value,
                'tier' => $f->rule->tier->value,
                'status' => $f->status->value,
                'change' => $f->rule->describe(),
                'reason' => $f->rule->reason,
                'detail' => $f->detail,
                'rebuild' => $f->requiresRebuild(),
                'sql' => array_map(static fn (Statement $s): string => $s->sql, $f->statements),
            ], $plan->findings),
        ];
    }

    private function wrap(string $reason): string
    {
        return implode("\n    ", explode("\n", wordwrap($reason, 88)));
    }
}
