<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\RecordRef;
use Byfareska\Crud\Value\TypedValue;
use Byfareska\Crud\Value\ValueNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * An entity of Doctrine ORM (which is no Stringable - that one is its text) as a reference to its record: its id
 * (DoctrineIdCodec) and what names it (DoctrineRecordLabels).
 */
#[AsTaggedItem(priority: -200)]
final readonly class DoctrineEntityNormalizer implements ValueNormalizer
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
        private ?DoctrineRecordLabels $labels = null,
    ) {
    }

    public function supports(mixed $value): bool
    {
        return is_object($value)
            && $this->doctrine->getManagerForClass($value::class) instanceof EntityManagerInterface;
    }

    /**
     * A relation: a TypedValue "relation" of a RecordRef - printed as what names the entity (DoctrineRecordLabels),
     * written in JSON as its id.
     */
    public function normalize(mixed $value, CrudValues $values): TypedValue
    {
        assert(is_object($value));
        $manager = $this->doctrine->getManagerForClass($value::class);
        assert($manager instanceof EntityManagerInterface);
        $id = $this->idOf($manager->getClassMetadata($value::class), $value, $values);

        return new TypedValue('relation', new RecordRef($id, $this->labels?->of($value) ?? (string) $id));
    }

    /**
     * The id of an entity, as an address carries it (DoctrineIdCodec).
     *
     * @param ClassMetadata<object> $metadata
     */
    public function idOf(ClassMetadata $metadata, object $entity, CrudValues $values): ?string
    {
        return $this->ids->encode($metadata, $entity, $values);
    }
}
