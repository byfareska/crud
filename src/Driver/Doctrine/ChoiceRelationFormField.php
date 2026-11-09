<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * A relation of a guessed form as a choice of every related entity - for a few of them; see {@see RelationFormField}.
 * The choices are named as the lists name them (no __toString() needed), and a relation to the same entity (a parent)
 * never offers the record being edited.
 */
final readonly class ChoiceRelationFormField implements RelationFormField
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private ?DoctrineRecordLabels $labels = null,
    ) {
    }

    public function options(AsCrud $crud, string $field, string $entity, ?object $record, ?string $id): array
    {
        $options = [];
        if ($this->labels !== null) {
            $options['choice_label'] = $this->labels->of(...);
        }
        $manager = $record === null ? null : $this->doctrine->getManagerForClass($record::class);
        if ($manager instanceof EntityManagerInterface && $manager->getClassMetadata($record::class)->getName() === $entity) {
            $identifier = $manager->getClassMetadata($entity)->getIdentifierValues($record);
            $options['query_builder'] = static function (EntityRepository $repository) use ($identifier): QueryBuilder {
                $builder = $repository->createQueryBuilder('e');
                foreach ($identifier as $name => $value) {
                    $builder->andWhere(sprintf('e.%1$s != :self_%1$s', $name))->setParameter('self_' . $name, $value);
                }

                return $builder;
            };
        }

        return $options;
    }
}
