<?php

declare(strict_types=1);

namespace Byfareska\Crud\Form;

use Byfareska\Crud\Files\RichTextFiles;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field of rich text: a textarea of HTML that an editor of the browser draws over - the one registered under the
 * name of "editor" (registerRichTextEditor() of @byfareska/crud; the bundle's adapter is TinyMCE's). Without the
 * editor it stays the textarea.
 *
 *     $builder->add('description', CrudRichTextType::class, [
 *         'editor_options' => ['toolbar' => 'bold italic | bullist numlist | link image'],
 *         'files' => ['accept' => ['images']],
 *     ]);
 *
 * Uploading images and files into the text is off until "files" says (or byfareska_crud.rich_text.files): true, or
 * where to - storage, accept, max_size, browse - of a storage with public addresses, which the HTML keeps. The files of
 * a text are told to the index of usages once the record is saved, whatever "files" says.
 *
 * The HTML is the browser's: what is shown elsewhere is sanitized - "sanitize_html" of symfony/html-sanitizer, or the
 * template that prints it.
 */
final class CrudRichTextType extends AbstractType
{
    /**
     * @param array{editor: string, options: array<string, mixed>, files: bool} $config byfareska_crud.rich_text
     */
    public function __construct(
        #[Autowire(param: 'byfareska_crud.rich_text')]
        private readonly array $config,
        private readonly ?RichTextFiles $files = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $files = $this->files;
        if (!$files instanceof RichTextFiles) {
            return;
        }
        $builder->setAttribute(RichTextFiles::ATTRIBUTE, $files);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($files): void {
            if ($event->getForm()->isSynchronized()) {
                $files->record($event->getForm());
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /**
         * @var array{editor: string, editor_options: array<string, mixed>, files: array<string, mixed>|false} $options
         */
        // The height of the textarea, and of the editor drawn over it.
        $attr = is_array($view->vars['attr']) ? $view->vars['attr'] : [];
        $view->vars['attr'] = $attr + ['rows' => 12];
        $view->vars['editor'] = $options['editor'];
        $view->vars['editor_options'] = $this->config['options'] === []
            ? $options['editor_options']
            : array_replace_recursive($this->config['options'], $options['editor_options']);
        $view->vars['upload_url'] = null;
        $view->vars['library_token'] = null;
        $view->vars['image_token'] = null;
        if ($options['files'] !== false && $this->files instanceof RichTextFiles) {
            $view->vars = $this->files->view($this->files->profile($options['files'])) + $view->vars;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'editor' => null,
            'editor_options' => [],
            'files' => null,
            'required' => false,
        ]);
        $resolver->setAllowedTypes('editor', ['null', 'string']);
        $resolver->setAllowedTypes('editor_options', 'array');
        $resolver->setAllowedTypes('files', ['null', 'bool', 'array']);
        $resolver->setNormalizer('editor', fn (Options $options, ?string $editor): string => $editor
            ?? $this->config['editor']);
        $resolver->setNormalizer('files', function (Options $options, bool|array|null $files): array|false {
            /** @var bool|array<string, mixed>|null $files */
            $files ??= $this->config['files'];
            if ($files === false) {
                return false;
            }
            if (!$this->files instanceof RichTextFiles) {
                throw new InvalidOptionsException(
                    'A field of rich text uploads files with byfareska_crud.files enabled only.',
                );
            }
            $files = $files === true ? [] : $files;
            // Checked here, where the form is built: a storage without public addresses is a mistake of the code.
            $this->files->profile($files);

            return $files;
        });
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'crud_rich_text';
    }
}
