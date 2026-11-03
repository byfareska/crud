<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\ValueNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * An entity of Doctrine ORM (which is no Stringable - that one is its text) as its id (DoctrineIdCodec).
 */
#[AsTaggedItem(priority: -200)]
final readonly class DoctrineEntityNormalizer implements ValueNormalizer
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
    ) {
    }

    public function supports(mixed $value): bool
    {
        return is_object($value)
            && $this->doctrine->getManagerForClass($value::class) instanceof EntityManagerInterface;
    }

    public function normalize(mixed $value, CrudValues $values): ?string
    {
        assert(is_object($value));
        $manager = $this->doctrine->getManagerForClass($value::class);
        assert($manager instanceof EntityManagerInterface);

        return $this->idOf($manager->getClassMetadata($value::class), $value, $values);
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
