<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Form;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Byfareska\Crud\Files\Binding\ColumnBinding;
use Byfareska\Crud\Files\Binding\FileBinding;
use Byfareska\Crud\Files\Binding\ItemCollectionBinding;
use Byfareska\Crud\Files\Binding\RecordBinding;
use Byfareska\Crud\Files\BrowserFiles;
use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileReferenceSigner;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\LibraryCapability;
use Byfareska\Crud\Files\SubmittedFiles;
use Byfareska\Crud\Files\UploadProfile;
use Closure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A field of files: uploaded at once when they are chosen or dropped, or picked from those uploaded before (the
 * window of the library), one file or many - a gallery, sorted by dragging, when it takes only images. Its value in
 * the record is what its "binding" says:
 *
 * - "column" (the default): the reference ("public://ab/cd/x7k2.jpg"), or a list of them in a column of JSON - with
 *   what "meta" says of each ([{"ref": "…", "alt": "…"}]);
 * - "record": the entity of a file (record_class: the library's CrudFileRecord, the application's Media) - a relation
 *   to one, or ManyToMany with "multiple";
 * - "items": the items of the application's own (item_class: ProductImage) with their file, position and "meta";
 * - a FileBinding of the application's.
 *
 *     $builder->add('gallery', CrudFileType::class, [
 *         'multiple' => true,
 *         'accept' => ['images'],
 *         'meta' => ['alt'],
 *         'binding' => 'items',
 *         'item_class' => ProductImage::class,
 *     ]);
 *
 * Files the record held are what the index of usages (FileUsages) is told once it is saved.
 */
final class CrudFileType extends AbstractType
{
    /** The attribute of the config of a field with its FileBinding (reindexing reads the fields with it). */
    public const string BINDING = 'crud_file_binding';

