<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Byfareska\Crud\Autocomplete\RelationChoiceLabel;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Value\CrudValues;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use JsonSerializable;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Throwable;

/**
 * What names an entity where it is shown as another record's relation - a cell of a list, the details, a choice of a
 * form or a filter, a result of the autocomplete. The first of:
 *
 * 1. a RelationChoiceLabel service of the application ("Kowalska, Anna (PL123)");
 * 2. the title of the resource of its entity (AsCrud "title", else its first column);
 * 3. its __toString();
 * 4. its id.
 *
 * So an entity needs no __toString() to be a choice.
 */
final readonly class DoctrineRecordLabels
{
    /**
     * @param iterable<RelationChoiceLabel> $labels
     */
    public function __construct(
        private ManagerRegistry $doctrine,
        private CrudRegistry $registry,
        private PropertyAccessorInterface $propertyAccessor,
        private CrudValues $values,
        #[AutowireIterator(RelationChoiceLabel::TAG)]
        private iterable $labels = [],
        private DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
    ) {
    }

    public function of(object $entity): string
    {
        foreach ($this->labels as $label) {
            if ($label->supports($entity)) {
                return $label->label($entity);
            }
        }

        $manager = $this->doctrine->getManagerForClass($entity::class);
        $metadata = $manager instanceof EntityManagerInterface ? $manager->getClassMetadata($entity::class) : null;
        $title = $metadata instanceof ClassMetadata ? $this->titleOf($metadata->getName(), $entity) : null;
        if ($title !== null) {
            return $title;
        }
        if ($entity instanceof Stringable) {
            return (string) $entity;
        }

        return $metadata instanceof ClassMetadata ? (string) $this->ids->encode($metadata, $entity, $this->values) : '';
    }

    /** The title of the first resource of the entity's class that has one; null = none. */
    private function titleOf(string $class, object $entity): ?string
    {
        foreach ($this->registry->all() as $crud) {
            if (!$crud->source instanceof DoctrineSource || $crud->source->entity !== $class) {
                continue;
            }
            $parts = [];
            foreach ($crud->titleFields() as $field) {
                try {
                    $value = $this->propertyAccessor->isReadable($entity, $field)
                        ? $this->propertyAccessor->getValue($entity, $field)
                        : null;
                } catch (Throwable) {
                    $value = null;
                }
                if ($value instanceof JsonSerializable && !$value instanceof Stringable) {
                    $value = $value->jsonSerialize();
                }
                // A title of another relation (a category named by its parent) would go round: only plain values.
                $plain = is_scalar($value) || (
                    $value instanceof Stringable
                    && !$this->doctrine->getManagerForClass($value::class) instanceof ObjectManager
                );
                if ($plain) {
                    $text = trim((string) $value);
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        return null;
    }
}
