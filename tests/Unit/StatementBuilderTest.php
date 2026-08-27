<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Apply\StatementBuilder;
use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\Index;
use PHPUnit\Framework\TestCase;

class StatementBuilderTest extends TestCase
{
    public function testItPrefixesTheTableAndAsksForAnOnlineChange(): void
    {
        $rule = Rule::addIndex('x', 'site_content', 'tune_parent', ['parent', 'menuindex'], Tier::Core, 'because');

        $statements = (new StatementBuilder('evo_'))->forRule($rule);

        $this->assertCount(1, $statements);
        $this->assertSame(
            'ALTER TABLE `evo_site_content` ADD INDEX `tune_parent` (`parent`, `menuindex`), ALGORITHM=INPLACE, LOCK=NONE',
            $statements[0]->sql
        );
        $this->assertTrue($statements[0]->online);
    }

    public function testItKeepsAColumnPrefixLengthOutsideTheQuotes(): void
    {
        $rule = Rule::addIndex('x', 'tv', 'i', ['tmplvarid', 'value(50)'], Tier::Core, 'because');

        $this->assertStringContainsString('(`tmplvarid`, `value`(50))', (new StatementBuilder())->forRule($rule)[0]->sql);
    }

    public function testReplacingAnIndexWithAUniqueHappensInOneStatement(): void
    {
        $rule = Rule::addUnique('x', 'tv', 'tune_pair', ['tmplvarid', 'contentid'], Tier::Core, 'because', 'old_pair');

        $sql = (new StatementBuilder('evo_'))->forRule($rule)[0]->sql;

        $this->assertSame(
            'ALTER TABLE `evo_tv` DROP INDEX `old_pair`, ADD UNIQUE INDEX `tune_pair` (`tmplvarid`, `contentid`),'
            . ' ALGORITHM=INPLACE, LOCK=NONE',
            $sql
        );
    }

    public function testARebuildingChangeIsNotMarkedOnlineAndCarriesNoAlgorithmClause(): void
    {
        $rule = Rule::dropIndex('x', 'site_content', 'content_ft_idx', Tier::Aggressive, 'because', true);

        $statements = (new StatementBuilder('evo_'))->forRule($rule);

        $this->assertFalse($statements[0]->online);
        $this->assertStringNotContainsString('ALGORITHM', $statements[0]->sql);
    }

    public function testAServerWithoutOnlineDdlNeverGetsTheAlgorithmClause(): void
    {
        $rule = Rule::addIndex('x', 'site_content', 'i', ['parent'], Tier::Core, 'because');

        $statements = (new StatementBuilder('evo_', false))->forRule($rule);

        $this->assertFalse($statements[0]->online);
        $this->assertStringNotContainsString('ALGORITHM', $statements[0]->sql);
    }

    public function testTheUndoOfAnAdditionPutsTheReplacedIndexBackAsItWas(): void
    {
        $rule = Rule::addUnique('x', 'tv', 'tune_pair', ['tmplvarid', 'contentid'], Tier::Core, 'because', 'old_pair');
        $replaced = new Index('old_pair', ['tmplvarid', 'contentid'], false);

        $sql = (new StatementBuilder('evo_'))->undoOfAddition($rule, $replaced)[0]->sql;

        $this->assertSame(
            'ALTER TABLE `evo_tv` DROP INDEX `tune_pair`, ADD INDEX `old_pair` (`tmplvarid`, `contentid`),'
            . ' ALGORITHM=INPLACE, LOCK=NONE',
            $sql
        );
    }

    public function testTheUndoOfAPlainAdditionOnlyDropsWhatItAdded(): void
    {
        $rule = Rule::addIndex('x', 'site_content', 'tune_children', ['parent', 'menuindex'], Tier::Core, 'because');

        $statements = (new StatementBuilder('evo_'))->undoOfAddition($rule, null);

        $this->assertSame(
            'ALTER TABLE `evo_site_content` DROP INDEX `tune_children`, ALGORITHM=INPLACE, LOCK=NONE',
            $statements[0]->sql
        );
    }

    public function testTheUndoOfADropRebuildsTheIndexExactlyAsItWas(): void
    {
        $dropped = new Index('idx_value_prefix', ['tmplvarid', 'value(50)'], false);

        $sql = (new StatementBuilder('evo_'))->undoOfDrop('tv', $dropped)[0]->sql;

        $this->assertSame(
            'ALTER TABLE `evo_tv` ADD INDEX `idx_value_prefix` (`tmplvarid`, `value`(50)),'
            . ' ALGORITHM=INPLACE, LOCK=NONE',
            $sql
        );
    }

    public function testTheUndoOfADropKeepsAUniqueUnique(): void
    {
        $dropped = new Index('ix_dg_id', ['document_group', 'document'], true);

        $sql = (new StatementBuilder('evo_'))->undoOfDrop('document_groups', $dropped)[0]->sql;

        $this->assertStringContainsString('ADD UNIQUE INDEX `ix_dg_id`', $sql);
    }

    public function testPuttingAFulltextIndexBackIsNotAnOnlineChange(): void
    {
        $dropped = new Index('content_ft_idx', ['pagetitle', 'content'], false, Index::FULLTEXT);

        $statements = (new StatementBuilder('evo_'))->undoOfDrop('site_content', $dropped);

        $this->assertFalse($statements[0]->online);
        $this->assertSame(
            'ALTER TABLE `evo_site_content` ADD FULLTEXT INDEX `content_ft_idx` (`pagetitle`, `content`)',
            $statements[0]->sql
        );
    }
}
