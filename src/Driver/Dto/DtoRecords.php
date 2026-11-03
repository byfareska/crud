<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

use BackedEnum;
use Byfareska\Crud\Attribute\AsCrudFormSection;
use Byfareska\Crud\UseCase\FillsFromRecord;
use Byfareska\Crud\UseCase\InvalidIdException;
use Byfareska\Crud\UseCase\MapsToRequest;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use Byfareska\Crud\UseCase\UseCaseFailed;
use JsonSerializable;
use LogicException;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface as PropertyAccessExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * DTOs addressed to a record by its id, and the edit DTOs filled with what "read" returns - for the "dto" driver and
 * for the sections other parts add to a form (AsCrudFormSection), whatever the driver of the resource.
 */
final readonly class DtoRecords
{
    public function __construct(
        private UseCaseExecutor $executor,
        private PropertyAccessorInterface $propertyAccessor,
        private ?DtoFactory $factory = null,
        private ?DtoRecordProperties $properties = null,
    ) {
    }

    /**
     * What a record read by a use case is by property - see DtoRecordProperties (by default propertiesOf()).
     *
     * @return array<string, mixed>
     */
    public function properties(mixed $record): array
    {
        return $this->properties?->of($record) ?? self::propertiesOf($record);
    }

    /**
     * Runs the DTO of the class, addressed to the record.
     *
     * @param class-string<MapsToRequest>|null $class
     *
     * @throws UseCaseFailed
     */
    public function run(?string $class, string $id): mixed
    {
        return $this->executor->execute($this->dtoFor($class, $id));
    }

    /**
     * The DTO of an edit form: addressed to the record and, when it can be read, carrying its current values.
     *
     * @param class-string<MapsToRequest> $class
     * @param class-string<MapsToRequest>|null $read
     *
     * @throws UseCaseFailed
     */
    public function editDto(string $class, ?string $read, string $id): MapsToRequest
    {
        $dto = $this->dtoFor($class, $id);
        if ($read !== null) {
            $this->fill($dto, $this->run($read, $id));
        }

        return $dto;
    }

    /**
     * Fills a DTO in with what "read" says of a record - the add DTO of a copy - only the properties named.
     *
     * @param class-string<MapsToRequest> $read
     * @param list<string> $properties
     *
     * @throws UseCaseFailed
     */
    public function copyInto(MapsToRequest $dto, string $read, string $id, array $properties): void
    {
        $record = array_intersect_key($this->properties($this->run($read, $id)), array_flip($properties));
        $this->fill($dto, $record);
    }

    /**
     * The DTO of a section another part adds to the edit form, carrying the current values its "read" returns.
     *
     * @throws UseCaseFailed
     */
    public function sectionDto(AsCrudFormSection $section, string $id): MapsToRequest
    {
        return $this->editDto($section->update, $section->read, $id);
    }

    /**
     * The DTO of a new record: its own defaults, with the plain values given for properties it has.
     *
     * @param class-string<MapsToRequest> $class
     * @param array<string, mixed> $with
     */
    public function newDto(string $class, array $with = []): MapsToRequest
    {
        $dto = $this->factory()->create($class);
        $this->prefill($dto, $with);

        return $dto;
    }

    /**
     * Gives a DTO the plain values given for properties it has (not its "id"); a value the property cannot take is
     * left out. Only what the caller allows: the add form gives it what the address says for its own fields.
     *
     * @param array<array-key, mixed> $with
     */
    public function prefill(object $dto, array $with): void
    {
        foreach ($with as $property => $value) {
            $property = (string) $property;
            if (
                !is_scalar($value) && $value !== null
                || $property === 'id'
                || !$this->propertyAccessor->isWritable($dto, $property)
            ) {
                continue;
            }

            try {
                $this->propertyAccessor->setValue($dto, $property, $value);
            } catch (PropertyAccessExceptionInterface) {
                // Not a value of that property (a text for a number): the DTO keeps its default.
            }
        }
    }

    /**
     * Puts the id of the record onto a DTO of a form, which has no field for it.
     */
    public function address(object $dto, string $id): void
    {
        try {
            $typed = DtoIds::of(DtoIds::typeOf($dto::class), $id);
        } catch (InvalidIdException $exception) {
            throw new UseCaseFailed(404, 'Not found.', null, $exception, 'crud.error.not_found');
        }
        // Made with it already (a readonly id given to the constructor): nothing to write.
        if ($this->propertyAccessor->isReadable($dto, 'id')) {
            $current = $this->propertyAccessor->getValue($dto, 'id');
            if ($current === $typed || (is_object($current) && $current == $typed)) {
                return;
            }
        }
        $this->propertyAccessor->setValue($dto, 'id', $typed);
    }

    /**
     * What a record read by a use case is by property: an array as it is; of an object its public properties and
     * what its getters say (getName(), isActive(), hasStock() - "name", "active", "stock"), a read model of private
     * properties too.
     *
     * @return array<string, mixed>
     */
    public static function propertiesOf(mixed $record): array
    {
        if (is_array($record)) {
            $properties = [];
            foreach ($record as $name => $value) {
                $properties[(string) $name] = $value;
            }

            return $properties;
        }
        if (!is_object($record)) {
            return [];
        }

        /** @var array<string, mixed> $properties the names of properties are strings */
        $properties = get_object_vars($record);
        foreach (new \ReflectionObject($record)->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->isStatic()
                || $method->getNumberOfRequiredParameters() > 0
                || preg_match('/^(get|is|has)([A-Z]\w*)$/', $method->getName(), $matches) !== 1
            ) {
                continue;
            }
            $name = lcfirst($matches[2]);
            if (!array_key_exists($name, $properties)) {
                $properties[$name] = $method->invoke($record);
            }
        }

        return $properties;
    }

    /**
     * Copies what was read onto the same-named properties of a form's DTO.
     */
    private function fill(MapsToRequest $dto, mixed $current): void
    {
        foreach ($this->properties($current) as $property => $value) {
            if ($property === 'id' || !$this->propertyAccessor->isWritable($dto, $property)) {
                continue;
            }
            // An object the property of the DTO is typed for goes as it is: an enum to an enum, a date to a date, a
            // value object to its own class. Anything else is text, as in JSON - an id (a Uuid), an enum to a string.
            if (!is_object($value) || !$this->accepts($dto, $property, $value)) {
                $value = is_array($value) ? array_map($this->text(...), $value) : $this->text($value);
                // Only plain values and lists of them: anything structured is mapped by FillsFromRecord.
                if (!$this->isPlain($value)) {
                    continue;
                }
            }

            try {
                $this->propertyAccessor->setValue($dto, $property, $value);
            } catch (PropertyAccessExceptionInterface) {
                // Not a value of that property (a text for an enum of another kind): left to FillsFromRecord.
            }
        }
        if ($dto instanceof FillsFromRecord && is_object($current)) {
            $dto->fillFrom($current);
        }
    }

    /**
     * Whether the property of the DTO is declared with a type the object is of; an untyped one (or one only a setter
     * has) is not - it gets text, as before.
     */
    private function accepts(object $dto, string $property, object $value): bool
    {
        if (!property_exists($dto, $property)) {
            return false;
        }
        $type = new \ReflectionProperty($dto, $property)->getType();
        $types = match (true) {
            $type instanceof \ReflectionNamedType => [$type],
            $type instanceof \ReflectionUnionType => $type->getTypes(),
            default => [],
        };
        foreach ($types as $named) {
            if (!$named instanceof \ReflectionNamedType) {
                continue;
            }
            $name = $named->getName();
            if ($name === 'object' || (!$named->isBuiltin() && $value instanceof $name)) {
                return true;
            }
        }

        return false;
    }

    private function text(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            default => $value,
        };
    }

    private function isPlain(mixed $value): bool
    {
        if (is_array($value)) {
            return array_is_list($value) && array_all($value, static fn (mixed $entry): bool => is_scalar($entry));
        }

        return is_scalar($value) || $value === null;
    }

    /**
     * @param class-string<MapsToRequest>|null $class
     */
    private function dtoFor(?string $class, string $id): MapsToRequest
    {
        if ($class === null) {
            throw new LogicException('The resource does not support this operation.');
        }

        try {
            return $this->factory()->create($class, $id);
        } catch (InvalidIdException $exception) {
            // No id of the type of the DTO (a typo in an address): nothing has it.
            throw new UseCaseFailed(404, 'Not found.', null, $exception, 'crud.error.not_found');
        }
    }

    private function factory(): DtoFactory
    {
        return $this->factory ?? new ConstructorDtoFactory($this->propertyAccessor);
    }
}
