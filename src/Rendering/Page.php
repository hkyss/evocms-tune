<?php

declare(strict_types=1);

namespace hkyss\Tune\Rendering;

interface Page
{
    public function template(): int;

    public function controller(): string;

    public function controllerNamespace(): string;
}
