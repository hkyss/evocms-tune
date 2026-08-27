<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Analysis\RedundancyAnalyzer;
use hkyss\Tune\Schema\Index;
use PHPUnit\Framework\TestCase;

class RedundancyAnalyzerTest extends TestCase
{
    public function testItProposesDroppingAnIndexThatIsTheLeadingPartOfAnother(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('pub', ['pub_date'], false),
                new Index('pub_unpub', ['pub_date', 'unpub_date'], false),
                new Index('pub_unpub_published', ['pub_date', 'unpub_date', 'published'], false),
                new Index('unpub', ['unpub_date'], false),
            ],
        ]);

        $dropped = array_map(static fn ($rule): string => $rule->index, $rules);

        $this->assertSame(['pub', 'pub_unpub'], $dropped);
    }

    public function testItNeverProposesDroppingThePrimaryKey(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('id_created', ['id', 'createdon'], false),
            ],
        ]);

        $this->assertSame([], $rules);
    }

    public function testAUniqueIsNotRedundantAgainstAWiderNonUniqueIndex(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'user_values' => [
                new Index('pair', ['tmplvarid', 'userid'], true),
                new Index('wide', ['tmplvarid', 'userid', 'value(50)'], false),
            ],
        ]);

        $this->assertSame([], $rules);
    }

    public function testANonUniqueIsRedundantAgainstAUniqueOverTheSameColumns(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'document_groups' => [
                new Index('ix_dg_id', ['document_group', 'document'], true),
                new Index('document_group', ['document_group'], false),
            ],
        ]);

        $this->assertCount(1, $rules);
        $this->assertSame('document_group', $rules[0]->index);
    }

    public function testTwoIdenticalIndexesLeaveOneStanding(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('parent_a', ['parent'], false),
                new Index('parent_b', ['parent'], false),
            ],
        ]);

        $this->assertCount(1, $rules);
        $this->assertSame('parent_b', $rules[0]->index);
    }

    public function testTheReasonNamesTheIndexThatMakesItRedundant(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('pub', ['pub_date'], false),
                new Index('pub_unpub', ['pub_date', 'unpub_date'], false),
            ],
        ]);

        $this->assertStringContainsString('pub_unpub', $rules[0]->reason);
    }
}
