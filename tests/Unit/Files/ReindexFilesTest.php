<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\Command\ReindexFilesCommand;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\Supplier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * What the index of usages is built of again: the files the fields of files of the form of a record hold.
 */
final class ReindexFilesTest extends TestCase
{
    public function testTheFilesOfTheFieldsOfAForm(): void
    {
        $files = new FilesKit();
        $supplier = new Supplier();
        $supplier->logo = 'public://ab/logo.png';
        $supplier->gallery = [['ref' => 'public://ab/a.png'], ['ref' => 'public://ab/b.png']];
        $forms = $files->forms();
        $form = $forms->createBuilder(FormType::class, $supplier, ['data_class' => Supplier::class])
            ->add('name', TextType::class)
            ->add('logo')
            ->add($forms->createNamedBuilder('more', FormType::class, $supplier, [
                'auto_initialize' => false,
                'inherit_data' => true,
            ])->add('gallery', CrudFileType::class, ['multiple' => true]))
            ->getForm();

        self::assertEquals(
            [
                'logo' => [FileReference::parse('public://ab/logo.png')],
                'more.gallery' => [
                    FileReference::parse('public://ab/a.png'),
                    FileReference::parse('public://ab/b.png'),
                ],
            ],
            ReindexFilesCommand::filesOf($form),
        );
    }
}
