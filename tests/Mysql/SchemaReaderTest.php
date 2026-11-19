<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Mysql;

use hkyss\Tune\Analysis\RedundancyAnalyzer;
use hkyss\Tune\Schema\Index;
use hkyss\Tune\Schema\SchemaReader;

class SchemaReaderTest extends MysqlTestCase
{
    public function testDescendingAndFunctionalKeyPartsAreReadAsSuch(): void
    {
        $this->createContent();

        $reader = new SchemaReader($this->connection);

        $this->assertTrue($this->index($reader, 'parent')->isComparableByColumns());
        $this->assertFalse($this->index($reader, 'parent_desc')->isComparableByColumns());
        $this->assertFalse($this->index($reader, 'parent_expression')->isComparableByColumns());
        $this->assertFalse($this->index($reader, 'title_ft')->isComparableByColumns());
    }

    public function testNothingIsRedundantAmongThemOnALiveSchema(): void
    {
        $this->createContent();

        $this->assertSame([], (new RedundancyAnalyzer())->rulesFor((new SchemaReader($this->connection))->all()));
    }

    private function createContent(): void
    {
        $this->connection->statement(
            'CREATE TABLE `evo_site_content` (`id` int unsigned NOT NULL AUTO_INCREMENT, `parent` int NOT NULL,'
            . ' `pagetitle` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `parent` (`parent`),'
            . ' KEY `parent_desc` (`parent` DESC, `id`), KEY `parent_expression` ((`parent` + 0), `id`),'
            . ' FULLTEXT KEY `title_ft` (`pagetitle`), FULLTEXT KEY `title_ft_again` (`pagetitle`)) ENGINE=InnoDB'
        );
    }

    private function index(SchemaReader $reader, string $name): Index
    {
        $index = $reader->indexNamed('site_content', $name);
        $this->assertNotNull($index);

        return $index;
    }
}
