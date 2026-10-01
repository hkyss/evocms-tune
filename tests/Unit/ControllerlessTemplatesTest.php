<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit;

use hkyss\Tune\Rendering\ControllerlessTemplates;
use hkyss\Tune\Tests\Unit\Fake\ConfigFake;
use hkyss\Tune\Tests\Unit\Fake\PageFake;
use hkyss\Tune\Tests\Unit\Fake\ProcessorFake;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ControllerlessTemplatesTest extends TestCase
{
    private const WITHOUT_BASE = 'hkyss\\Tune\\Tests\\Unit\\Fake\\Missing\\';

    private const WITH_BASE = 'hkyss\\Tune\\Tests\\Unit\\Fake\\Site\\';

    public function testATemplateWithoutAControllerRendersPastANamespaceThatHasNoBaseController(): void
    {
        $config = $this->configNaming(self::WITHOUT_BASE);
        $processor = new ProcessorFake($config);

        $template = $this->templates($processor, new PageFake(22, '', self::WITHOUT_BASE), $config)
            ->getBladeDocumentContent();

        $this->assertSame('templates.productreviews', $template);
        $this->assertSame([''], $processor->namespaces);
        $this->assertSame(self::WITHOUT_BASE, $config->get('cms.settings.ControllerNamespace'));
    }

    public function testATemplateThatNamesItsControllerKeepsTheNamespace(): void
    {
        $this->assertTheProcessorSees(self::WITHOUT_BASE, new PageFake(19, 'Templates\\FaqController', self::WITHOUT_BASE));
    }

    public function testANamespaceThatHasABaseControllerKeepsIt(): void
    {
        $this->assertTheProcessorSees(self::WITH_BASE, new PageFake(22, '', self::WITH_BASE));
    }

    public function testADocumentWithoutATemplateKeepsTheNamespaceItsBlankControllerIsLookedUpIn(): void
    {
        $this->assertTheProcessorSees(self::WITHOUT_BASE, new PageFake(0, '', self::WITHOUT_BASE));
    }

    public function testASiteThatNamesNoNamespaceIsLeftAsItIs(): void
    {
        $config = new ConfigFake(['cms' => ['settings' => []]]);
        $processor = new ProcessorFake($config);

        $this->templates($processor, new PageFake(22, '', ''), $config)->getBladeDocumentContent();

        $this->assertSame([null], $processor->namespaces);
        $this->assertSame(['cms' => ['settings' => []]], $config->all());
    }

    public function testTheNamespaceComesBackWhenTheRenderFails(): void
    {
        $config = $this->configNaming(self::WITHOUT_BASE);
        $templates = $this->templates(
            new ProcessorFake($config, fails: true),
            new PageFake(22, '', self::WITHOUT_BASE),
            $config
        );

        try {
            $templates->getBladeDocumentContent();
            $this->fail('The render was expected to fail.');
        } catch (RuntimeException) {
            $this->assertSame(self::WITHOUT_BASE, $config->get('cms.settings.ControllerNamespace'));
        }
    }

    public function testANamespaceTheSiteKeepsInItsSettingsTableIsNotLeftInTheConfig(): void
    {
        $config = new ConfigFake(['cms' => ['settings' => ['site_name' => 'Shop']]]);
        $processor = new ProcessorFake($config);

        $this->templates($processor, new PageFake(22, '', self::WITHOUT_BASE), $config)->getBladeDocumentContent();

        $this->assertSame([''], $processor->namespaces);
        $this->assertSame(['site_name' => 'Shop'], $config->get('cms.settings'));
    }

    public function testEveryOtherCallReachesTheProcessor(): void
    {
        $config = $this->configNaming(self::WITHOUT_BASE);
        $templates = $this->templates(new ProcessorFake($config), new PageFake(22, '', self::WITHOUT_BASE), $config);

        $this->assertSame('the code of template 22', $templates->getTemplateCodeFromDB(22));
    }

    private function assertTheProcessorSees(string $namespace, PageFake $page): void
    {
        $config = $this->configNaming($page->controllerNamespace());
        $processor = new ProcessorFake($config);

        $this->templates($processor, $page, $config)->getBladeDocumentContent();

        $this->assertSame([$namespace], $processor->namespaces);
    }

    private function configNaming(string $namespace): ConfigFake
    {
        return new ConfigFake(['cms' => ['settings' => ['ControllerNamespace' => $namespace]]]);
    }

    private function templates(ProcessorFake $processor, PageFake $page, ConfigFake $config): ControllerlessTemplates
    {
        return new ControllerlessTemplates($processor, $page, $config);
    }
}
