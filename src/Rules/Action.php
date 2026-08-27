<?php

declare(strict_types=1);

namespace hkyss\Tune\Rules;

enum Action: string
{
    case AddIndex = 'add-index';
    case AddUnique = 'add-unique';
    case DropIndex = 'drop-index';

    public function isAdditive(): bool
    {
        return $this !== self::DropIndex;
    }
}
