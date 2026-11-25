<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Byfareska\Crud\Autocomplete\RelationChoiceLabel;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Value\CrudValues;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use JsonSerializable;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Throwable;
use UnitEnum;

/**
 * What names an entity where it is shown as another record's relation - a cell of a list, the details, a choice of a
 * form or a filter, a result of the autocomplete. The first of:
 *
 * 1. a RelationChoiceLabel service of the application ("Kowalska, Anna (PL123)");
 * 2. the title of the resource of its entity (AsCrud "title", else its first column) - of the panel being shown first;
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
        private ?RequestStack $requests = null,
    ) {
    }

    /**
     * The resource whose title names the entities of a class where no RelationChoiceLabel does: the first of its
     * resources with title fields, of the panel being shown first; null = none.
     *
     * @param class-string $class
     */
    public function titledBy(string $class): ?AsCrud
    {
        return array_find($this->resourcesOf($class), static fn (AsCrud $crud): bool => $crud->titleFields() !== []);
    }

    /**
     * The resources of the entities of a class, those of the panel being shown (of the main request) first.
     *
     * @return list<AsCrud>
     */
    private function resourcesOf(string $class): array
    {
        $resources = array_values(array_filter(
            $this->registry->all(),
            static fn (AsCrud $crud): bool => $crud->source instanceof DoctrineSource
                && $crud->source->entity === $class,
        ));
        $panel = $this->requests?->getMainRequest()?->attributes->get(Scope::ATTRIBUTE);
        if (is_string($panel)) {
            $inPanel = static fn (AsCrud $crud): bool => $crud->scope === $panel;
            usort($resources, static fn (AsCrud $a, AsCrud $b): int => $inPanel($b) <=> $inPanel($a));
        }

        return $resources;
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

    /** The title of the first resource of the entity's class that has one (of the panel shown first); null = none. */
    private function titleOf(string $class, object $entity): ?string
    {
        foreach ($this->resourcesOf($class) as $crud) {
            $parts = [];
            foreach ($crud->titleFields() as $field) {
                try {
                    $value = $this->propertyAccessor->isReadable($entity, $field)
                        ? $this->propertyAccessor->getValue($entity, $field)
                        : null;
                } catch (Throwable) {
                    $value = null;
                }
                // An enum that translates itself by its translation, as the lists print it.
                if ($value instanceof UnitEnum) {
                    $value = $this->values->normalize($value);
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
