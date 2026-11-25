<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Form;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Tests\Unit\Files\FilesKit;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\Supplier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A field of files takes the files the server gave the browser - uploaded, picked in the library - and those the
 * record had; nothing else the browser names.
 */
final class CrudFileTypeTest extends TestCase
{
    private FilesKit $files;

    protected function setUp(): void
    {
        $this->files = new FilesKit();
    }

    public function testAnUploadedFileIsTakenWithItsSignature(): void
    {
        $logo = $this->files->put('ab/cd/logo.png');
        $form = $this->form(new Supplier());

        $form->submit(['logo' => $this->value($logo)]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('public://ab/cd/logo.png', $this->supplier($form)->logo);
        self::assertEquals(['logo' => [$logo], 'gallery' => []], $this->files->submitted->take());
    }

    public function testAFileTheBrowserNamesIsNotTaken(): void
    {
        $supplier = new Supplier();
        $supplier->logo = 'public://ab/old.png';
        $form = $this->form($supplier);

        $form->submit(['logo' => FilesKit::json([['ref' => 'public://ab/cd/somebody-elses.png']])]);

        self::assertFalse($form->get('logo')->isSynchronized());
        self::assertSame('public://ab/old.png', $supplier->logo);
        self::assertStringContainsString('file.field.invalid', (string) $form->get('logo')->getErrors());
    }

    public function testTheFileTheRecordHadNeedsNoSignature(): void
    {
        $supplier = new Supplier();
        $supplier->gallery = [['ref' => 'public://ab/a.png', 'alt' => 'A'], ['ref' => 'public://ab/b.png']];
        $form = $this->form($supplier);

        $form->submit(['gallery' => FilesKit::json([
            ['ref' => 'public://ab/b.png', 'meta' => ['alt' => ' B ', 'caption' => 'not of this field']],
            ['ref' => 'public://ab/a.png'],
        ])]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(
            [['ref' => 'public://ab/b.png', 'alt' => 'B'], ['ref' => 'public://ab/a.png']],
            $supplier->gallery,
        );
    }

    public function testNotAFileOfAStorageTheFieldDoesNotPickFrom(): void
    {
        $form = $this->form(new Supplier());
        $private = FileReference::parse('private://ab/contract.png');

        $form->submit(['logo' => $this->value($private)]);

        self::assertFalse($form->get('logo')->isSynchronized());
    }

    public function testNotAFileOfAKindTheFieldDoesNotTake(): void
    {
        $form = $this->form(new Supplier());
        $pdf = $this->files->put('ab/invoice.pdf', "%PDF-1.4\n");

        $form->submit(['logo' => $this->value($pdf)]);

        self::assertFalse($form->get('logo')->isSynchronized());
        self::assertStringContainsString('file.field.kind', (string) $form->get('logo')->getErrors());
    }

    public function testAFieldOfOneFileKeepsTheFirst(): void
    {
        $form = $this->form(new Supplier());
        $a = $this->files->put('ab/a.png');
        $b = $this->files->put('ab/b.png');

        $form->submit(['logo' => FilesKit::json([$this->entry($a), $this->entry($b)])]);

        self::assertSame('public://ab/a.png', $this->supplier($form)->logo);
    }

    public function testAtMostMaxFiles(): void
    {
        $form = $this->files->forms()->createBuilder(FormType::class, new Supplier())
            ->add('attachments', CrudFileType::class, ['multiple' => true, 'max_files' => 1])
            ->getForm();

        $form->submit(['attachments' => FilesKit::json([
            $this->entry($this->files->put('a.txt')),
            $this->entry($this->files->put('b.txt')),
        ])]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('file.field.too_many', (string) $form->get('attachments')->getErrors());
    }

    public function testEmptiedTheFieldHoldsNothing(): void
    {
        $supplier = new Supplier();
        $supplier->logo = 'public://ab/old.png';
        $form = $this->form($supplier);

        $form->submit(['logo' => '[]']);

        self::assertNull($supplier->logo);
    }

    public function testTheBrowserSeesTheFilesAndWhereToUploadThem(): void
    {
        $supplier = new Supplier();
        $supplier->gallery = [['ref' => (string) $this->files->put('ab/a.png'), 'alt' => 'A']];

        $view = $this->form($supplier)->createView()['gallery'];

        self::assertTrue($view->vars['multiple']);
        self::assertTrue($view->vars['gallery'], 'Only images: a gallery.');
        self::assertSame(['alt'], $view->vars['meta']);
        self::assertSame('image/jpeg,image/png,image/gif,image/webp,image/avif', $view->vars['accept']);
        self::assertSame('[{"ref":"public://ab/a.png","meta":{"alt":"A"}}]', $view->vars['value']);
        self::assertIsString($view->vars['upload_url']);
        self::assertTrue(
            $this->files->uriSigner->checkRequest(Request::create($view->vars['upload_url'], 'POST')),
            'What the controller checks: the whole address of the request.',
        );
        self::assertIsString($view->vars['library_token']);
        self::assertSame([['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true], [
            $this->files->browser->libraryOf($view->vars['library_token'])[0]->accept ?? null,
            $this->files->browser->libraryOf($view->vars['library_token'])[1] ?? null,
        ]);
        $files = $view->vars['files'];
        self::assertIsArray($files);
        $file = $files[0] ?? null;
        self::assertIsArray($file);
        self::assertSame(['public://ab/a.png', '/uploads/ab/a.png', 'a.png', true, ['alt' => 'A']], [
            $file['ref'],
            $file['url'],
            $file['name'],
            $file['image'],
            $file['meta'],
        ]);
    }

    public function testARefusedValueTellsNothingOfAFileTheBrowserNamed(): void
    {
        $files = new FilesKit(['ROLE_ADMIN']);
        // A file of another record, never shown to the one editing this one.
        $secret = $files->put('zz/other-record-secret.png');
        $form = $files->forms()->createBuilder(FormType::class, new Supplier(), ['data_class' => Supplier::class])
            ->add('logo')
            ->getForm();

        $form->submit(['logo' => FilesKit::json([['ref' => (string) $secret]])]);

        self::assertFalse($form->get('logo')->isSynchronized());
        // The form comes back with what the browser sent: not described, not signed.
        self::assertSame([], $form->createView()['logo']->vars['files']);

        // Nor taken in another request with a signature made up.
        $supplier = new Supplier();
        $again = $files->forms()->createBuilder(FormType::class, $supplier, ['data_class' => Supplier::class])
            ->add('logo')
            ->getForm();
        $again->submit(['logo' => FilesKit::json([['ref' => (string) $secret, 'sig' => 'forged']])]);

        self::assertFalse($again->get('logo')->isSynchronized());
        self::assertNull($supplier->logo);
    }

    public function testNoSignedAddressOfAPrivateFileTheBrowserNamed(): void
    {
        $files = new FilesKit(['ROLE_ADMIN']);
        $files->filesystem->write('notes/anything.txt', 'secret');
        $form = $files->forms()->createBuilder(FormType::class, ['att' => null])
            ->add('att', CrudFileType::class, ['storage' => 'private', 'multiple' => true])
            ->getForm();

        $form->submit(['att' => FilesKit::json([['ref' => 'private://notes/anything.txt']])]);

        self::assertSame([], $form->createView()['att']->vars['files']);
    }

    public function testTheFilesTheServerGaveAreShownAgainWhenTheFormIsRefused(): void
    {
        $supplier = new Supplier();
        $supplier->gallery = [['ref' => 'public://ab/had.png']];
        $form = $this->form($supplier);
        $uploaded = $this->files->put('ab/uploaded.png');

        $form->submit(['gallery' => FilesKit::json([
            ['ref' => 'public://ab/had.png'],
            $this->entry($uploaded),
            ['ref' => 'public://ab/made-up.png'],
        ])]);

        self::assertFalse($form->get('gallery')->isSynchronized());
        $files = $form->createView()['gallery']->vars['files'];
        self::assertIsArray($files);
        self::assertSame(['public://ab/had.png', 'public://ab/uploaded.png'], array_column($files, 'ref'));
        self::assertIsArray($files[1]);
        self::assertSame($this->files->signer->sign($uploaded), $files[1]['sig'] ?? null);
    }

    public function testNotAFileBiggerThanTheFieldTakes(): void
    {
        $form = $this->files->forms()->createBuilder(FormType::class, new Supplier())
            ->add('attachments', CrudFileType::class, ['multiple' => true, 'max_size' => 10])
            ->getForm();
        // Picked in the library, uploaded by a field that takes bigger files.
        $big = $this->files->put('ab/big.txt', str_repeat('a', 11));

        $form->submit(['attachments' => $this->value($big)]);

        self::assertFalse($form->get('attachments')->isSynchronized());
        self::assertStringContainsString('file.refused.too_big', (string) $form->get('attachments')->getErrors());

        $form = $this->files->forms()->createBuilder(FormType::class, new Supplier())
            ->add('attachments', CrudFileType::class, ['multiple' => true, 'max_size' => 11])
            ->getForm();
        $form->submit(['attachments' => $this->value($big)]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testNotAFileOfAStorageTheOneActingMayNotUse(): void
    {
        // Signed for an administrator; the one acting is none.
        $contract = FileReference::parse('private://ab/contract.pdf');
        $this->files->filesystem->write('ab/contract.pdf', "%PDF-1.4\n");
        $form = $this->files->forms()->createBuilder(FormType::class, new Supplier())
            ->add('attachments', CrudFileType::class, ['multiple' => true, 'browse' => ['private']])
            ->getForm();

        $form->submit(['attachments' => $this->value($contract)]);

        self::assertFalse($form->get('attachments')->isSynchronized());
        self::assertSame([], $form->createView()['attachments']->vars['files']);
    }

    public function testNoHtmlNorSvgForAFieldOfAnyKind(): void
    {
        $svg = $this->files->put('ab/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>');
        $form = $this->files->forms()->createBuilder(FormType::class, new Supplier())
            ->add('attachments', CrudFileType::class, ['multiple' => true])
            ->getForm();

        $form->submit(['attachments' => $this->value($svg)]);

        self::assertFalse($form->get('attachments')->isSynchronized());
        self::assertStringContainsString('file.field.kind', (string) $form->get('attachments')->getErrors());
    }

    public function testNoUploadToAStorageOnlyToPickFrom(): void
    {
        $form = $this->files->forms()->createBuilder(FormType::class, new Supplier())
            ->add('attachments', CrudFileType::class, ['storage' => 'archive', 'multiple' => true])
            ->getForm();

        $view = $form->createView()['attachments'];

        self::assertNull($view->vars['upload_url']);
        self::assertNull($view->vars['library_token'], 'Its library lists nothing.');
    }

    public function testAPathOfAField(): void
    {
        $forms = $this->files->forms();
        $section = $forms->createNamedBuilder('section', FormType::class, null, ['auto_initialize' => false]);
        $form = $forms->createBuilder(FormType::class)
            ->add($section->add('logo', CrudFileType::class))
            ->getForm();

        self::assertSame('section.logo', CrudFileType::pathOf($form->get('section')->get('logo')));
    }

    /**
     * @return FormInterface<mixed>
     */
    private function form(Supplier $supplier): FormInterface
    {
        // The fields guessed from #[CrudFile] (FileTypeGuesser).
        return $this->files->forms()->createBuilder(FormType::class, $supplier, ['data_class' => Supplier::class])
            ->add('logo')
            ->add('gallery')
            ->getForm();
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function supplier(FormInterface $form): Supplier
    {
        $supplier = $form->getData();
        self::assertInstanceOf(Supplier::class, $supplier);

        return $supplier;
    }

    private function value(FileReference $reference): string
    {
        return FilesKit::json([$this->entry($reference)]);
    }

    /**
     * @return array{ref: string, sig: string}
     */
    private function entry(FileReference $reference): array
    {
        return ['ref' => (string) $reference, 'sig' => $this->files->signer->sign($reference)];
    }
}