    public function __construct(
        private readonly FileStorages $storages,
        private readonly FileLibraries $libraries,
        private readonly BrowserFiles $browser,
        private readonly FileReferenceSigner $signer,
        private readonly FileRecordMappers $mappers,
        private readonly SubmittedFiles $submittedFiles,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array{binding: FileBinding, multiple: bool, meta: list<string>, max_files: int|null} $options */
        $transformer = new FileFieldTransformer(
            $options['binding'],
            $this->profile($options),
            $options['multiple'],
            $options['meta'],
            $this->signer,
            $this->libraries,
            $this->translator,
        );
        $builder->addViewTransformer($transformer);
        $builder->setAttribute(self::BINDING, $options['binding']);

        $maxFiles = $options['max_files'];
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($transformer, $maxFiles): void {
                $form = $event->getForm();
                if (!$form->isSynchronized()) {
                    return;
                }
                $items = $transformer->submitted();
                if ($maxFiles !== null && count($items) > $maxFiles) {
                    $form->addError(new FormError($this->translator->trans('file.field.too_many', [
                        'max' => $maxFiles,
                    ])));
                }
                $this->submittedFiles->record(
                    self::pathOf($form),
                    array_map(static fn (FileItem $item): FileReference => $item->reference, $items),
                );
            },
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array{multiple: bool, meta: list<string>, max_files: int|null, library: bool, gallery: bool|null} $options */
        $profile = $this->profile($options);
        $browse = $options['library'] ? array_values(array_filter(
            $profile->storages(),
            fn (string $storage): bool => $this->browser->mayUse($storage)
                && $this->libraries->can($storage, LibraryCapability::Browse),
        )) : [];
        $multiple = $options['multiple'];

        $view->vars['multiple'] = $multiple;
        $view->vars['gallery'] = $options['gallery'] ?? $profile->imagesOnly();
        $view->vars['meta'] = $options['meta'];
        $view->vars['max_files'] = $multiple ? $options['max_files'] : 1;
        $view->vars['accept'] = $this->acceptAttribute($profile);
        $view->vars['upload_url'] = $this->browser->uploadUrl($profile);
        $view->vars['library_token'] = $browse !== []
            ? $this->browser->libraryToken(
                new UploadProfile($profile->storage, $profile->accept, null, $browse),
                $multiple,
            )
            : null;
        $view->vars['files'] = $this->files(is_string($view->vars['value'] ?? null) ? $view->vars['value'] : '');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'error_bubbling' => false,
            'empty_data' => '',
            'storage' => null,
            'multiple' => false,
            'accept' => [],
            'max_size' => null,
            'max_files' => null,
            'browse' => [],
            'library' => true,
            'meta' => [],
            'gallery' => null,
            'binding' => 'column',
            'record_class' => null,
            'item_class' => null,
            'item_file' => 'file',
            'item_position' => 'position',
            'item_meta' => null,
            'item_file_record' => null,
            'item_factory' => null,
            // A list of objects is written through the adders and removers of the record (addImage(), removeImage()).
            'by_reference' => static fn (Options $options): bool => $options['binding'] === 'column'
                || $options['binding'] instanceof FileBinding
                || !$options['multiple'],
        ]);
        $resolver->setAllowedTypes('storage', ['null', 'string']);
        $resolver->setAllowedTypes('multiple', 'bool');
        $resolver->setAllowedTypes('accept', 'string[]');
        $resolver->setAllowedTypes('max_size', ['null', 'int']);
        $resolver->setAllowedTypes('max_files', ['null', 'int']);
        $resolver->setAllowedTypes('browse', 'string[]');
        $resolver->setAllowedTypes('library', 'bool');
        $resolver->setAllowedTypes('meta', 'string[]');
        $resolver->setAllowedTypes('gallery', ['null', 'bool']);
        $resolver->setAllowedTypes('binding', ['string', FileBinding::class]);
        $resolver->setAllowedValues(
            'binding',
            static fn (string|FileBinding $binding): bool => $binding instanceof FileBinding
                || in_array($binding, ['column', 'record', 'items'], true),
        );
        $resolver->setAllowedTypes('record_class', ['null', 'string']);
        $resolver->setAllowedTypes('item_class', ['null', 'string']);
        $resolver->setAllowedTypes('item_file', 'string');
        $resolver->setAllowedTypes('item_position', ['null', 'string']);
        $resolver->setAllowedTypes('item_meta', ['null', 'string[]']);
        $resolver->setAllowedTypes('item_file_record', ['null', 'string']);
        $resolver->setAllowedTypes('item_factory', ['null', Closure::class]);
        $resolver->setNormalizer('storage', fn (Options $options, ?string $storage): string => $storage
            ?? $this->storages->default());
        $resolver->setNormalizer(
            'accept',
            static fn (Options $options, array $accept): array => UploadProfile::expand(
                array_values(array_filter($accept, is_string(...))),
            ),
        );
        $resolver->setNormalizer(
            'binding',
            fn (Options $options, string|FileBinding $binding): FileBinding => $this->binding($binding, $options),
        );
    }

    public function getBlockPrefix(): string
    {
        return 'crud_file';
    }

    /**
     * The path of a field below its form: "gallery", "section.gallery".
     *
     * @param FormInterface<mixed> $form
     */
    public static function pathOf(FormInterface $form): string
    {
        $names = [];
        for ($field = $form; $field->getParent() instanceof FormInterface; $field = $field->getParent()) {
            array_unshift($names, $field->getName());
        }

        return implode('.', $names);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function profile(array $options): UploadProfile
    {
        /** @var array{storage: string, accept: list<string>, max_size: int|null, browse: list<string>} $options */
        return new UploadProfile($options['storage'], $options['accept'], $options['max_size'], $options['browse']);
    }

    private function binding(string|FileBinding $binding, Options $options): FileBinding
    {
        if ($binding instanceof FileBinding) {
            return $binding;
        }
        /** @var bool $multiple */
        $multiple = $options['multiple'];
        /** @var list<string> $meta */
        $meta = $options['meta'];

        return match ($binding) {
            'record' => new RecordBinding(
                $this->mappers,
                $this->classOption($options, 'record_class', 'binding "record"'),
                $multiple,
            ),
            'items' => new ItemCollectionBinding(
                $this->classOption($options, 'item_class', 'binding "items"'),
                $this->stringOption($options, 'item_file'),
                is_string($options['item_position']) ? $options['item_position'] : null,
                $this->itemMeta($options['item_meta'], $meta),
                is_string($options['item_file_record'])
                    ? new RecordBinding($this->mappers, $this->classOption($options, 'item_file_record', 'its file'))
                    : null,
                $options['item_factory'] instanceof Closure ? $options['item_factory'] : null,
            ),
            default => new ColumnBinding($multiple, $meta),
        };
    }

    /**
     * @return class-string
     */
    private function classOption(Options $options, string $name, string $for): string
    {
        $class = $options[$name];
        if (!is_string($class) || !class_exists($class)) {
            throw new InvalidOptionsException(sprintf(
                'A field of files with the %s needs "%s": the class.',
                $for,
                $name,
            ));
        }

        return $class;
    }

    private function stringOption(Options $options, string $name): string
    {
        $value = $options[$name];

        return is_string($value) ? $value : '';
    }

    /**
     * The properties of an item holding what is said of its file, by name: those of "item_meta", else of the same
     * names.
     *
     * @param list<string> $meta
     * @return array<string, string>
     */
    private function itemMeta(mixed $itemMeta, array $meta): array
    {
        $properties = [];
        foreach (is_array($itemMeta) ? $itemMeta : array_combine($meta, $meta) as $name => $property) {
            if (is_string($name) && is_string($property)) {
                $properties[$name] = $property;
            }
        }

        return $properties;
    }

    /** What the file input of the browser offers: the MIME types and extensions the field takes. */
    private function acceptAttribute(UploadProfile $profile): string
    {
        return implode(',', $profile->accept);
    }

    /**
     * The files of a value of the field, as the browser shows them.
     *
     * @return list<array<string, mixed>>
     */
    private function files(string $value): array
    {
        $entries = $value === '' ? [] : json_decode($value, true);
        $files = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            $reference = is_array($entry) && is_string($entry['ref'] ?? null)
                ? FileReference::tryParse($entry['ref'])
                : null;
            if (!$reference instanceof FileReference) {
                continue;
            }
            $meta = is_array($entry['meta'] ?? null) ? array_filter($entry['meta'], is_string(...)) : [];
            $files[] = $this->browser->json($this->libraries->describe($reference)) + ['meta' => $meta];
        }

        return $files;
    }
}
