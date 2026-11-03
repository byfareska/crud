<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

use BackedEnum;
use Byfareska\Crud\UseCase\InvalidIdException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * The id of an address (text) as the "id" of a DTO takes it: by the type of the parameter or the property - an int,
 * a float, a text, a backed enum, an object made from its text (Uuid::fromString(), Ulid, a value object with
 * fromString()). A DTO shared with an API keeps `public readonly Uuid $id`.
 */
final class DtoIds
{
    /**
     * @throws InvalidIdException the text is no id of that type (a typo in an address): nothing has it
     */
    public static function of(?ReflectionType $type, string $id): mixed
    {
        $names = match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_map(
                static fn (ReflectionType $one): string => $one instanceof ReflectionNamedType ? $one->getName() : '',
                $type->getTypes(),
            ),
            default => ['mixed'],
        };
        // A text is taken as it is, where it may be one.
        if (array_intersect($names, ['string', 'mixed']) !== []) {
            return $id;
        }

        foreach ($names as $name) {
            $value = self::as($name, $id);
            if ($value !== null) {
                return $value;
            }
        }

        throw new InvalidIdException(sprintf('"%s" is no id of the type %s.', $id, implode('|', $names)));
    }

    /**
     * The type of the "id" of a DTO: of its property, else of the parameter of its constructor; null = none.
     *
     * @param class-string $class
     */
    public static function typeOf(string $class): ?ReflectionType
    {
        if (property_exists($class, 'id')) {
            return new ReflectionProperty($class, 'id')->getType();
        }
        foreach (new \ReflectionClass($class)->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() === 'id') {
                return $parameter->getType();
            }
        }

        return null;
    }

    private static function as(string $type, string $id): mixed
    {
        return match (true) {
            $type === 'int' => preg_match('/^-?\d+$/', $id) === 1 ? (int) $id : null,
            $type === 'float' => is_numeric($id) ? (float) $id : null,
            is_subclass_of($type, BackedEnum::class) => self::enum($type, $id),
            class_exists($type) && method_exists($type, 'fromString') => self::made($type, $id),
            default => null,
        };
    }

    /**
     * @param class-string<BackedEnum> $type
     */
    private static function enum(string $type, string $id): ?BackedEnum
    {
        try {
            return $type::tryFrom($id) ?? (preg_match('/^-?\d+$/', $id) === 1 ? $type::tryFrom((int) $id) : null);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param class-string $type
     */
    private static function made(string $type, string $id): ?object
    {
        try {
            $made = $type::fromString($id);
        } catch (Throwable) {
            return null;
        }

        return is_object($made) ? $made : null;
    }
}
