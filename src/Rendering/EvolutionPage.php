<?php

declare(strict_types=1);

namespace hkyss\Tune\Rendering;

use EvolutionCMS\Core;

final class EvolutionPage implements Page
{
    public function __construct(
        private readonly Core $evolution,
    ) {
    }

    public function template(): int
    {
        return (int) ($this->evolution->documentObject['template'] ?? 0);
    }

    public function controller(): string
    {
        return trim((string) ($this->evolution->documentObject['templatecontroller'] ?? ''));
    }

    public function controllerNamespace(): string
    {
        return trim((string) $this->evolution->getConfig('ControllerNamespace'));
    }
}
