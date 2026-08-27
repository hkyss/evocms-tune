<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Schema\Index;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    public function testAShorterIndexIsAPrefixOfALongerOneWithTheSameLeadingColumns(): void
    {
        $short = new Index('a', ['tmplvarid', 'contentid'], false);
        $long = new Index('b', ['tmplvarid', 'contentid', 'value(50)'], false);

        $this->assertTrue($short->isPrefixOf($long));
        $this->assertFalse($long->isPrefixOf($short));
    }

    public function testColumnsThatDoNotLeadTheSameWayAreNotAPrefix(): void
    {
        $one = new Index('a', ['tmplvarid', 'value(50)'], false);
        $other = new Index('b', ['tmplvarid', 'contentid', 'value(50)'], false);

        $this->assertFalse($one->isPrefixOf($other));
    }

    public function testIndexesOfDifferentTypesAreNeverPrefixesOfEachOther(): void
    {
        $btree = new Index('a', ['value'], false);
        $fulltext = new Index('b', ['value'], false, Index::FULLTEXT);

        $this->assertFalse($btree->isPrefixOf($fulltext));
        $this->assertFalse($fulltext->isPrefixOf($btree));
    }

    public function testAnIndexIsAPrefixOfItsOwnColumnSet(): void
    {
        $index = new Index('a', ['parent'], false);

        $this->assertTrue($index->isPrefixOf(new Index('b', ['parent'], false)));
        $this->assertTrue($index->coversSameColumnsAs(new Index('b', ['parent'], false)));
    }
}
