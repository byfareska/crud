<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The texts made of the names of the resources (CrudDefaultTexts) are only what nobody translated: a translation file
 * of the application wins over them, in the default locale and in English.
 */
final class DefaultTextsTest extends PanelTestCase
{
    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function locales(): iterable
    {
        yield 'Polish by default' => ['pl', 'pl', [
            'crud.customer.title' => 'Klienci',
            'crud.customer.field.name.name' => 'Nazwa',
            'panel.section.billing' => 'Rozliczenia',
        ]];
        yield 'English of a Polish application' => ['pl', 'en', [
            'crud.customer.title' => 'Clients',
            'crud.customer.field.name.name' => 'Full name',
            'panel.section.billing' => 'Invoicing',
        ]];
        yield 'English by default' => ['en', 'en', [
            'crud.customer.title' => 'Clients',
            'crud.customer.field.name.name' => 'Full name',
            'panel.section.billing' => 'Invoicing',
        ]];
    }

    /**
     * @param array<string, string> $translated
     */
    #[DataProvider('locales')]
    public function testATranslationOfTheApplicationWins(string $default, string $locale, array $translated): void
    {
        $translator = $this->boot($default)->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        foreach ($translated as $key => $text) {
            self::assertSame($text, $translator->trans($key, locale: $locale), $key);
        }
    }

    /**
     * @param array<string, string> $translated
     */
    #[DataProvider('locales')]
    public function testWhatNobodyTranslatedIsTheDefaultText(string $default, string $locale, array $translated): void
    {
        $translator = $this->boot($default)->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertNotContains('Status', $translated);
        self::assertSame('Status', $translator->trans('crud.customer.field.status.name', locale: $locale));
        self::assertSame('New customer', $translator->trans('crud.customer.new', locale: $locale));
        self::assertSame('Contracts', $translator->trans('crud.contract.title', locale: $locale));
    }
}
