<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CopiesRecords;
use Byfareska\Crud\Driver\CrudDriver;
use Byfareska\Crud\Driver\PrefillsForm;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\IdMapper;
use Byfareska\Crud\UseCase\CountsApproximately;
use Byfareska\Crud\UseCase\ListPage;
use Byfareska\Crud\UseCase\MapsToRequest;
use Byfareska\Crud\UseCase\Page;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use LogicException;
use Stringable;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * The records of a {@see DtoSource}: every operation is its DTO, filled through its own form type (list, create,
 * update) or addressed by the id (read, delete), and run through the use case it names.
 */
final readonly class DtoDriver implements CrudDriver, PrefillsForm, CopiesRecords
{
    public function __construct(
        private UseCaseExecutor $executor,
        private DtoRecords $records,
        private FormFactoryInterface $formFactory,
        private IdMapper $ids,
    ) {
    }

    public function name(): string
    {
        return DtoSource::DRIVER;
    }

    public function listForm(AsCrud $crud): FormInterface
    {
        return $this->formFactory->createNamed(
            'filters',
            $this->dto($crud, CrudOperation::List),
            null,
            ['csrf_protection' => false],
        );
    }

    public function list(AsCrud $crud, FormInterface $form): Page
    {
        $dto = $form->getData();
        if (!$dto instanceof MapsToRequest) {
            throw new LogicException(sprintf('The list form of "%s" did not produce its DTO.', $crud->name));
        }

        $result = $this->executor->execute($dto);
        // A list of the records, not a page of them: the list pages them itself (a list DTO without "page").
        if (is_array($result) && array_is_list($result)) {
            $items = array_map($this->records->properties(...), $result);

            return new ListPage($items, count($items), 1, max(1, count($items)));
        }
        if (!$result instanceof Page) {
            throw new LogicException(sprintf(
                'The list use case of "%s" has to return a "%s" or a list of the records.',
                $crud->name,
                Page::class,
            ));
        }
        // Records as objects of the application's (read models): by property, as the list has them.
        if (!array_all($result->items, static fn (mixed $item): bool => is_array($item))) {
            return new ListPage(
                array_map($this->records->properties(...), $result->items),
                $result->total,
                $result->page,
                $result->perPage,
                // "More than N" as the use case said it.
                $result instanceof CountsApproximately && $result->more,
            );
        }

        return $result;
    }

    public function read(AsCrud $crud, string $id): array
    {
        return $this->records->properties($this->records->run($this->dto($crud, CrudOperation::Read), $id));
    }

    public function delete(AsCrud $crud, string $id): void
    {
        $this->records->run($this->dto($crud, CrudOperation::Delete), $id);
    }

    public function form(AsCrud $crud, ?string $id, array $with = []): FormBuilderInterface
    {
        $source = $this->source($crud);
        $data = $id === null
            ? $this->records->newDto($this->dto($crud, CrudOperation::Create))
            : $this->records->editDto($this->dto($crud, CrudOperation::Update), $source->read, $id);

        // A class marked #[AsFormType] is a form type by its name. The live component endpoint has its own
        // protection against cross-site requests.
        $builder = $this->formFactory->createBuilder($data::class, $data, ['csrf_protection' => false]);
        if ($id === null) {
            // What the address gives a new record, only for the fields of its form: nothing it keeps from the user.
            $this->prefill($crud, $builder, $with);
        }

        return $builder;
    }

    public function copy(AsCrud $crud, string $id, FormBuilderInterface $builder, array $fields): void
    {
        $data = $builder->getData();
        $source = $this->source($crud);
        if ($data instanceof MapsToRequest && $source->read !== null) {
            // What "read" says of the record, onto the properties of the add DTO the form has.
            $this->records->copyInto($data, $source->read, $id, $fields);
        }
    }

    public function prefill(AsCrud $crud, FormBuilderInterface $builder, array $with): void
    {
        $data = $builder->getData();
        if (!is_object($data)) {
            return;
        }
        // Only the fields of its form a user may fill in: nothing it keeps from the user, nothing disabled.
        $this->records->prefill($data, array_filter(
            $with,
            static fn (int|string $field): bool => $builder->has((string) $field)
                && !$builder->get((string) $field)->getDisabled()
                && $builder->get((string) $field)->getMapped(),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function save(AsCrud $crud, FormInterface $form, ?string $id): ?string
    {
        $dto = $form->getData();
        if (!$dto instanceof MapsToRequest) {
            throw new LogicException(sprintf('The form of "%s" did not produce its DTO.', $crud->name));
        }
        if ($id !== null) {
            $this->records->address($dto, $id);
        }

        $saved = $this->executor->execute($dto);

        // A new record is known by what its use case returned, if it says: the id itself (a number, a Uuid), or
        // something with an "id" (a property, getId()).
        return $id ?? $this->ids->toId(match (true) {
            is_int($saved), is_string($saved), $saved instanceof Stringable => $saved,
            is_object($saved) && isset($saved->id) => $saved->id,
            is_object($saved) && method_exists($saved, 'getId') => $saved->getId(),
            default => null,
        });
    }

    /**
     * @return class-string<MapsToRequest>
     */
    private function dto(AsCrud $crud, CrudOperation $operation): string
    {
        return $this->source($crud)->dto($operation)
            ?? throw new LogicException(sprintf('CRUD resource "%s" cannot %s.', $crud->name, $operation->value));
    }

    private function source(AsCrud $crud): DtoSource
    {
        return $crud->source instanceof DtoSource
            ? $crud->source
            : throw new LogicException(sprintf('CRUD resource "%s" is not one of DTOs.', $crud->name));
    }
}
