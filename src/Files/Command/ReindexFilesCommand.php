<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Command;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDriver;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Driver\ListsIds;
use Byfareska\Crud\Files\Binding\FileBinding;
use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Files\RichTextFiles;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\IdMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormInterface;
use Throwable;

/**
 * Builds the index of where files are used (FileUsages) again from the records themselves: every record of every
 * resource with an edit form, read through its driver, and the fields of files of that form (CrudFileType) - what
 * changed outside of the panel (an import, an API) is in it then. Run it before byfareska:crud:files:prune when records
 * are changed so.
 */
#[AsCommand('byfareska:crud:files:reindex', 'Builds the index of where uploaded files are used again')]
final readonly class ReindexFilesCommand
{
    private const int PAGE = 100;

    public function __construct(
        private CrudRegistry $registry,
        private CrudDrivers $drivers,
        private FileUsages $usages,
        private IdMapper $ids,
    ) {
    }

    /**
     * @param list<string> $resource
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only this resource (repeatable); default every one')]
        array $resource = [],
    ): int {
        if (!$this->usages->tracks()) {
            $io->warning('No index of where files are used (byfareska_crud.files.usages: none).');

            return 0;
        }
        $records = 0;
        $failed = 0;
        foreach ($this->registry->all() as $crud) {
            if (($resource !== [] && !in_array($crud->name, $resource, true)) || !$crud->can(CrudOperation::Update)) {
                continue;
            }
            $driver = $this->drivers->of($crud);
            foreach ($this->idsOf($crud, $driver) as $id) {
                try {
                    $fields = self::filesOf($driver->form($crud, $id)->getForm());
                } catch (Throwable $failure) {
                    $io->warning(sprintf('%s #%s: %s', $crud->name, $id, $failure->getMessage()));
                    ++$failed;
                    continue;
                }
                if ($fields === []) {
                    // No field of files in its form: none in the forms of the other records either.
                    break;
                }
                $this->usages->forget($crud->name, $id);
                foreach ($fields as $field => $references) {
                    $this->usages->replace($crud->name, $id, $field, $references);
                }
                ++$records;
            }
        }

        $io->success(sprintf('Indexed the files of %d record(s).', $records));

        return $failed === 0 ? 0 : 1;
    }

    /**
     * The files of the fields of files and of rich text of a form, by the path of the field.
     *
     * @param FormInterface<mixed> $form
     * @return array<string, list<FileReference>>
     */
    public static function filesOf(FormInterface $form): array
    {
        $files = [];
        foreach ($form->all() as $field) {
            $binding = $field->getConfig()->getAttribute(CrudFileType::BINDING);
            $richText = $field->getConfig()->getAttribute(RichTextFiles::ATTRIBUTE);
            if ($richText instanceof RichTextFiles) {
                $html = $field->getData();
                $files[CrudFileType::pathOf($field)] = $richText->references(is_string($html) ? $html : '');
            } elseif ($binding instanceof FileBinding) {
                $files[CrudFileType::pathOf($field)] = array_map(
                    static fn (FileItem $item): FileReference => $item->reference,
                    $binding->read($field->getData()),
                );
            } elseif ($field->getConfig()->getCompound()) {
                $files += self::filesOf($field);
            }
        }

        return $files;
    }

    /**
     * @return iterable<string>
     */
    private function idsOf(AsCrud $crud, CrudDriver $driver): iterable
    {
        $form = $driver->listForm($crud);
        $form->submit([]);
        $ids = $driver instanceof ListsIds && $form->isValid() ? $driver->ids($crud, $form) : null;
        if ($ids !== null) {
            yield from $ids;

            return;
        }
        for ($page = 1;; ++$page) {
            $form = $driver->listForm($crud);
            $form->submit(['page' => $page, 'perPage' => self::PAGE]);
            $found = $driver->list($crud, $form);
            foreach ($found->items as $item) {
                $id = $this->ids->toId($item['id'] ?? null);
                if ($id !== null) {
                    yield $id;
                }
            }
            if ($page >= $found->pageCount) {
                return;
            }
        }
    }
}
