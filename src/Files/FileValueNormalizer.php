<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\TypedValue;
use Byfareska\Crud\Value\ValueNormalizer;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * A file in a value of a record is shown as one - an image as its thumbnail, another file as a link: the reference of
 * a storage ("public://…"), an entry of a list of a column of JSON ({"ref": "…"}), an object of a file (FileRecord, or
 * one a FileRecordMapper knows) and an item of a gallery (ProvidesFile). TypedValue "image" or "file", whose value is
 * the reference; the blocks print it by crud_file_url() and crud_file().
 */
#[AsTaggedItem(priority: -50)]
final readonly class FileValueNormalizer implements ValueNormalizer
{
    public function __construct(
        private FileStorages $storages,
        private FileRecordMappers $mappers,
    ) {
    }

    public function supports(mixed $value): bool
    {
        return $this->fileOf($value) instanceof StoredFile;
    }

    public function normalize(mixed $value, CrudValues $values): mixed
    {
        $file = $this->fileOf($value);
        if (!$file instanceof StoredFile) {
            return null;
        }

        return new TypedValue($file->isImage() ? 'image' : 'file', (string) $file->reference);
    }

    private function fileOf(mixed $value): ?StoredFile
    {
        if (is_array($value) && is_string($value['ref'] ?? null)) {
            $value = $value['ref'];
        }
        if (is_string($value)) {
            $reference = str_contains($value, '://') ? FileReference::tryParse($value) : null;

            return $reference instanceof FileReference && $this->storages->has($reference->storage)
                ? StoredFile::of($reference)
                : null;
        }
        if ($value instanceof FileReference) {
            return StoredFile::of($value);
        }
        if ($value instanceof ProvidesFile) {
            return StoredFile::of($value->fileItem()->reference);
        }

        return is_object($value) ? $this->mappers->toStoredFile($value) : null;
    }
}
