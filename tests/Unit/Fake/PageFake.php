<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit\Fake;

use hkyss\Tune\Rendering\Page;

final class PageFake implements Page
{
    public function __construct(
        private readonly int $template,
        private readonly string $controller,
        private readonly string $controllerNamespace,
    ) {
    }

    public function template(): int
    {
        return $this->template;
    }

    public function controller(): string
    {
        return $this->controller;
    }

    public function controllerNamespace(): string
    {
        return $this->controllerNamespace;
    }
}
