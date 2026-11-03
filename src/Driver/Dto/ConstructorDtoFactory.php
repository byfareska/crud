<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

use Byfareska\Crud\UseCase\MapsToRequest;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * The bundle's {@see DtoFactory}: `new $class(id: $id)` when the constructor takes an "id" (a readonly one), else
 * `new $class()` and the id written to its property "id" - of the type it has (DtoIds: an int, a Uuid...).
 */
final readonly class ConstructorDtoFactory implements DtoFactory
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function create(string $class, ?string $id = null): MapsToRequest
    {
        $takesId = array_any(
            new ReflectionClass($class)->getConstructor()?->getParameters() ?? [],
            static fn (\ReflectionParameter $parameter): bool => $parameter->getName() === 'id',
        );
        $typed = $id === null ? null : DtoIds::of(DtoIds::typeOf($class), $id);
        if ($id !== null && $takesId) {
            return new $class(id: $typed);
        }

        $dto = new $class();
        if ($id !== null) {
            $this->propertyAccessor->setValue($dto, 'id', $typed);
        }

        return $dto;
    }
}
