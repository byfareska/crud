<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Form;

use Byfareska\Crud\Files\Command\ReindexFilesCommand;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Form\CrudRichTextType;
use Byfareska\Crud\Tests\Unit\Files\FilesKit;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\Article;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class CrudRichTextTypeTest extends TestCase
{
    private FilesKit $files;

    protected function setUp(): void
    {
        $this->files = new FilesKit();
    }

    public function testWithoutFilesItIsATextareaForTheEditor(): void
    {
        $forms = Forms::createFormFactoryBuilder()
            ->addType(new CrudRichTextType([
                'editor' => 'tinymce',
                'options' => ['license_key' => 'gpl'],
                'files' => false,
            ]))
            ->getFormFactory();

        $view = $forms->createNamed('body', CrudRichTextType::class, '<p>Hi</p>', [
            'editor_options' => ['toolbar' => 'bold'],
        ])->createView();

        self::assertSame('tinymce', $view->vars['editor']);
        self::assertSame(['license_key' => 'gpl', 'toolbar' => 'bold'], $view->vars['editor_options']);
        self::assertNull($view->vars['upload_url']);
        self::assertNull($view->vars['library_token']);
        self::assertSame('<p>Hi</p>', $view->vars['value']);
        self::assertIsArray($view->vars['attr']);
        self::assertSame(12, $view->vars['attr']['rows'] ?? null);
        self::assertIsArray($view->vars['block_prefixes']);
        self::assertContains('textarea', $view->vars['block_prefixes']);
    }

    public function testUploadingNeedsFiles(): void
    {
        $forms = Forms::createFormFactoryBuilder()
            ->addType(new CrudRichTextType(['editor' => 'tinymce', 'options' => [], 'files' => false]))
            ->getFormFactory();

        $this->expectException(InvalidOptionsException::class);

        $forms->create(CrudRichTextType::class, null, ['files' => true]);
    }

    public function testTheFilesOfTheTextAreToldOnceSubmitted(): void
    {
        $form = $this->article();

        $form->submit([
            'body' => '<p><img src="/uploads/ab/a.png" alt="">'
                . '<a href="http://localhost/uploads/ab/b%20c.pdf?v=2">PDF</a>'
                . '<img srcset="/uploads/ab/s.png 1x, /uploads/ab/l.png 2x" src="/uploads/ab/a.png">'
                . '<img src="https://cdn.example.com/x.png"><img src="data:image/png;base64,AAAA"><a href="#top">Up</a>'
                . '<a href="/uploads/ab/q.png?a=1&amp;b=2">Q</a></p>',
            'lead' => '<p>Nothing</p>',
            'title' => 'Hi',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame(
            [
                'body' => [
                    'public://ab/a.png',
                    'public://ab/b c.pdf',
                    'public://ab/s.png',
                    'public://ab/l.png',
                    'public://ab/q.png',
                ],
                'lead' => [],
            ],
            array_map(
                static fn (array $references): array => array_map(strval(...), $references),
                $this->files->submitted->take(),
            ),
        );
    }

    public function testTheFieldThatUploadsHasWhereToAndTheLibrary(): void
    {
        $view = $this->article()->createView();
        $body = $view['body'];

        self::assertSame(['toolbar' => 'bold italic'], $body->vars['editor_options']);
        self::assertIsString($body->vars['upload_url']);
        self::assertStringContainsString('storage=public', $body->vars['upload_url']);
        self::assertIsString($body->vars['library_token']);
        self::assertIsString($body->vars['image_token']);
        $images = $this->files->browser->libraryOf($body->vars['image_token']);
        self::assertSame(
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
            $images[0]->accept ?? null,
        );
        self::assertFalse($images[1] ?? true, 'One file at a time.');
        self::assertNull($view['lead']->vars['upload_url'], 'Off until it is asked for.');
    }

    public function testAnyFileButImagesFromTheirOwnWindow(): void
    {
        $view = $this->files->forms()
            ->createNamed('body', CrudRichTextType::class, null, ['files' => true])
            ->createView();
        self::assertIsString($view->vars['library_token']);
        self::assertIsString($view->vars['image_token']);

        self::assertSame([], $this->files->browser->libraryOf($view->vars['library_token'])[0]->accept ?? null);
        self::assertSame(
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
            $this->files->browser->libraryOf($view->vars['image_token'])[0]->accept ?? null,
        );
    }

    public function testFilesOfTheConfigurationUnlessTheFieldSays(): void
    {
        $forms = $this->files->forms(['files' => true]);

        self::assertNotNull($forms->create(CrudRichTextType::class)->createView()->vars['upload_url']);
        self::assertNull(
            $forms->create(CrudRichTextType::class, null, ['files' => false])->createView()->vars['upload_url'],
        );
    }

    public function testOnlyAStorageWithPublicAddresses(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('"private"');

        $this->files->forms()->create(CrudRichTextType::class, null, ['files' => ['storage' => 'private']]);
    }

    public function testReindexingReadsTheText(): void
    {
        $article = new Article();
        $article->body = '<img src="/uploads/ab/a.png">';

        $files = ReindexFilesCommand::filesOf($this->article($article));

        self::assertEquals(['body' => [FileReference::parse('public://ab/a.png')], 'lead' => []], $files);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function article(?Article $article = null): FormInterface
    {
        return $this->files->forms()->createBuilder(FormType::class, $article ?? new Article(), [
            'data_class' => Article::class,
        ])
            ->add('body')
            ->add('lead')
            ->add('title')
            ->getForm();
    }
}
