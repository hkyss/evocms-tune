<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Mysql;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

abstract class MysqlTestCase extends TestCase
{
    protected Connection $connection;

    private Capsule $capsule;

    protected function setUp(): void
    {
        if (getenv('EVOCMS_TEST_DB') !== 'mysql') {
            $this->markTestSkipped('Set EVOCMS_TEST_DB=mysql and EVOCMS_TEST_DB_* to run against MySQL.');
        }

        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'mysql',
            'host' => getenv('EVOCMS_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('EVOCMS_TEST_DB_PORT') ?: '3306',
            'database' => getenv('EVOCMS_TEST_DB_NAME') ?: 'evolution',
            'username' => getenv('EVOCMS_TEST_DB_USER') ?: 'root',
            'password' => getenv('EVOCMS_TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'evo_',
        ]);

        $this->connection = $this->capsule->getConnection();
        $this->dropTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->dropTables();
            $this->connection->disconnect();
        }
    }

    /** @param array<string, mixed> $input */
    protected function artisan(Command $command, array $input = []): CommandTester
    {
        $container = new class () extends Container {
            public function runningUnitTests(): bool
            {
                return true;
            }
        };
        $container->instance('db', $this->capsule->getDatabaseManager());
        $command->setLaravel($container);

        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }

    /** @return list<string> */
    protected function indexesOf(string $table): array
    {
        $rows = $this->connection->select(
            'SELECT DISTINCT INDEX_NAME AS `name` FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['evo_' . $table]
        );

        $names = array_map(static fn (object $row): string => (string) $row->name, $rows);
        sort($names);

        return $names;
    }

    private function dropTables(): void
    {
        foreach ($this->connection->select('SHOW TABLES') as $row) {
            $this->connection->statement(sprintf('DROP TABLE `%s`', (string) array_values((array) $row)[0]));
        }
    }
}
