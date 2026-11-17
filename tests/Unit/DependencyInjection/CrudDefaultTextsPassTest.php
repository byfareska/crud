<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\DependencyInjection\CrudDefaultTextsPass;
use Byfareska\Crud\Translation\CrudDefaultTexts;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * The default texts are the first translation file of their locales, and the catalogues cached with them last (by
 * v0.2.0) are not read again.
 */
final class CrudDefaultTextsPassTest extends TestCase
{
    public function testTheDefaultTextsAreTheFirstFileOfTheDefaultLocaleAndOfEnglish(): void
    {
        $options = $this->processed([
            'resource_files' => [
                'pl' => ['/app/translations/messages+intl-icu.pl.yaml'],
                'de' => ['/app/translations/messages+intl-icu.de.yaml'],
            ],
            'cache_vary' => ['scanned_directories' => ['translations']],
        ]);

        self::assertSame(
            [
                'pl' => ['byfareska-crud/messages.pl.byfareska_crud', '/app/translations/messages+intl-icu.pl.yaml'],
                'de' => ['/app/translations/messages+intl-icu.de.yaml'],
                'en' => ['byfareska-crud/messages.en.byfareska_crud'],
            ],
            $options['resource_files'],
        );
        self::assertSame(
            ['scanned_directories' => ['translations'], CrudDefaultTextsPass::CACHE_VARY => 'first'],
            $options['cache_vary'],
        );
    }

    public function testNoCallOfAddResourceWhichWouldAddEveryFileBeforeThem(): void
    {
        $container = $this->container(['resource_files' => []]);
        new CrudDefaultTextsPass()->process($container);

        self::assertSame([], $container->getDefinition('translator.default')->getMethodCalls());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function processed(array $options): array
    {
        $container = $this->container($options);
        new CrudDefaultTextsPass()->process($container);
        $processed = $container->getDefinition('translator.default')->getArgument(4);
        self::assertIsArray($processed);

        /** @var array<string, mixed> $processed */
        return $processed;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function container(array $options): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.default_locale', 'pl');
        $container->setDefinition(CrudDefaultTexts::class, new Definition(CrudDefaultTexts::class));
        $container->setDefinition(
            'translator.default',
            new Definition(Translator::class, [null, null, 'pl', [], $options + ['cache_dir' => '/cache']]),
        );

        return $container;
    }
}
