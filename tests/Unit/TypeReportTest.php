<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Analysis\TypeReport;
use hkyss\Tune\Tests\Unit\Fake\SchemaFake;
use PHPUnit\Framework\TestCase;

class TypeReportTest extends TestCase
{
    public function testItReportsAColumnThatCannotPointAtWhatItPointsAt(): void
    {
        $found = (new TypeReport())->against($this->schema([
            'site_content' => ['id' => 'int unsigned', 'parent' => 'int'],
        ]));

        $this->assertCount(1, $found);
        $this->assertSame('site_content', $found[0]->table);
        $this->assertSame('parent', $found[0]->column);
        $this->assertSame(
            'site_content.parent is int, and site_content.id it points at is int unsigned',
            $found[0]->describe()
        );
    }

    public function testColumnsOfTheSameTypeAreNotReported(): void
    {
        $found = (new TypeReport())->against($this->schema([
            'site_content' => ['id' => 'int unsigned', 'parent' => 'int unsigned'],
        ]));

        $this->assertSame([], $found);
    }

    public function testAColumnThisInstallationDoesNotHaveIsSkipped(): void
    {
        $this->assertSame([], (new TypeReport())->against($this->schema(['site_content' => ['id' => 'int']])));
    }

    public function testItLooksAcrossTablesAsWellAsWithinOne(): void
    {
        $found = (new TypeReport())->against($this->schema([
            'site_content' => ['id' => 'int unsigned'],
            'site_tmplvar_contentvalues' => ['contentid' => 'int'],
        ]));

        $this->assertCount(1, $found);
        $this->assertSame('site_tmplvar_contentvalues', $found[0]->table);
        $this->assertSame('site_content.id', $found[0]->target);
    }

    /** @param array<string, array<string, string>> $columns */
    private function schema(array $columns): SchemaFake
    {
        return new SchemaFake([], [], 'evo_', $columns);
    }
}
