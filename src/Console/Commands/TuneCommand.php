<?php

declare(strict_types=1);

namespace hkyss\Tune\Console\Commands;

use hkyss\Tune\Analysis\Finding;
use hkyss\Tune\Apply\Applier;
use hkyss\Tune\Apply\RebuildRefused;
use hkyss\Tune\Apply\Statement;
use hkyss\Tune\Apply\Statistics;
use hkyss\Tune\Console\DatabaseCommand;
use hkyss\Tune\Record\Journal;
use Throwable;

class TuneCommand extends DatabaseCommand
{
    protected $signature = 'db:tune
        {--tier=core : How far to go — core, extended or aggressive}
        {--only=* : Restrict to these rule ids or table names}
        {--database= : Connection to change}
        {--dry-run : Print the statements and change nothing}
        {--allow-rebuild : Permit statements that rebuild a table and block writes}
        {--no-analyze : Leave the optimizer reading the statistics it had before}
        {--force : Skip the confirmation}';

    protected $description = 'Apply the schema changes db:doctor reports';

    public function handle(): int
    {
        $plan = $this->planner()->plan($this->tier(), $this->only());
        $pending = $plan->pending();

        $this->newLine();
        $this->reportPlatform();

        if ($pending === []) {
            $this->info('Nothing to apply.');

            return self::SUCCESS;
        }

        foreach ($pending as $finding) {
            foreach ($finding->statements as $statement) {
                $this->line(sprintf('  %s %s;', $statement->online ? '<fg=green>online</>' : '<fg=red>blocks</>', $statement->sql));
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->comment(sprintf('%d statement(s) — dry run, nothing was changed.', $this->statementCount($pending)));

            return self::SUCCESS;
        }

        $blocking = array_values(array_filter($pending, static fn (Finding $f): bool => $f->requiresRebuild()));

        if ($blocking !== [] && !$this->option('allow-rebuild')) {
            $this->warn(sprintf(
                '%d change(s) rebuild a table and block writes. Re-run with --allow-rebuild once you have a window.',
                count($blocking)
            ));
        }

        if (!$this->option('force') && !$this->confirm(sprintf('Apply %d change(s)?', count($pending)), false)) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        return $this->applyAll($plan->pending());
    }

    /** @param list<Finding> $pending */
    private function applyAll(array $pending): int
    {
        $applier = new Applier($this->connection());
        $journal = new Journal($this->connection(), $this->reader());
        $applied = 0;
        $failed = 0;
        $touched = [];

        foreach ($pending as $finding) {
            try {
                $journal->record($finding->rule, $applier->apply($finding, (bool) $this->option('allow-rebuild')), $finding->undo);
                array_push($touched, ...$finding->statements);
                $this->line(sprintf('  <fg=green>done</> %s.%s', $finding->rule->table, $finding->rule->describe()));
                $applied++;
            } catch (RebuildRefused) {
                $this->line(sprintf('  <fg=yellow>held</> %s.%s — needs --allow-rebuild', $finding->rule->table, $finding->rule->describe()));
            } catch (Throwable $e) {
                $this->line(sprintf('  <fg=red>fail</> %s.%s — %s', $finding->rule->table, $finding->rule->describe(), $e->getMessage()));
                $failed++;
            }
        }

        $this->newLine();
        $this->line(sprintf('<options=bold>%d applied, %d failed.</>', $applied, $failed));

        if ($applied > 0) {
            $this->reader()->forget();
            $this->refresh($touched);
            $this->line('Run <comment>php artisan db:doctor</comment> to confirm.');
            $this->line(sprintf('Recorded in %s; <comment>php artisan db:untune</comment> puts it back.', $journal->table()));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // An index the optimizer has no statistics for is one it may decline to use, so the change
    // is not finished until they are read again.
    /** @param list<Statement> $statements */
    private function refresh(array $statements): void
    {
        if ($this->option('no-analyze') || $statements === []) {
            return;
        }

        $tables = (new Statistics($this->connection()))->refreshFor($statements);

        $this->line(sprintf('Statistics read again on %d table(s).', $tables));
    }

    /** @param list<Finding> $pending */
    private function statementCount(array $pending): int
    {
        return (int) array_sum(array_map(static fn (Finding $f): int => count($f->statements), $pending));
    }
}
