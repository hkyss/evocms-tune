<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Analysis\Finding;
use hkyss\Tune\Analysis\Plan;
use hkyss\Tune\Analysis\Planner;
use hkyss\Tune\Analysis\Status;
use hkyss\Tune\Rules\Tier;
use hkyss\Tune\Schema\Index;
use hkyss\Tune\Tests\Unit\Fake\SchemaFake;
use PHPUnit\Framework\TestCase;

class PlannerTest extends TestCase
{
    public function testTheClosureTableIsFlaggedOnAStockInstallation(): void
    {
        $plan = $this->planFor($this->stockSchema());

        $this->assertSame(Status::Pending, $this->find($plan, 'closure.pair')->status);
        $this->assertSame(Status::Pending, $this->find($plan, 'closure.descendant_depth')->status);
    }

    public function testAddingTheAliasIndexMakesTheShippedOneRedundant(): void
    {
        $plan = $this->planFor($this->stockSchema());

        $dropped = $this->pendingDrops($plan, 'site_content');

        $this->assertContains('aliasidx', $dropped);
        $this->assertContains('parent', $dropped);
    }

    public function testItFindsTheRedundantPublishDatesWithoutARuleForThem(): void
    {
        $dropped = $this->pendingDrops($this->planFor($this->stockSchema()), 'site_content');

        $this->assertContains('pub', $dropped);
        $this->assertContains('pub_unpub', $dropped);
        $this->assertNotContains('unpub', $dropped);
        $this->assertNotContains('PRIMARY', $dropped);
    }

    public function testAnIndexAlreadyCoveredByAWiderOneIsSatisfiedAndCarriesNoStatement(): void
    {
        $schema = $this->stockSchema();
        $schema['site_content_closure'][] = new Index('anything', ['descendant', 'depth', 'ancestor'], false);

        $finding = $this->find($this->planFor($schema), 'closure.descendant_depth');

        $this->assertSame(Status::Satisfied, $finding->status);
        $this->assertSame([], $finding->statements);
        $this->assertSame('covered by anything', $finding->detail);
    }

    public function testAUniqueIsBlockedWhileTheDataStillHasDuplicates(): void
    {
        $plan = $this->planFor($this->stockSchema(), ['site_tmplvar_contentvalues' => 12]);

        $finding = $this->find($plan, 'tmplvar_contentvalues.pair');

        $this->assertSame(Status::Blocked, $finding->status);
        $this->assertSame([], $finding->statements);
        $this->assertStringContainsString('12 duplicate group(s)', (string) $finding->detail);
    }

    public function testTheCoreTierLeavesTheFulltextIndexesAlone(): void
    {
        $this->assertNotContains('content_ft_idx', $this->pendingDrops($this->planFor($this->stockSchema()), 'site_content'));

        $aggressive = $this->planFor($this->stockSchema(), [], Tier::Aggressive);

        $this->assertContains('content_ft_idx', $this->pendingDrops($aggressive, 'site_content'));
        $this->assertTrue($this->find($aggressive, 'site_content.fulltext')->requiresRebuild());
    }

    public function testOnlyRestrictsThePlanToTheTableItNames(): void
    {
        $plan = $this->planFor($this->stockSchema(), [], Tier::Core, ['site_content_closure']);

        $tables = array_map(static fn (Finding $f): string => $f->rule->table, $plan->findings);

        $this->assertSame(['site_content_closure'], array_values(array_unique($tables)));
    }

    public function testATableThisInstallationDoesNotHaveIsAbsentRatherThanWork(): void
    {
        $plan = $this->planFor(['site_content' => [new Index('PRIMARY', ['id'], true)]]);

        $finding = $this->find($plan, 'closure.pair');

        $this->assertSame(Status::Absent, $finding->status);
        $this->assertNotContains($finding, $plan->pending());
    }

    public function testTheStatementsCarryTheConfiguredTablePrefix(): void
    {
        $finding = $this->find($this->planFor($this->stockSchema()), 'closure.pair');

        $this->assertStringContainsString('`evo_site_content_closure`', $finding->statements[0]->sql);
    }

    public function testEveryPendingChangeCarriesTheStatementThatUndoesIt(): void
    {
        foreach ($this->planFor($this->stockSchema(), [], Tier::Aggressive)->pending() as $finding) {
            $this->assertNotSame([], $finding->undo, $finding->rule->id);
        }
    }

    public function testTheUndoOfADerivedDropRestoresTheIndexTheSchemaActuallyHad(): void
    {
        $plan = $this->planFor($this->stockSchema());

        $finding = $this->find($plan, 'redundant.site_content.aliasidx');

        $this->assertStringContainsString('DROP INDEX `aliasidx`', $finding->statements[0]->sql);
        $this->assertStringContainsString('ADD INDEX `aliasidx` (`alias`)', $finding->undo[0]->sql);
    }

    public function testTheUndoOfTheUniqueConversionPutsTheOriginalIndexBack(): void
    {
        $finding = $this->find($this->planFor($this->stockSchema()), 'tmplvar_contentvalues.pair');

        $this->assertStringContainsString('DROP INDEX `tune_tvcv_pair`', $finding->undo[0]->sql);
        $this->assertStringContainsString('ADD INDEX `idx_tmplvarid_contentid`', $finding->undo[0]->sql);
    }

    public function testTheUndoOfDroppingAFulltextIndexIsNotOnline(): void
    {
        $finding = $this->find($this->planFor($this->stockSchema(), [], Tier::Aggressive), 'site_content.fulltext');

        $this->assertStringContainsString('ADD FULLTEXT INDEX `content_ft_idx`', $finding->undo[0]->sql);
        $this->assertFalse($finding->undo[0]->online);
    }

    /**
     * @param array<string, list<Index>> $schema
     * @param array<string, int>         $duplicates
     * @param list<string>               $only
     */
    private function planFor(array $schema, array $duplicates = [], Tier $tier = Tier::Core, array $only = []): Plan
    {
        return (new Planner(new SchemaFake($schema, $duplicates)))->plan($tier, $only);
    }

    private function find(Plan $plan, string $id): Finding
    {
        foreach ($plan->findings as $finding) {
            if ($finding->rule->id === $id) {
                return $finding;
            }
        }

        $this->fail(sprintf('No finding for rule %s', $id));
    }

    /** @return list<string> */
    private function pendingDrops(Plan $plan, string $table): array
    {
        $dropped = [];

        foreach ($plan->pending() as $finding) {
            if ($finding->rule->table === $table && $finding->rule->columns === []) {
                $dropped[] = $finding->rule->index;
            }
        }

        return $dropped;
    }

    /** @return array<string, list<Index>> */
    private function stockSchema(): array
    {
        return [
            'site_content' => [
                new Index('PRIMARY', ['id'], true),
                new Index('typeidx', ['type'], false),
                new Index('aliasidx', ['alias'], false),
                new Index('parent', ['parent'], false),
                new Index('pub_unpub_published', ['pub_date', 'unpub_date', 'published'], false),
                new Index('pub_unpub', ['pub_date', 'unpub_date'], false),
                new Index('unpub', ['unpub_date'], false),
                new Index('pub', ['pub_date'], false),
                new Index('content_ft_idx', ['pagetitle', 'description', 'content'], false, Index::FULLTEXT),
            ],
            'site_content_closure' => [
                new Index('PRIMARY', ['closure_id'], true),
            ],
            'site_tmplvar_contentvalues' => [
                new Index('PRIMARY', ['id'], true),
                new Index('idx_tmplvarid_contentid', ['tmplvarid', 'contentid'], false),
                new Index('idx_contentid', ['contentid'], false),
            ],
        ];
    }
}
