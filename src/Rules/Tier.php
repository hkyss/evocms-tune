<?php

declare(strict_types=1);

namespace hkyss\Tune\Rules;

enum Tier: string
{
    case Core = 'core';
    case Extended = 'extended';
    case Aggressive = 'aggressive';

    public function weight(): int
    {
        return match ($this) {
            self::Core => 0,
            self::Extended => 1,
            self::Aggressive => 2,
        };
    }

    public function includes(self $other): bool
    {
        return $other->weight() <= $this->weight();
    }
}
