<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Translation;

use MessageFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every language of the bundle says what English says - no text missing, none left over - in messages ICU reads.
 */
final class TranslationsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        foreach (glob(dirname(__DIR__, 3) . '/translations/messages+intl-icu.*.yaml') ?: [] as $file) {
            $locale = explode('.', basename($file))[1];
            if ($locale !== 'en') {
                yield $locale => [$locale];
            }
        }
    }

    #[DataProvider('languages')]
    public function testTheTextsOfEnglish(string $locale): void
    {
        $english = array_keys(self::messages('en'));
        $own = array_keys(self::messages($locale));

        self::assertSame([], array_values(array_diff($english, $own)), 'Missing.');
        self::assertSame([], array_values(array_diff($own, $english)), 'Not in English.');
    }

    #[DataProvider('languages')]
    #[RequiresPhpExtension('intl')]
    public function testMessagesOfIcu(string $locale): void
    {
        foreach (self::messages($locale) as $key => $message) {
            self::assertInstanceOf(
                MessageFormatter::class,
                MessageFormatter::create(str_replace('_', '-', $locale), $message),
                $key,
            );
        }
    }

    /**
     * @return array<string, string> by the whole key: "file.field.upload"
     */
    private static function messages(string $locale): array
    {
        $tree = Yaml::parseFile(dirname(__DIR__, 3) . '/translations/messages+intl-icu.' . $locale . '.yaml');

        return self::flatten(is_array($tree) ? $tree : []);
    }

    /**
     * @param array<mixed> $tree
     * @return array<string, string>
     */
    private static function flatten(array $tree, string $prefix = ''): array
    {
        $messages = [];
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                $messages += self::flatten($value, $prefix . $key . '.');
            } elseif (is_scalar($value)) {
                $messages[$prefix . $key] = (string) $value;
            }
        }

        return $messages;
    }
}
