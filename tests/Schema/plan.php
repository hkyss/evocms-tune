<?php

declare(strict_types=1);

use hkyss\Tune\Analysis\Planner;
use hkyss\Tune\Apply\Applier;
use hkyss\Tune\Apply\Statistics;
use hkyss\Tune\Record\Journal;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\SchemaReader;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../vendor/autoload.php';

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('TUNE_TEST_HOST') ?: '127.0.0.1',
    'port' => getenv('TUNE_TEST_PORT') ?: '3306',
    'database' => getenv('TUNE_TEST_DATABASE') ?: 'evolution',
    'username' => getenv('TUNE_TEST_USERNAME') ?: 'root',
    'password' => getenv('TUNE_TEST_PASSWORD') ?: 'root',
    'charset' => 'utf8mb4',
    'prefix' => getenv('TUNE_TEST_PREFIX') ?: 'evo_',
]);

$connection = $capsule->getConnection();
$reader = new SchemaReader($connection);
$planner = new Planner($reader);
$applier = new Applier($connection);
$journal = new Journal($connection, $reader);

/** @return array<string, array<string, string>> */
$inventory = static function (SchemaReader $reader): array {
    $inventory = [];

    foreach ($reader->all() as $table => $indexes) {
        foreach ($indexes as $index) {
            $inventory[$table][$index->name] = sprintf(
                '%s|%s|%s',
                $index->signature(),
                $index->unique ? 'unique' : 'plain',
                $index->type
            );
        }

        ksort($inventory[$table]);
    }

    ksort($inventory);

    return $inventory;
};

$before = $inventory($reader);
$pending = $planner->plan(Tier::Aggressive)->pending();

printf("%d change(s) planned against the baseline schema.\n", count($pending));

if ($pending === []) {
    fwrite(STDERR, "The planner found nothing on a stock schema, which means it stopped reading it.\n");

    exit(1);
}

foreach ($pending as $finding) {
    $journal->record($finding->rule, $applier->apply($finding, true), $finding->undo);
}

$refreshed = (new Statistics($connection))->refreshFor(array_merge(
    ...array_map(static fn ($finding): array => $finding->statements, $pending)
));

if ($refreshed < 1) {
    fwrite(STDERR, "A batch of index changes left the optimizer on the statistics it had.\n");

    exit(1);
}

printf("Statistics read again on %d table(s).\n", $refreshed);

$reader->forget();

if (!$planner->plan(Tier::Aggressive)->isClean()) {
    fwrite(STDERR, "Applying the plan did not settle it:\n");

    foreach ($planner->plan(Tier::Aggressive)->pending() as $finding) {
        fwrite(STDERR, sprintf("  %s %s\n", $finding->rule->table, $finding->rule->describe()));
    }

    exit(1);
}

printf("Applied, and a second pass finds nothing left to do. %d change(s) on record.\n", $journal->count());

foreach ($journal->entries() as $change) {
    $applier->run($change->undo, true);
    $journal->forget($change->id);
}

$journal->discard();
$reader->forget();
$after = $inventory($reader);

if ($before !== $after) {
    fwrite(STDERR, "db:untune did not put the schema back:\n");

    foreach ($before as $table => $indexes) {
        foreach ($indexes as $name => $definition) {
            if (($after[$table][$name] ?? null) !== $definition) {
                fwrite(STDERR, sprintf("  lost %s.%s (%s)\n", $table, $name, $definition));
            }
        }
    }

    foreach ($after as $table => $indexes) {
        foreach ($indexes as $name => $definition) {
            if (($before[$table][$name] ?? null) !== $definition) {
                fwrite(STDERR, sprintf("  left %s.%s (%s)\n", $table, $name, $definition));
            }
        }
    }

    exit(1);
}

$again = count($planner->plan(Tier::Aggressive)->pending());

if ($again !== count($pending)) {
    fwrite(STDERR, sprintf("The schema is back but the plan is not: %d change(s), expected %d.\n", $again, count($pending)));

    exit(1);
}

printf("Undone, and the schema is identical to the baseline — %d change(s) planned again.\n", $again);

// An index this package created and something else renamed: db:untune has to read that
// record as stale rather than fail on it at every run from here on.
$pair = $planner->plan(Tier::Core, ['tmplvar_contentvalues.pair'])->pending();

if (count($pair) !== 1) {
    fwrite(STDERR, sprintf("Expected one pending change for the unique pair, got %d.\n", count($pair)));

    exit(1);
}

$values = $reader->qualify('site_tmplvar_contentvalues');
$journal->record($pair[0]->rule, $applier->apply($pair[0], true), $pair[0]->undo);
$connection->statement(sprintf(
    'ALTER TABLE `%s` DROP INDEX `tune_tvcv_pair`, ADD UNIQUE INDEX `ix_tvid_contentid` (`tmplvarid`, `contentid`)',
    $values
));
$reader->forget();

$prevailing = $reader->prevailingCollation();
$mine = $reader->collationOf(Journal::TABLE);

if ($prevailing !== null && $mine !== $prevailing) {
    fwrite(STDERR, sprintf("The record table is %s where the schema around it is %s.\n", (string) $mine, $prevailing));

    exit(1);
}

printf("The record table reads like the schema around it: %s.\n", (string) $mine);

$recorded = $journal->entries();
$guards = array_values(array_filter(array_map(static fn ($change) => $change->undo[0]->guard ?? null, $recorded)));

if ($guards === []) {
    fwrite(STDERR, "The record carries no guard, so nothing can tell it has gone stale.\n");

    exit(1);
}

foreach ($guards as $guard) {
    if ($guard->holds($reader)) {
        fwrite(STDERR, sprintf("A renamed index still reads as replayable: %s\n", $guard->explain()));

        exit(1);
    }
}

printf("A record the schema has moved past reads as stale: %s.\n", $guards[0]->explain());

foreach ($recorded as $change) {
    $journal->forget($change->id);
}

$journal->discard();
$connection->statement(sprintf(
    'ALTER TABLE `%s` DROP INDEX `ix_tvid_contentid`, ADD INDEX `idx_tmplvarid_contentid` (`tmplvarid`, `contentid`)',
    $values
));
$reader->forget();

if ($inventory($reader) !== $before) {
    fwrite(STDERR, "The stale-record check did not leave the schema as it found it.\n");

    exit(1);
}

foreach (['event_log' => 'createdon', 'manager_log' => 'timestamp'] as $table => $column) {
    $connection->table($table)->insert([$column => 1]);
    $removed = $connection->table($table)->where($column, '<', 2)->delete();

    if ($removed !== 1) {
        fwrite(STDERR, sprintf("The prune path did not reach %s.\n", $reader->qualify($table)));

        exit(1);
    }
}

echo "The prune path reaches its tables through the connection prefix.\n";

unset($inventory, $before, $after, $again, $index, $removed, $pair, $values, $recorded, $guards, $guard, $change);
