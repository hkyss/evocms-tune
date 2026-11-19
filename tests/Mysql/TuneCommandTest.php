<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Mysql;

use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Rules\Tier;

class TuneCommandTest extends MysqlTestCase
{
    public function testADropThatReliesOnAnAdditionRunsOnceTheAdditionIsInPlace(): void
    {
        $this->createContent('KEY `parent` (`parent`)');

        $tester = $this->artisan($this->tuneAddingWide(), ['--force' => true, '--no-analyze' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(['PRIMARY', 'wide'], $this->indexesOf('site_content'));
        $this->assertSame(2, $this->connection->table('tune_changes')->count());
    }

    public function testADropThatReliesOnAFailedAdditionDoesNotRun(): void
    {
        $this->createContent('KEY `parent` (`parent`), KEY `wide` (`template`)');

        $tester = $this->artisan($this->tuneAddingWide(), ['--force' => true, '--no-analyze' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('fail', $tester->getDisplay());
        $this->assertStringContainsString('skip site_content.drop index parent', $tester->getDisplay());
        $this->assertSame(['PRIMARY', 'parent', 'wide'], $this->indexesOf('site_content'));
        $this->assertFalse($this->hasTable('tune_changes'));
    }

    public function testAChangeThatCannotBeRecordedIsNotApplied(): void
    {
        $this->createContent('KEY `parent` (`parent`)');
        $this->connection->statement('CREATE VIEW `evo_tune_changes` AS SELECT 1 AS `id`');

        $tester = $this->artisan($this->tuneAddingWide(), ['--force' => true, '--no-analyze' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not recorded, so not applied', $tester->getDisplay());
        $this->assertSame(['PRIMARY', 'parent'], $this->indexesOf('site_content'));
    }

    public function testADryRunStillShowsTheDropTheAdditionWillAllow(): void
    {
        $this->createContent('KEY `parent` (`parent`)');

        $tester = $this->artisan($this->tuneAddingWide(), ['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DROP INDEX `parent`', $tester->getDisplay());
        $this->assertSame(['PRIMARY', 'parent'], $this->indexesOf('site_content'));
    }

    private function tuneAddingWide(): TuneWithRules
    {
        return new TuneWithRules([
            Rule::addIndex('content.wide', 'site_content', 'wide', ['parent', 'id', 'template'], Tier::Core, 'Reads children in id order.'),
        ]);
    }

    private function createContent(string $keys): void
    {
        $this->connection->statement(
            'CREATE TABLE `evo_site_content` (`id` int unsigned NOT NULL AUTO_INCREMENT, `parent` int NOT NULL,'
            . ' `template` int NOT NULL, PRIMARY KEY (`id`), ' . $keys . ') ENGINE=InnoDB'
        );
    }
}
