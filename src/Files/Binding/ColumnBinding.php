<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Binding;

use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileReference;
use Stringable;

/**
 * The files of a field in a column of the record itself - an entity, a DTO, a row of an API:
 *
 * - one file: its reference, "public://ab/cd/x7k2.jpg" (null without one);
 * - many: a list of references, ["public://…", "public://…"], or - when the field says something of each ("meta":
 *   alt, caption) - a list of objects, [{"ref": "public://…", "alt": "…"}] (a column of JSON).
 *
 * It reads either form of a list, and a FileReference or a Stringable as its text.
 */
final readonly class ColumnBinding implements FileBinding
{
    /**
     * @param list<string> $meta what is written of each file of a list beside its reference
     */
    public function __construct(
        private bool $multiple = false,
        private array $meta = [],
    ) {
    }

    public function read(mixed $value): array
    {
        if (in_array($value, [null, '', []], true)) {
            return [];
        }
        $items = [];
        foreach (is_iterable($value) ? $value : [$value] as $entry) {
            $item = $this->item($entry);
            if ($item instanceof FileItem) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function write(array $items, mixed $previous): mixed
    {
        if (!$this->multiple) {
            return isset($items[0]) ? (string) $items[0]->reference : null;
        }
        if ($this->meta === []) {
            return array_map(static fn (FileItem $item): string => (string) $item->reference, $items);
        }

        return array_map(
            fn (FileItem $item): array => ['ref' => (string) $item->reference] + array_filter(
                array_intersect_key($item->meta, array_flip($this->meta)),
                static fn (string $value): bool => $value !== '',
            ),
            $items,
        );
    }

    /** A reference, as text or an object; an object of a list with "ref" and what else it says. */
    private function item(mixed $entry): ?FileItem
    {
        if ($entry instanceof FileReference) {
            return new FileItem($entry);
        }
        if (is_string($entry) || $entry instanceof Stringable) {
            $reference = FileReference::tryParse((string) $entry);

            return $reference instanceof FileReference ? new FileItem($reference) : null;
        }
        if (!is_array($entry) || !is_string($entry['ref'] ?? null)) {
            return null;
        }
        $reference = FileReference::tryParse($entry['ref']);
        if (!$reference instanceof FileReference) {
            return null;
        }
        $meta = [];
        foreach ($entry as $name => $value) {
            if ($name !== 'ref' && is_string($name) && is_scalar($value)) {
                $meta[$name] = (string) $value;
            }
        }

        return new FileItem($reference, $meta);
    }
}
