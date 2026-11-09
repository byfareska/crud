<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\RecordRef;
use Byfareska\Crud\Value\TypedValue;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * An id of one field as it is (7, a uuid); one of several fields as its parts in the order of the mapping, each
 * encoded (rawurlencode) and joined by "," - "12,3" for the third line of the order 12. Nothing of a part can be taken
 * for the separator, so a uuid or a text with "-" or "," in it is read back as it was.
 */
final readonly class DefaultDoctrineIdCodec implements DoctrineIdCodec
{
    private const string SEPARATOR = ',';

    private const array INTEGER_TYPES = ['integer', 'smallint', 'bigint'];

    public function encode(ClassMetadata $metadata, object $entity, CrudValues $values): ?string
    {
        $ids = $metadata->getIdentifierValues($entity);
        if ($ids === []) {
            return null;
        }
        $parts = [];
        foreach ($metadata->getIdentifierFieldNames() as $field) {
            $value = $values->normalize($ids[$field] ?? null);
            // A relation in the id (an order line: its order) is the id of the record it points to.
            if ($value instanceof TypedValue && $value->value instanceof RecordRef) {
                $value = $value->value->id;
            }
            if (!is_scalar($value) || $value === '') {
                return null;
            }
            $parts[] = (string) $value;
        }

        return count($parts) === 1 ? $parts[0] : implode(self::SEPARATOR, array_map(rawurlencode(...), $parts));
    }

    public function decode(ClassMetadata $metadata, string $id): ?array
    {
        $fields = $metadata->getIdentifierFieldNames();
        if ($fields === [] || $id === '') {
            return null;
        }
        $parts = count($fields) === 1 ? [$id] : array_map(rawurldecode(...), explode(self::SEPARATOR, $id));
        if (count($parts) !== count($fields)) {
            return null;
        }

        $criteria = [];
        foreach ($fields as $index => $field) {
            $part = $parts[$index];
            // A number in the address of a field of numbers; anything else names nothing.
            if (
                $part === ''
                || $metadata->hasField($field)
                && in_array($metadata->getTypeOfField($field), self::INTEGER_TYPES, true)
                && preg_match('/^-?\d+$/', $part) !== 1
            ) {
                return null;
            }
            $criteria[$field] = $part;
        }

        return $criteria;
    }
}
