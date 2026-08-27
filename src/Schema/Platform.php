<?php

declare(strict_types=1);

namespace hkyss\Tune\Schema;

final class Platform
{
    public function __construct(
        public readonly string $driver,
        public readonly string $version,
    ) {
    }

    public function isMariaDb(): bool
    {
        return stripos($this->version, 'mariadb') !== false;
    }

    public function isMySql(): bool
    {
        return $this->driver === 'mysql' && !$this->isMariaDb();
    }

    public function supportsOnlineIndexChanges(): bool
    {
        if (!$this->isMySql() && !$this->isMariaDb()) {
            return false;
        }

        $number = $this->versionNumber();

        return $this->isMariaDb() ? version_compare($number, '10.0', '>=') : version_compare($number, '5.6', '>=');
    }

    public function versionNumber(): string
    {
        preg_match('/\d+(?:\.\d+){0,2}/', $this->version, $matches);

        return $matches[0] ?? '0';
    }
}
