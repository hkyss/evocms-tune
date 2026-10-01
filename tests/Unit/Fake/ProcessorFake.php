<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit\Fake;

use RuntimeException;

final class ProcessorFake
{
    /** @var list<mixed> */
    public array $namespaces = [];

    public function __construct(
        private readonly ConfigFake $config,
        private readonly bool $fails = false,
    ) {
    }

    public function getBladeDocumentContent(): string
    {
        $this->namespaces[] = $this->config->get('cms.settings.ControllerNamespace');

        if ($this->fails) {
            throw new RuntimeException('the view is gone');
        }

        return 'templates.productreviews';
    }

    public function getTemplateCodeFromDB(int $template): string
    {
        return "the code of template {$template}";
    }
}
