<?php

declare(strict_types=1);

namespace hkyss\Tune\Analysis;

enum Status: string
{
    case Pending = 'pending';
    case Satisfied = 'satisfied';
    case Blocked = 'blocked';
    case Absent = 'absent';

    public function isActionable(): bool
    {
        return $this === self::Pending;
    }
}
