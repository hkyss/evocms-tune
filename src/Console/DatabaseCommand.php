<?php

declare(strict_types=1);

namespace hkyss\Tune\Console;

use hkyss\Tune\Analysis\Planner;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\SchemaReader;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

abstract class DatabaseCommand extends Command
{
    private ?SchemaReader $reader = null;

    protected function connection(): Connection
    {
        $name = $this->option('database') ?: $this->setting('tune.connection');

        /** @var DatabaseManager $manager */
        $manager = $this->laravel->make('db');

        return $manager->connection(is_string($name) && $name !== '' ? $name : null);
    }

    protected function reader(): SchemaReader
    {
        return $this->reader ??= new SchemaReader($this->connection());
    }

    protected function planner(): Planner
    {
        return new Planner($this->reader());
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        try {
            return $this->laravel->make('config')->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function tier(): Tier
    {
        $requested = (string) ($this->option('tier') ?: $this->setting('tune.tier', 'core'));
        $tier = Tier::tryFrom($requested);

        if ($tier === null) {
            $this->warn(sprintf('Unknown tier "%s", falling back to core.', $requested));

            return Tier::Core;
        }

        return $tier;
    }

    /** @return list<string> */
    protected function only(): array
    {
        $only = $this->option('only');
        $values = array_map('strval', is_array($only) ? $only : []);

        return array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
    }

    protected function reportPlatform(): void
    {
        $platform = $this->reader()->platform();
        $prefix = $this->reader()->prefix();

        $this->line(sprintf(
            '<comment>%s %s</comment>, table prefix <comment>%s</comment>, online index changes: <comment>%s</comment>',
            $platform->isMariaDb() ? 'MariaDB' : ucfirst($platform->driver),
            $platform->versionNumber(),
            $prefix !== '' ? $prefix : '(none)',
            $platform->supportsOnlineIndexChanges() ? 'yes' : 'no'
        ));
        $this->newLine();
    }
}
