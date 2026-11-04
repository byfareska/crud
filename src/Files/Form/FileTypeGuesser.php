<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Form;

use Byfareska\Crud\Attribute\CrudFile;
use Byfareska\Crud\Files\FileRecord;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Traversable;

/**
 * A property marked #[CrudFile] is a field of files, in a form whose fields are guessed: before the guess of Doctrine
 * (a text field of a string column, a choice of a relation).
 */
final readonly class FileTypeGuesser implements FormTypeGuesserInterface
{
    public function guessType(string $class, string $property): ?TypeGuess
    {
        $reflection = $this->property($class, $property);
        $attribute = $reflection?->getAttributes(CrudFile::class)[0] ?? null;
        if (!$reflection instanceof \ReflectionProperty || $attribute === null) {
            return null;
        }

        return new TypeGuess(
            CrudFileType::class,
            self::options($attribute->newInstance(), $reflection),
            Guess::VERY_HIGH_CONFIDENCE,
        );
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function options(CrudFile $file, ReflectionProperty $property): array
    {
        $type = $property->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
        $list = $typeName === 'array' || $typeName === 'iterable'
            || ($typeName !== null && is_a($typeName, Traversable::class, true));
        $multiple = $file->multiple ?? $list;

        $binding = $file->binding ?? match (true) {
            $typeName !== null && !$list && is_a($typeName, FileRecord::class, true) => 'record',
            $list && $file->class !== null => is_a($file->class, FileRecord::class, true)
                ? 'record'
                : 'items',
            default => 'column',
        };
        $options = [
            'multiple' => $multiple,
            'accept' => $file->accept,
            'meta' => $file->meta,
            'binding' => $binding,
        ];
        if ($file->storage !== null) {
            $options['storage'] = $file->storage;
        }
        if ($binding === 'record') {
            $options['record_class'] = $file->class ?? $typeName;
        }
        if ($binding === 'items') {
            $options['item_class'] = $file->class;
        }

        return $file->options + $options;
    }

    private function property(string $class, string $property): ?ReflectionProperty
    {
        if (!class_exists($class) || !property_exists($class, $property)) {
            return null;
        }

        return new ReflectionProperty($class, $property);
    }
}
