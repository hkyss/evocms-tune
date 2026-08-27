<?php

declare(strict_types=1);

use hkyss\Tune\Analysis\Finding;
use hkyss\Tune\Apply\Applier;
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
$planner = new hkyss\Tune\Analysis\Planner($reader);

$plan = $planner->plan(Tier::Aggressive);
$pending = $plan->pending();

printf("%d change(s) planned against the baseline schema.\n", count($pending));

if ($pending === []) {
    fwrite(STDERR, "The planner found nothing on a stock schema, which means it stopped reading it.\n");

    exit(1);
}

$applier = new Applier($connection);

foreach ($pending as $finding) {
    foreach ($applier->apply($finding, true) as $sql) {
        printf("  %s;\n", $sql);
    }
}

$reader->forget();
$second = $planner->plan(Tier::Aggressive);

if (!$second->isClean()) {
    fwrite(STDERR, "Applying the plan did not settle it:\n");

    foreach ($second->pending() as $finding) {
        fwrite(STDERR, sprintf("  %s %s\n", $finding->rule->table, $finding->rule->describe()));
    }

    exit(1);
}

$blocked = array_map(static fn (Finding $finding): string => $finding->rule->id, $second->blocked());

if ($blocked !== []) {
    fwrite(STDERR, sprintf("Blocked after applying: %s\n", implode(', ', $blocked)));

    exit(1);
}

echo "Applied, and a second pass finds nothing left to do.\n";
