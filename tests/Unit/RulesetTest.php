<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Rules\Action;
use hkyss\Tune\Rules\Rule;
use hkyss\Tune\Rules\Ruleset;
use hkyss\Tune\Rules\Tier;
use PHPUnit\Framework\TestCase;

class RulesetTest extends TestCase
{
    public function testEveryRuleHasItsOwnId(): void
    {
        $ids = array_map(static fn (Rule $rule): string => $rule->id, Ruleset::evolutionCore());

        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    public function testEveryRuleExplainsItselfToTheOperator(): void
    {
        foreach (Ruleset::evolutionCore() as $rule) {
            $this->assertGreaterThan(40, strlen($rule->reason), $rule->id . ' needs a reason worth reading');
            $this->assertStringEndsWith('.', $rule->reason, $rule->id . ' should read as a sentence');
        }
    }

    public function testAdditiveRulesNameColumnsAndDropsDoNot(): void
    {
        foreach (Ruleset::evolutionCore() as $rule) {
            if ($rule->action === Action::DropIndex) {
                $this->assertSame([], $rule->columns, $rule->id);

                continue;
            }

            $this->assertNotSame([], $rule->columns, $rule->id);
        }
    }

    public function testOnlyAggressiveRulesRebuildATable(): void
    {
        foreach (Ruleset::evolutionCore() as $rule) {
            if ($rule->rebuild) {
                $this->assertSame(Tier::Aggressive, $rule->tier, $rule->id);
            }
        }
    }

    public function testTheCoreTierNeverRebuildsATable(): void
    {
        foreach (Ruleset::evolutionCore() as $rule) {
            if ($rule->tier === Tier::Core) {
                $this->assertFalse($rule->rebuild, $rule->id);
            }
        }
    }

    public function testATierIncludesEveryTierBelowIt(): void
    {
        $this->assertTrue(Tier::Aggressive->includes(Tier::Core));
        $this->assertTrue(Tier::Extended->includes(Tier::Core));
        $this->assertFalse(Tier::Core->includes(Tier::Extended));
    }
}
