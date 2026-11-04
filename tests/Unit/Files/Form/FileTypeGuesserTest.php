<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Form;

use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Files\Form\FileTypeGuesser;
use Byfareska\Crud\Tests\Unit\Files\FilesKit;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\MediaFile;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\Product;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\ProductImage;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\Supplier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Guess\Guess;

/**
 * A property marked #[CrudFile] is a field of files, its binding told by its type.
 */
final class FileTypeGuesserTest extends TestCase
{
    public function testAColumn(): void
    {
        $guess = new FileTypeGuesser()->guessType(Supplier::class, 'gallery');

        self::assertNotNull($guess);
        self::assertSame(CrudFileType::class, $guess->getType());
        self::assertSame(Guess::VERY_HIGH_CONFIDENCE, $guess->getConfidence());
        self::assertSame(
            ['multiple' => true, 'accept' => ['images'], 'meta' => ['alt'], 'binding' => 'column'],
            $guess->getOptions(),
        );
        self::assertFalse(new FileTypeGuesser()->guessType(Supplier::class, 'logo')?->getOptions()['multiple']);
    }

    public function testItemsOfAGallery(): void
    {
        $options = new FileTypeGuesser()->guessType(Product::class, 'images')?->getOptions() ?? [];

        self::assertSame(['items', ProductImage::class], [$options['binding'] ?? null, $options['item_class'] ?? null]);
    }

    public function testNotAPropertyWithoutTheAttribute(): void
    {
        self::assertNull(new FileTypeGuesser()->guessType(Supplier::class, 'name'));
        self::assertNull(new FileTypeGuesser()->guessType(MediaFile::class, 'url'));
        self::assertNull(new FileTypeGuesser()->guessType('App\\NoSuchClass', 'logo'));
    }

    public function testAGalleryOfItemsThroughTheForm(): void
    {
        $files = new FilesKit();
        $product = new Product();
        $form = $files->forms()->createBuilder(FormType::class, $product, ['data_class' => Product::class])
            ->add('images')
            ->getForm();
        $a = $files->put('ab/a.png');
        $b = $files->put('ab/b.png');

        $form->submit(['images' => FilesKit::json([
            ['ref' => (string) $a, 'sig' => $files->signer->sign($a), 'meta' => ['alt' => 'A']],
            ['ref' => (string) $b, 'sig' => $files->signer->sign($b)],
        ])]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $images = $product->getImages();
        self::assertCount(2, $images);
        self::assertSame(['public://ab/a.png', 0, 'A', $product], [
            $images[0]->file,
            $images[0]->position,
            $images[0]->alt,
            $images[0]->product,
        ]);

        $form = $files->forms()->createBuilder(FormType::class, $product, ['data_class' => Product::class])
            ->add('images')
            ->getForm();
        $form->submit(['images' => FilesKit::json([['ref' => (string) $b]])]);

        self::assertSame([$images[1]], $product->getImages(), 'Removed through removeImage().');
        self::assertSame(0, $images[1]->position);
    }
}
