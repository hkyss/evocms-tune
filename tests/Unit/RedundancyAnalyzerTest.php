<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Analysis\RedundancyAnalyzer;
use hkyss\Tune\Schema\Index;
use PHPUnit\Framework\TestCase;

class RedundancyAnalyzerTest extends TestCase
{
    public function testAnIndexIsRedundantWhenTheWiderOneContinuesWithThePrimaryKey(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('parent', ['parent'], false),
                new Index('parent_id_template', ['parent', 'id', 'template'], false),
            ],
        ]);

        $this->assertSame(['parent'], array_map(static fn ($rule): string => $rule->index, $rules));
    }

    public function testAnIndexIsNotRedundantWhenTheWiderOneOrdersItsRowsByAnotherColumn(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('parent', ['parent'], false),
                new Index('children', ['parent', 'deleted', 'published', 'menuindex'], false),
                new Index('pub', ['pub_date'], false),
                new Index('pub_unpub', ['pub_date', 'unpub_date'], false),
                new Index('pub_unpub_published', ['pub_date', 'unpub_date', 'published'], false),
            ],
        ]);

        $this->assertSame([], $rules);
    }

    public function testAnIndexEndingInThePrimaryKeyIsRedundantAgainstAWiderOneWithTheSameStart(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('alias_id', ['alias', 'id'], false),
                new Index('alias_id_parent', ['alias', 'id', 'parent'], false),
            ],
        ]);

        $this->assertSame(['alias_id'], array_map(static fn ($rule): string => $rule->index, $rules));
    }

    public function testWithoutAPrimaryKeyOnlyAnIdenticalIndexIsRedundant(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('parent', ['parent'], false),
                new Index('parent_template', ['parent', 'template'], false),
            ],
        ]);

        $this->assertSame([], $rules);
    }

    public function testFulltextIndexesAreNeverRedundant(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('ft_a', ['pagetitle'], false, Index::FULLTEXT),
                new Index('ft_b', ['pagetitle'], false, Index::FULLTEXT),
                new Index('ft_wide', ['pagetitle', 'content'], false, Index::FULLTEXT),
            ],
        ]);

        $this->assertSame([], $rules);
    }

    public function testDescendingOrFunctionalIndexesAreNeitherRedundantNorCovering(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('parent', ['parent'], false),
                new Index('parent_desc', ['parent'], false, ascendingColumns: false),
                new Index('expression', [''], false, ascendingColumns: false),
                new Index('expression_too', [''], false, ascendingColumns: false),
            ],
        ]);

        $this->assertSame([], $rules);
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
                new Index('PRIMARY', ['id'], true),
                new Index('ix_dg_id', ['document_group', 'document'], true),
                new Index('dg_document', ['document_group', 'document'], false),
            ],
        ]);

        $this->assertCount(1, $rules);
        $this->assertSame('dg_document', $rules[0]->index);
    }

    public function testANonUniqueIsNotRedundantAgainstAWiderUnique(): void
    {
        $rules = (new RedundancyAnalyzer())->rulesFor([
            'document_groups' => [
                new Index('PRIMARY', ['id'], true),
                new Index('ix_dg_id', ['document_group', 'document'], true),
                new Index('document_group', ['document_group'], false),
            ],
        ]);

        $this->assertSame([], $rules);
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
                new Index('PRIMARY', ['id'], true),
                new Index('pub', ['pub_date'], false),
                new Index('pub_id_unpub', ['pub_date', 'id', 'unpub_date'], false),
            ],
        ]);

        $this->assertStringContainsString('pub_id_unpub', $rules[0]->reason);
    }
}
