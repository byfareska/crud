<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrowserFilesTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function names(): iterable
    {
        yield 'words' => ['Kot na płocie.jpg', 'Kot na plocie'];
        yield 'signs' => ['<b>Logo</b> "firmy" & co. (2).PNG', 'b Logo b firmy co 2'];
        yield 'a slash' => ['A/B testy.webp', 'A B testy'];
        yield 'underscores' => ['IMG_2026_10_04.jpeg', 'IMG 2026 10 04'];
        yield 'no extension' => ['README', 'README'];
        yield 'only signs' => ['!!!.png', ''];
    }

    #[DataProvider('names')]
    public function testTheAlternativeTextOfAFileIsItsNameMadePlain(string $name, string $alt): void
    {
        $json = new FilesKit()->browser->json($this->file($name));

        self::assertSame($alt, $json['alt']);
    }

    public function testNoAlternativeTextOfARandomName(): void
    {
        self::assertSame('', new FilesKit()->browser->alt($this->file('x7k2abcd.png')));
    }

    public function testALongNameIsCut(): void
    {
        $alt = new FilesKit()->browser->alt($this->file(str_repeat('ab ', 100) . '.jpg'));

        self::assertLessThanOrEqual(125, mb_strlen($alt));
        self::assertSame($alt, trim($alt));
    }

    public function testAFileABrowserWouldRunIsOpenedFromThePanelOnlyAtItsSignedDownload(): void
    {
        $browser = new FilesKit(['ROLE_ADMIN'])->browser;
        $svg = new StoredFile(FileReference::parse('public://ab/logo.svg'), 'logo.svg', 'image/svg+xml');
        $html = new StoredFile(FileReference::parse('public://ab/page.html'), 'page.html');
        $private = new StoredFile(FileReference::parse('private://ab/logo.svg'), 'logo.svg', 'image/svg+xml');

        $json = $browser->json($svg);

        self::assertSame(['/uploads/ab/logo.svg', null], [$json['url'], $json['link']], 'Shown as an image only.');
        self::assertNull($browser->link($html));
        self::assertStringContainsString('/admin/_files/download?', (string) $browser->link($private));
        self::assertSame('/uploads/ab/cd/x7k2abcd.png', $browser->json($this->file('a.png'))['link']);
    }

    private function file(string $name): StoredFile
    {
        return new StoredFile(new FileReference('public', 'ab/cd/x7k2abcd.png'), $name, 'image/png', 4);
    }
}
