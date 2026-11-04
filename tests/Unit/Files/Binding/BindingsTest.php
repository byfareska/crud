<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Binding;

use Byfareska\Crud\Files\Binding\ColumnBinding;
use Byfareska\Crud\Files\Binding\ItemCollectionBinding;
use Byfareska\Crud\Files\Binding\RecordBinding;
use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileRecord;
use Byfareska\Crud\Files\FileRecordMapper;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\ProductImage;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * How a field of files reads and writes the files of a record.
 */
final class BindingsTest extends TestCase
{
    public function testAColumnOfOneFile(): void
    {
        $binding = new ColumnBinding();

        self::assertEquals([$this->item('public://a.png')], $binding->read('public://a.png'));
        self::assertSame([], $binding->read(null));
        self::assertSame([], $binding->read('not a reference'));
        self::assertSame('public://a.png', $binding->write([$this->item('public://a.png')], null));
        self::assertNull($binding->write([], 'public://a.png'));
    }

    public function testAColumnOfAList(): void
    {
        $plain = new ColumnBinding(multiple: true);
        $described = new ColumnBinding(multiple: true, meta: ['alt']);
        $items = [
            $this->item('public://a.png', ['alt' => 'A', 'caption' => 'not kept']),
            $this->item('public://b.png'),
        ];

        self::assertSame(['public://a.png', 'public://b.png'], $plain->write($items, null));
        self::assertSame(
            [['ref' => 'public://a.png', 'alt' => 'A'], ['ref' => 'public://b.png']],
            $described->write($items, null),
        );
        self::assertEquals(
            [$this->item('public://a.png', ['alt' => 'A']), $this->item('public://b.png')],
            $described->read([['ref' => 'public://a.png', 'alt' => 'A'], 'public://b.png']),
        );
    }

    public function testObjectsOfFiles(): void
    {
        $a = $this->record('public://a.png');
        $b = $this->record('public://b.png');
        $mappers = new FileRecordMappers([$this->finding([$a, $b])]);
        $binding = new RecordBinding($mappers, $a::class, multiple: true);

        self::assertEquals([$this->item('public://a.png')], $binding->read([$a]));
        $reordered = [$this->item('public://b.png'), $this->item('public://a.png')];
        self::assertSame([$b, $a], $binding->write($reordered, [$a]));
        self::assertSame($b, new RecordBinding($mappers, $a::class)->write([$this->item('public://b.png')], null));

        $this->expectException(LogicException::class);
        $binding->write([$this->item('public://none.png')], []);
    }

    public function testItemsOfTheApplication(): void
    {
        $binding = new ItemCollectionBinding(ProductImage::class, meta: ['alt' => 'alt']);
        $first = $this->image('public://a.png', 1, 'A');
        $second = $this->image('public://b.png', 0, null);

        self::assertEquals(
            [$this->item('public://b.png'), $this->item('public://a.png', ['alt' => 'A'])],
            $binding->read([$first, $second]),
            'By their position.',
        );

        $written = $binding->write(
            [$this->item('public://a.png', ['alt' => 'Changed']), $this->item('public://c.png', ['alt' => 'New'])],
            [$first, $second],
        );

        self::assertIsArray($written);
        self::assertCount(2, $written);
        self::assertSame($first, $written[0], 'The item of a file still there is kept.');
        self::assertSame([0, 'Changed'], [$first->position, $first->alt]);
        self::assertInstanceOf(ProductImage::class, $written[1]);
        self::assertSame(['public://c.png', 1, 'New'], [$written[1]->file, $written[1]->position, $written[1]->alt]);

        $binding->write([$this->item('public://a.png')], [$first]);
        self::assertNull($first->alt, 'Nothing said: null.');
    }

    public function testAnItemMadeWithArgumentsNeedsAFactory(): void
    {
        $item = new class ('x') {
            public string $file = '';

            public function __construct(public string $required)
            {
            }
        };
        $binding = new ItemCollectionBinding($item::class, position: null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('item_factory');
        $binding->write([$this->item('public://a.png')], []);
    }

    /**
     * @param array<string, string> $meta
     */
    private function item(string $reference, array $meta = []): FileItem
    {
        return new FileItem(FileReference::parse($reference), $meta);
    }

    private function image(string $file, int $position, ?string $alt): ProductImage
    {
        $image = new ProductImage();
        $image->file = $file;
        $image->position = $position;
        $image->alt = $alt;

        return $image;
    }

    private function record(string $reference): FileRecord
    {
        return new readonly class (FileReference::parse($reference)) implements FileRecord {
            public function __construct(private FileReference $reference)
            {
            }

            public function storedFile(): StoredFile
            {
                return StoredFile::of($this->reference);
            }
        };
    }

    /**
     * @param list<FileRecord> $records
     */
    private function finding(array $records): FileRecordMapper
    {
        return new readonly class ($records) implements FileRecordMapper {
            /** @param list<FileRecord> $records */
            public function __construct(private array $records)
            {
            }

            public function supports(object $record): bool
            {
                return $record instanceof FileRecord;
            }

            public function toStoredFile(object $record): StoredFile
            {
                assert($record instanceof FileRecord);

                return $record->storedFile();
            }

            public function find(string $class, FileReference $reference): ?object
            {
                return array_find(
                    $this->records,
                    static fn (FileRecord $record): bool => $record->storedFile()->reference->equals($reference),
                );
            }
        };
    }
}
