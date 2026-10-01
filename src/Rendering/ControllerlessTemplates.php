<?php

declare(strict_types=1);

namespace hkyss\Tune\Rendering;

use Illuminate\Contracts\Config\Repository;

final class ControllerlessTemplates
{
    private const SETTINGS = 'cms.settings';

    private const SETTING = 'ControllerNamespace';

    public function __construct(
        private readonly object $processor,
        private readonly Page $page,
        private readonly Repository $config,
    ) {
    }

    public function getBladeDocumentContent(): mixed
    {
        if (!$this->looksForAMissingBaseController()) {
            return $this->forward('getBladeDocumentContent', []);
        }

        $before = $this->settings();
        $this->config->set(self::SETTINGS . '.' . self::SETTING, '');

        try {
            return $this->forward('getBladeDocumentContent', []);
        } finally {
            $this->restore($before);
        }
    }

    public function getTemplateCodeFromDB(mixed $templateID): mixed
    {
        return $this->forward('getTemplateCodeFromDB', [$templateID]);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->forward($method, $arguments);
    }

    private function looksForAMissingBaseController(): bool
    {
        $namespace = $this->page->controllerNamespace();

        return $namespace !== ''
            && $this->page->template() !== 0
            && $this->page->controller() === ''
            && !class_exists($namespace . 'BaseController');
    }

    /**
     * @param array<string, mixed> $before
     */
    private function restore(array $before): void
    {
        $settings = $this->settings();

        if (array_key_exists(self::SETTING, $before)) {
            $settings[self::SETTING] = $before[self::SETTING];
        } else {
            unset($settings[self::SETTING]);
        }

        $this->config->set(self::SETTINGS, $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = $this->config->get(self::SETTINGS, []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function forward(string $method, array $arguments): mixed
    {
        return $this->processor->{$method}(...$arguments);
    }
}
