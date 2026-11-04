<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Binding;

use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\ProvidesFile;
use Closure;
use LogicException;
use ReflectionClass;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * The files of a field as items of the application's own - the gallery of a product as ProductImage, each with its
 * file, its position and its "alt" (OneToMany, orphanRemoval, #[OrderBy(['position' => 'ASC'])]). An item of a file
 * still there is kept and given its new position; one of a new file is made; one of a file taken away is left out
 * (the field has "by_reference" false: the record's removeImage() is called, and orphanRemoval deletes it).
 *
 * A new item is made by $factory, or by its constructor when it takes no arguments; the record's addImage() then
 * gives it its product.
 */
final readonly class ItemCollectionBinding implements FileBinding
{
    private FileBinding $fileBinding;

    private PropertyAccessorInterface $accessor;

    /**
     * @param class-string $itemClass
     * @param string $file the property of an item holding its file
     * @param string|null $position the property of an item holding its place in the field, from 0; null = none
     * @param array<string, string> $meta the properties of an item holding what the field says of its file, by name:
     *     ['alt' => 'alt', 'caption' => 'description']
     * @param FileBinding|null $fileBinding how the property $file holds the file; null = its reference (ColumnBinding)
     * @param (Closure(FileItem): object)|null $factory makes the item of a new file
     */
    public function __construct(
        private string $itemClass,
        private string $file = 'file',
        private ?string $position = 'position',
        private array $meta = [],
        ?FileBinding $fileBinding = null,
        private ?Closure $factory = null,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->fileBinding = $fileBinding ?? new ColumnBinding();
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function read(mixed $value): array
    {
        $items = [];
        foreach ($this->sorted($value) as $item) {
            $fileItem = $this->itemOf($item);
            if ($fileItem instanceof FileItem) {
                $items[] = $fileItem;
            }
        }

        return $items;
    }

    public function write(array $items, mixed $previous): mixed
    {
        // The items the record had, by the reference of their file.
        $had = [];
        foreach ($this->sorted($previous) as $item) {
            $fileItem = $this->itemOf($item);
            if ($fileItem instanceof FileItem) {
                $had[(string) $fileItem->reference][] = $item;
            }
        }

        $written = [];
        foreach ($items as $position => $fileItem) {
            $key = (string) $fileItem->reference;
            $item = isset($had[$key]) ? array_shift($had[$key]) : null;
            if ($item === null) {
                $item = $this->make($fileItem);
                $this->accessor->setValue($item, $this->file, $this->fileBinding->write([$fileItem], null));
            }
            if ($this->position !== null) {
                $this->accessor->setValue($item, $this->position, $position);
            }
            foreach ($this->meta as $name => $property) {
                $this->set($item, $property, $fileItem->meta($name));
            }
            $written[] = $item;
        }

        return $written;
    }

    private function itemOf(object $item): ?FileItem
    {
        if ($item instanceof ProvidesFile) {
            return $item->fileItem();
        }
        if (!$this->accessor->isReadable($item, $this->file)) {
            return null;
        }
        $file = $this->fileBinding->read($this->accessor->getValue($item, $this->file))[0] ?? null;
        if (!$file instanceof FileItem) {
            return null;
        }
        $meta = [];
        foreach ($this->meta as $name => $property) {
            $value = $this->accessor->isReadable($item, $property) ? $this->accessor->getValue($item, $property) : null;
            if (is_scalar($value) && (string) $value !== '') {
                $meta[$name] = (string) $value;
            }
        }

        return new FileItem($file->reference, $meta);
    }

    /**
     * The items of a value, by their position.
     *
     * @return list<object>
     */
    private function sorted(mixed $value): array
    {
        $items = is_iterable($value) ? array_values(array_filter([...$value], is_object(...))) : [];
        if ($this->position !== null) {
            $position = $this->position;
            usort($items, fn (object $a, object $b): int => $this->positionOf($a, $position)
                <=> $this->positionOf($b, $position));
        }

        return $items;
    }

    private function positionOf(object $item, string $position): int
    {
        $value = $this->accessor->isReadable($item, $position) ? $this->accessor->getValue($item, $position) : null;

        return is_numeric($value) ? (int) $value : PHP_INT_MAX;
    }

    private function make(FileItem $fileItem): object
    {
        if ($this->factory instanceof Closure) {
            return ($this->factory)($fileItem);
        }
        $constructor = new ReflectionClass($this->itemClass)->getConstructor();
        if ($constructor instanceof \ReflectionMethod && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new LogicException(sprintf(
                'A new %s is made with arguments: give the field an "item_factory" (fn (FileItem $file) => new %1$s('
                . '...)).',
                $this->itemClass,
            ));
        }

        return new $this->itemClass();
    }

    /** Nothing said is null where the property takes it, else "". */
    private function set(object $item, string $property, string $value): void
    {
        if ($value !== '') {
            $this->accessor->setValue($item, $property, $value);

            return;
        }
        try {
            $this->accessor->setValue($item, $property, null);
        } catch (InvalidArgumentException) {
            $this->accessor->setValue($item, $property, '');
        }
    }
}
