<?php

declare(strict_types=1);

namespace hkyss\Tune\Console\Commands;

use hkyss\Tune\Console\DatabaseCommand;

class PruneCommand extends DatabaseCommand
{
    protected $signature = 'db:prune
        {--table=* : Restrict to these log tables}
        {--days= : Override the retention configured for every selected table}
        {--database= : Connection to change}
        {--dry-run : Count the rows and delete nothing}';

    protected $description = 'Trim the audit logs Evolution writes to and never removes from';

    public function handle(): int
    {
        $configured = (array) $this->setting('tune.prune', []);
        $selected = $this->option('table');
        $selected = is_array($selected) ? array_map('strval', $selected) : [];
        $batch = max(100, (int) $this->setting('tune.prune_batch', 5000));
        $removed = 0;

        $this->newLine();

        foreach ($configured as $table => $settings) {
            $table = (string) $table;

            if ($selected !== [] && !in_array($table, $selected, true)) {
                continue;
            }

            if (!$this->reader()->hasTable($table)) {
                continue;
            }

            $removed += $this->prune($table, (string) ($settings['column'] ?? 'createdon'), $this->days($settings), $batch);
        }

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>%d row(s) %s.</>',
            $removed,
            $this->option('dry-run') ? 'would be removed' : 'removed'
        ));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $settings */
    private function days(array $settings): int
    {
        $override = $this->option('days');

        return $override !== null && $override !== '' ? max(1, (int) $override) : max(1, (int) ($settings['days'] ?? 90));
    }

    private function prune(string $table, string $column, int $days, int $batch): int
    {
        $qualified = $this->reader()->qualify($table);
        $cutoff = time() - ($days * 86400);
        $connection = $this->connection();

        $total = (int) $connection->table($qualified)->where($column, '<', $cutoff)->count();

        $this->line(sprintf('  %-14s older than %3d day(s): <comment>%d</comment>', $table, $days, $total));

        if ($total === 0 || $this->option('dry-run')) {
            return $total;
        }

        $removed = 0;

        do {
            $deleted = $connection->table($qualified)->where($column, '<', $cutoff)->limit($batch)->delete();
            $removed += $deleted;
        } while ($deleted > 0);

        return $removed;
    }
}
