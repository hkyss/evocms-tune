<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Mysql;

use hkyss\Tune\Apply\StatementBuilder;
use hkyss\Tune\Console\Commands\UntuneCommand;
use hkyss\Tune\Record\Journal;
use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\Index;
use hkyss\Tune\Schema\SchemaReader;

class UntuneCommandTest extends MysqlTestCase
{
    public function testARecordedAdditionThatNeverRanIsDroppedAsStale(): void
    {
        $rule = Rule::addIndex('content.wide', 'site_content', 'wide', ['parent', 'id'], Tier::Core, 'test');
        $builder = new StatementBuilder('evo_');
        $this->journal()->record($rule, ['ALTER TABLE ...'], $builder->undoOfAddition($rule, null));

        $tester = $this->artisan(new UntuneCommand(), ['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('stale', $tester->getDisplay());
        $this->assertSame(['PRIMARY', 'parent'], $this->indexesOf('site_content'));
        $this->assertFalse($this->hasTable('tune_changes'));
    }

    public function testARecordedDropThatNeverRanIsDroppedAsStale(): void
    {
        $rule = Rule::dropIndex('content.parent', 'site_content', 'parent', Tier::Core, 'test');
        $builder = new StatementBuilder('evo_');
        $this->journal()->record($rule, ['ALTER TABLE ...'], $builder->undoOfDrop('site_content', new Index('parent', ['parent'], false)));

        $tester = $this->artisan(new UntuneCommand(), ['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('stale', $tester->getDisplay());
        $this->assertSame(['PRIMARY', 'parent'], $this->indexesOf('site_content'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->statement(
            'CREATE TABLE `evo_site_content` (`id` int unsigned NOT NULL AUTO_INCREMENT, `parent` int NOT NULL,'
            . ' PRIMARY KEY (`id`), KEY `parent` (`parent`)) ENGINE=InnoDB'
        );
    }

    private function journal(): Journal
    {
        return new Journal($this->connection, new SchemaReader($this->connection));
    }
}
