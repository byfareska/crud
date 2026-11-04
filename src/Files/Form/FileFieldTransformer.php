<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Form;

use Byfareska\Crud\Files\Binding\FileBinding;
use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileReferenceSigner;
use Byfareska\Crud\Files\UploadProfile;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The value of a field of files in the browser - the JSON of its hidden input, [{"ref", "sig", "meta"}] - and back.
 * A file the record did not have is taken only with the signature the server gave it (an upload, the library): the
 * browser cannot name a file of another record, nor of a storage the field does not pick from, nor of a kind it does
 * not take.
 *
 * @implements DataTransformerInterface<mixed, string>
 */
final class FileFieldTransformer implements DataTransformerInterface
{
    /** What the files of the field say of themselves at most, in characters. */
    private const int META_LENGTH = 1000;

    /** What the record held: the objects it had are kept. */
    private mixed $original = null;

    /** @var array<string, true> the references the record had */
    private array $known = [];

    /** @var list<FileItem> the files of the field, as last submitted */
    private array $submitted = [];

    /**
     * @param list<string> $meta what the field says of each file
     */
    public function __construct(
        private readonly FileBinding $binding,
        private readonly UploadProfile $profile,
        private readonly bool $multiple,
        private readonly array $meta,
        private readonly FileReferenceSigner $signer,
        private readonly FileLibraries $libraries,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function transform(mixed $value): string
    {
        $this->original = $value;
        $items = $this->binding->read($value);
        $this->known = [];
        foreach ($items as $item) {
            $this->known[(string) $item->reference] = true;
        }

        return self::encode($items);
    }

    public function reverseTransform(mixed $value): mixed
    {
        $items = [];
        $seen = [];
        foreach ($this->entries($value) as $entry) {
            $item = $this->item($entry);
            if (!isset($seen[(string) $item->reference])) {
                $seen[(string) $item->reference] = true;
                $items[] = $item;
            }
        }
        if (!$this->multiple) {
            $items = array_slice($items, 0, 1);
        }
        $this->submitted = $items;

        return $this->binding->write($items, $this->original);
    }

    /**
     * The files of the field as last submitted.
     *
     * @return list<FileItem>
     */
    public function submitted(): array
    {
        return $this->submitted;
    }

    /**
     * @param list<FileItem> $items
     */
    public static function encode(array $items): string
    {
        return json_encode(array_map(
            static fn (FileItem $item): array => $item->meta === []
                ? ['ref' => (string) $item->reference]
                : ['ref' => (string) $item->reference, 'meta' => $item->meta],
            $items,
        ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * What the browser sent: a list of objects.
     *
     * @return list<array<array-key, mixed>>
     */
    private function entries(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $entries = is_string($value) ? json_decode($value, true) : null;
        if (
            !is_array($entries)
            || !array_is_list($entries)
            || !array_all($entries, static fn (mixed $entry): bool => is_array($entry))
        ) {
            throw $this->refused('file.field.invalid');
        }

        /** @var list<array<array-key, mixed>> $entries */
        return $entries;
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function item(array $entry): FileItem
    {
        $reference = is_string($entry['ref'] ?? null) ? FileReference::tryParse($entry['ref']) : null;
        if (!$reference instanceof FileReference) {
            throw $this->refused('file.field.invalid');
        }
        if (!isset($this->known[(string) $reference])) {
            $this->assertMayBeChosen($reference, is_string($entry['sig'] ?? null) ? $entry['sig'] : '');
        }

        $meta = [];
        $said = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];
        foreach ($this->meta as $name) {
            $value = $said[$name] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $meta[$name] = mb_substr(trim((string) $value), 0, self::META_LENGTH);
            }
        }

        return new FileItem($reference, $meta);
    }

    /** A file the record did not have: given by the server, of a storage and a kind the field takes. */
    private function assertMayBeChosen(FileReference $reference, string $signature): void
    {
        if (
            $reference->isExternal()
            || !in_array($reference->storage, $this->profile->storages(), true)
            || !$this->signer->verify($reference, $signature)
        ) {
            throw $this->refused('file.field.invalid');
        }
        if ($this->profile->accept !== [] && !$this->profile->acceptsFile($this->libraries->describe($reference))) {
            throw $this->refused('file.field.kind');
        }
    }

    private function refused(string $key): TransformationFailedException
    {
        $message = $this->translator->trans($key);
        $failure = new TransformationFailedException($message);
        // Shown as it is: translated here, in the domain of the panel.
        $failure->setInvalidMessage($message);

        return $failure;
    }
}
