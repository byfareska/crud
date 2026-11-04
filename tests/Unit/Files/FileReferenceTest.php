<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileReferenceTest extends TestCase
{
    public function testAReferenceIsTheStorageAndThePath(): void
    {
        $reference = FileReference::parse('public://ab/cd/abcd0123.jpg');

        self::assertSame('public', $reference->storage);
        self::assertSame('ab/cd/abcd0123.jpg', $reference->path);
        self::assertSame('public://ab/cd/abcd0123.jpg', (string) $reference);
        self::assertSame('abcd0123.jpg', $reference->basename());
        self::assertSame('jpg', $reference->extension());
        self::assertFalse($reference->isExternal());
        self::assertTrue($reference->equals(new FileReference('public', 'ab/cd/abcd0123.jpg')));
    }

    public function testAFileOfAnotherSiteIsItsAddress(): void
    {
        $reference = FileReference::parse('https://cdn.example.com/media/Photo.JPEG?w=200');

        self::assertTrue($reference->isExternal());
        self::assertSame('Photo.JPEG', $reference->basename());
        self::assertSame('jpeg', $reference->extension());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notReferences(): iterable
    {
        yield 'no scheme' => ['ab/cd/x.jpg'];
        yield 'upper-case storage' => ['Public://x.jpg'];
        yield 'a scheme of addresses' => ['ftp://host/x.jpg'];
        yield 'out of the storage' => ['public://ab/../../etc/passwd'];
        yield 'from the root' => ['public:///etc/passwd'];
        yield 'a backslash' => ['public://ab\\cd.jpg'];
        yield 'a control character' => ["public://ab\0.jpg"];
        yield 'no path' => ['public://'];
    }

    #[DataProvider('notReferences')]
    public function testWhatIsNoReference(string $text): void
    {
        self::assertNull(FileReference::tryParse($text));

        $this->expectException(InvalidArgumentException::class);
        FileReference::parse($text);
    }

    public function testTheSchemesOfAddressesAreNoStorages(): void
    {
        self::assertTrue(FileReference::isStorageName('avatars'));
        self::assertTrue(FileReference::isStorageName('private-docs_2'));
        self::assertFalse(FileReference::isStorageName('https'));
        self::assertFalse(FileReference::isStorageName('ftp'));
        self::assertFalse(FileReference::isStorageName('2public'));
    }
}
