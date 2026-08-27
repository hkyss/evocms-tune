<?php

declare(strict_types=1);

namespace hkyss\Tune\Console\Commands;

use hkyss\Tune\Apply\Applier;
use hkyss\Tune\Apply\RebuildRefused;
use hkyss\Tune\Console\DatabaseCommand;
use hkyss\Tune\Record\Change;
use hkyss\Tune\Record\Journal;
use Throwable;

class UntuneCommand extends DatabaseCommand
{
    protected $signature = 'db:untune
        {--only=* : Restrict to these rule ids or table names}
        {--database= : Connection to change}
        {--dry-run : Print the statements and change nothing}
        {--allow-rebuild : Permit statements that rebuild a table and block writes}
        {--force : Skip the confirmation}';

    protected $description = 'Put back what db:tune changed, newest change first';

    public function handle(): int
    {
        $journal = $this->journal();

        $this->newLine();
        $this->reportPlatform();

        if (!$journal->exists()) {
            $this->info('Nothing recorded — db:tune has not run against this database.');

            return self::SUCCESS;
        }

        $changes = $journal->entries($this->only());

        if ($changes === []) {
            $this->info($this->only() === [] ? 'Nothing left to undo.' : 'Nothing recorded matches --only.');

            return self::SUCCESS;
        }

        $stale = 0;

        foreach ($changes as $change) {
            $moved = $this->movedOn($change);

            if ($moved !== null) {
                $this->line(sprintf('  <fg=gray>stale</>  %s — %s', $change->ruleId, $moved));
                $stale++;

                continue;
            }

            foreach ($change->undo as $statement) {
                $this->line(sprintf('  %s %s;', $statement->online ? '<fg=green>online</>' : '<fg=red>blocks</>', $statement->sql));
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->comment(sprintf(
                '%d change(s), %d of them stale — dry run, nothing was undone.',
                count($changes),
                $stale
            ));

            return self::SUCCESS;
        }

        $blocking = array_values(array_filter($changes, static fn (Change $change): bool => $change->requiresRebuild()));

        if ($blocking !== [] && !$this->option('allow-rebuild')) {
            $this->warn(sprintf(
                '%d of them put back a fulltext index, which rebuilds the table. Re-run with --allow-rebuild for those.',
                count($blocking)
            ));
        }

        if (!$this->option('force') && !$this->confirm(sprintf('Undo %d change(s)?', count($changes)), false)) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        return $this->replay($changes, $journal);
    }

    /** @param list<Change> $changes */
    private function replay(array $changes, Journal $journal): int
    {
        $applier = new Applier($this->connection());
        $undone = 0;
        $stale = 0;
        $failed = 0;

        foreach ($changes as $change) {
            $moved = $this->movedOn($change);

            // The schema has moved on since db:tune touched it: another migration, or a
            // hand at a console, renamed or dropped what this record names. It cannot be
            // replayed and will never become replayable, so it goes rather than failing
            // this run and every run after it.
            if ($moved !== null) {
                $journal->forget($change->id);
                $this->line(sprintf('  <fg=gray>stale</>  %s — %s, record dropped', $change->ruleId, $moved));
                $stale++;

                continue;
            }

            try {
                $applier->run($change->undo, (bool) $this->option('allow-rebuild'));
                $journal->forget($change->id);
                $this->reader()->forget();
                $this->line(sprintf('  <fg=green>undone</> %s', $change->ruleId));
                $undone++;
            } catch (RebuildRefused) {
                $this->line(sprintf('  <fg=yellow>held</>   %s — needs --allow-rebuild', $change->ruleId));
            } catch (Throwable $e) {
                $this->line(sprintf('  <fg=red>fail</>   %s — %s', $change->ruleId, $e->getMessage()));
                $failed++;
            }
        }

        $this->newLine();
        $this->line(sprintf('<options=bold>%d undone, %d stale, %d failed.</>', $undone, $stale, $failed));

        if ($journal->count() === 0) {
            $journal->discard();
            $this->line(sprintf('Nothing left on record; dropped %s.', $journal->table()));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function movedOn(Change $change): ?string
    {
        foreach ($change->undo as $statement) {
            if ($statement->guard !== null && !$statement->guard->holds($this->reader())) {
                return $statement->guard->explain();
            }
        }

        return null;
    }

    private function journal(): Journal
    {
        return new Journal($this->connection(), $this->reader());
    }
}
