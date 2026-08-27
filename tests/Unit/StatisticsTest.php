<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Apply\Statement;
use hkyss\Tune\Apply\Statistics;
use PHPUnit\Framework\TestCase;

class StatisticsTest extends TestCase
{
    public function testATableTouchedTwiceIsReadOnce(): void
    {
        $tables = Statistics::tablesOf([
            new Statement('...', true, 'evo_site_content'),
            new Statement('...', true, 'evo_site_content'),
            new Statement('...', true, 'evo_site_content_closure'),
        ]);

        $this->assertSame(['evo_site_content', 'evo_site_content_closure'], $tables);
    }

    public function testItAsksForEveryTableInOneStatement(): void
    {
        $this->assertSame(
            'ANALYZE TABLE `evo_site_content`, `evo_site_content_closure`',
            Statistics::statement(['evo_site_content', 'evo_site_content_closure'])
        );
    }

    public function testTableNamesAreQuotedTheWayEverythingElseQuotesThem(): void
    {
        $this->assertSame('ANALYZE TABLE `odd``name`', Statistics::statement(['odd`name']));
    }

    public function testNothingTouchedIsNothingToRead(): void
    {
        $this->assertSame([], Statistics::tablesOf([]));
    }
}
