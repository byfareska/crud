<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileValueNormalizer;
use Byfareska\Crud\Files\ProvidesFile;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\MediaFile;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Byfareska\Crud\Value\TypedValue;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * A file in a value of a record is shown as an image or a file.
 */
final class FileValueNormalizerTest extends TestCase
{
    private CrudValues $values;

    protected function setUp(): void
    {
        $this->values = new CrudValues([
            new FileValueNormalizer(new FilesKit()->storages, new FileRecordMappers()),
            new PlainValueNormalizer(),
        ]);
    }

    public function testAReferenceOfAStorage(): void
    {
        self::assertEquals(new TypedValue('image', 'public://ab/a.png'), $this->values->normalize('public://ab/a.png'));
        self::assertEquals(
            new TypedValue('file', 'private://ab/a.pdf'),
            $this->values->normalize('private://ab/a.pdf'),
        );
        self::assertSame('avatars://a.png', $this->values->normalize('avatars://a.png'), 'No such storage: text.');
        self::assertSame('https://example.com/a.png', $this->values->normalize('https://example.com/a.png'));
        self::assertSame('Just text', $this->values->normalize('Just text'));
    }

    public function testAListOfAColumnOfJson(): void
    {
        self::assertEquals(
            [new TypedValue('image', 'public://a.png'), new TypedValue('image', 'public://b.png')],
            $this->values->normalize([['ref' => 'public://a.png', 'alt' => 'A'], 'public://b.png']),
        );
    }

    public function testObjectsOfFiles(): void
    {
        $item = new readonly class implements ProvidesFile {
            public function fileItem(): FileItem
            {
                return new FileItem(FileReference::parse('public://a.png'));
            }
        };
        $record = new MediaFile('public://ab/report', 'Report.pdf', 'application/pdf', new DateTimeImmutable());

        self::assertEquals(new TypedValue('image', 'public://a.png'), $this->values->normalize($item));
        self::assertEquals(new TypedValue('file', 'public://ab/report'), $this->values->normalize($record));
    }
}
