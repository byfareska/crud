<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\Component\FileLibrary;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\NoFileUsages;
use Byfareska\Crud\Files\UploadProfile;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\Supplier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

/**
 * The templates of files of every theme: the field, the values, the window of the library.
 */
final class FileTemplatesTest extends TestCase
{
    private FilesKit $files;

    protected function setUp(): void
    {
        $this->files = new FilesKit();
    }

    /** @return iterable<string, array{string}> */
    public static function themes(): iterable
    {
        foreach (['daisyui', 'tailwind', 'bootstrap5'] as $theme) {
            yield $theme => [$theme];
        }
    }

    #[DataProvider('themes')]
    public function testTheField(string $theme): void
    {
        $supplier = new Supplier();
        $supplier->gallery = [['ref' => (string) $this->files->put('ab/a.png'), 'alt' => 'A cat']];
        $form = $this->files->forms()->createBuilder(FormType::class, $supplier, ['data_class' => Supplier::class])
            ->add('gallery')
            ->getForm()
            ->createView();

        $html = $this->twig($theme)->createTemplate('{{ form_widget(form.gallery) }}')->render(['form' => $form]);

        self::assertStringContainsString('data-controller="file-field"', $html);
        self::assertStringContainsString('name="form[gallery]"', $html);
        self::assertStringContainsString(
            'value="' . htmlspecialchars('[{"ref":"public://ab/a.png","meta":{"alt":"A cat"}}]') . '"',
            $html,
        );
        self::assertStringContainsString('data-ref="public://ab/a.png"', $html);
        self::assertStringContainsString('src="/uploads/ab/a.png"', $html);
        self::assertStringContainsString('value="A cat"', $html);
        self::assertStringContainsString('data-file-field-target="template"', $html);
        self::assertStringContainsString('data-action="file-field#browse"', $html);
        self::assertStringContainsString('accept="image/jpeg,image/png,image/gif,image/webp,image/avif"', $html);
        // The file, and the one of the <template>.
        self::assertSame(2, substr_count($html, 'data-file-field-target="item"'));
    }

    #[DataProvider('themes')]
    public function testTheValues(string $theme): void
    {
        $twig = $this->twig($theme);
        $twig->addFunction(new TwigFunction(
            'crud_file_url',
            static fn (string $ref): string => '/uploads/' . explode('://', $ref)[1],
        ));
        $twig->addFunction(new TwigFunction('crud_file', static fn (): object => (object) ['name' => 'Invoice.pdf']));
        $values = $twig->load('crud/file_values.html.twig');

        self::assertStringContainsString(
            'src="/uploads/ab/a.png"',
            $values->renderBlock('type_image', ['value' => 'public://ab/a.png']),
        );
        self::assertStringContainsString('x.pdf', $values->renderBlock('type_file', ['value' => 'public://ab/x.pdf']));
        self::assertStringContainsString(
            'Invoice.pdf',
            $values->renderBlock('type_file_detail', ['value' => 'public://ab/x.pdf']),
        );
    }

    #[DataProvider('themes')]
    public function testTheWindowOfTheLibrary(string $theme): void
    {
        $this->files->put('ab/a.png');
        $library = new FileLibrary(
            $this->files->browser,
            $this->files->libraries,
            new FileRemover($this->files->storages, $this->files->libraries, new NoFileUsages()),
        );
        $library->open($this->files->browser->libraryToken(new UploadProfile('public'), true));

        $html = $this->twig($theme)->render('components/Crud/FileLibrary.html.twig', [
            'this' => $library,
            'attributes' => new ComponentAttributes(['data-controller' => 'live'], new EscaperRuntime()),
        ]);

        self::assertStringContainsString('data-ref="public://ab/a.png"', $html);
        self::assertStringContainsString('data-action="file-library#toggle"', $html);
        self::assertStringContainsString('data-live-action-param="remove"', $html);
        self::assertStringContainsString('data-model="debounce(300)|search"', $html);
    }

    private function twig(string $theme): Environment
    {
        $templates = dirname(__DIR__, 3) . '/templates/' . $theme;
        $loader = new FilesystemLoader([
            $templates,
            dirname(__DIR__, 3) . '/vendor/symfony/twig-bridge/Resources/views/Form',
        ]);
        $loader->addPath($templates, 'CrudTheme');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new FormExtension());
        $engine = new TwigRendererEngine(['crud/form_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));
        $twig->addFunction(new TwigFunction(
            'crud_icon',
            static fn (string $name): string => '<i class="bi bi-' . $name . '"></i>',
            ['is_safe' => ['html']],
        ));

        return $twig;
    }
}
