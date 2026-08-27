<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Apply\Guard;
use hkyss\Tune\Schema\Index;
use hkyss\Tune\Tests\Unit\Fake\SchemaFake;
use PHPUnit\Framework\TestCase;

class GuardTest extends TestCase
{
    public function testAnUndoThatDropsAnIndexHoldsWhileTheIndexIsThere(): void
    {
        $guard = new Guard('tv', 'tune_tvcv_pair', true);

        $this->assertTrue($guard->holds($this->schemaWith('tune_tvcv_pair')));
        $this->assertFalse($guard->holds($this->schemaWith('ix_tvid_contentid')));
    }

    public function testAnUndoThatRecreatesAnIndexHoldsOnlyWhileItIsStillMissing(): void
    {
        $guard = new Guard('tv', 'aliasidx', false);

        $this->assertTrue($guard->holds($this->schemaWith('something_else')));
        $this->assertFalse($guard->holds($this->schemaWith('aliasidx')));
    }

    public function testItSaysWhichIndexMovedAndWhichWay(): void
    {
        $this->assertSame('tune_tvcv_pair is no longer there', (new Guard('tv', 'tune_tvcv_pair', true))->explain());
        $this->assertSame('aliasidx is already back', (new Guard('tv', 'aliasidx', false))->explain());
    }

    public function testItSurvivesTheTripThroughTheJournal(): void
    {
        $guard = new Guard('site_content', 'tune_content_children', true);

        $restored = Guard::fromArray($guard->toArray());

        $this->assertNotNull($restored);
        $this->assertSame('site_content', $restored->table);
        $this->assertSame('tune_content_children', $restored->index);
        $this->assertTrue($restored->present);
    }

    public function testARecordWrittenBeforeGuardsExistedDecodesToNone(): void
    {
        $this->assertNull(Guard::fromArray([]));
    }

    private function schemaWith(string $index): SchemaFake
    {
        return new SchemaFake(['tv' => [new Index($index, ['tmplvarid', 'contentid'], true)]]);
    }
}
