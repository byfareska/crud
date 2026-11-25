<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Throwable;

/**
 * The entities of a relation of a resource - another entity, which may have resources of its own - as the panel may
 * offer them: within the DoctrineScopes of the related entity's resource (of the same panel first), or, when it has
 * none, within the scopes of every resource (DoctrineScope::EVERY). What a relation filter or a relation of a guessed
 * form offers - as choices, or as one types - what they take when sent (choices()) and what an address may give a new
 * record (?with[category]=5) go through it, so none of them reaches an entity the panel keeps out of sight.
 *
 * An EVERY scope applied to an entity without a resource of its own gets a DoctrineQuery whose "crud" is the resource
 * the relation is of and whose "metadata" is that of the related entity: it narrows by what that entity has.
 */
final readonly class DoctrineRelations
{
    public function __construct(
        private CrudRegistry $registry,
        private ManagerRegistry $doctrine,
        private DoctrineScopes $scopes,
        private ?DoctrineManagers $managers = null,
        private DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
    ) {
    }

    /**
     * The resources of an entity, those of the panel of $from first.
     *
     * @param class-string $entity
     *
     * @return list<AsCrud>
     */
    public function resourcesOf(AsCrud $from, string $entity): array
    {
        $related = array_values(array_filter(
            $this->registry->all(),
            static fn (AsCrud $other): bool => $other->source instanceof DoctrineSource
                && $other->source->entity === $entity,
        ));
        $inPanel = static fn (AsCrud $other): bool => $other->scope === $from->scope;
        usort($related, static fn (AsCrud $a, AsCrud $b): int => $inPanel($b) <=> $inPanel($a));

        return $related;
    }

    /**
     * The resource whose scopes the entities of a relation are in: the one the source names for the relation
     * (DoctrineSource::$relations), else the first of the entity's - of the panel of $from first; null = none.
     *
     * @param class-string $entity
     */
    public function resourceOf(AsCrud $from, string $entity, ?string $relation = null): ?AsCrud
    {
        $named = $relation !== null && $from->source instanceof DoctrineSource
            ? $from->source->relations[$relation] ?? null
            : null;
        $related = $this->resourcesOf($from, $entity);
        if ($named !== null) {
            return array_find($related, static fn (AsCrud $crud): bool => $crud->name === $named)
                ?? throw new LogicException(sprintf(
                    'CRUD resource "%s": the relation "%s" is offered in the scopes of "%s", which is no resource '
                    . 'of %s.',
                    $from->name,
                    $relation,
                    $named,
                    $entity,
                ));
        }

        return $related[0] ?? null;
    }

    /**
     * A query of the entities of a relation (as "e"), narrowed by the scopes that apply to them.
     *
     * @param class-string $entity
     * @param string|null $relation the relation (or the path of it, "category.parent"), for DoctrineSource::$relations
     */
    public function query(AsCrud $from, string $entity, ?string $relation = null): DoctrineQuery
    {
        // From the database of the resource the relation is of (a tenant's), as its records are.
        $manager = $this->manager($from, $entity);
        $builder = $manager->createQueryBuilder()->select('e')->from($entity, 'e');
        $related = $this->resourceOf($from, $entity, $relation);
        $query = new DoctrineQuery($related ?? $from, $builder, $manager->getClassMetadata($entity));
        if ($related instanceof AsCrud) {
            $this->scopes->apply($query);
        } else {
            $this->scopes->applyEvery($query);
        }

        return $query;
    }

    /**
     * The query_builder of a choice of the entities of a relation (EntityType): what it offers and what it takes when
     * sent - those the scopes leave, and those in $kept (what the record has in the relation now, which stays its
     * value even if the scopes no longer leave it, though it cannot be newly chosen), never $leftOut (the record
     * being edited, not its own parent). A query of its own each call.
     *
     * @param class-string $entity
     * @param string|null $relation the relation (or the path of it), for DoctrineSource::$relations
     * @param iterable<object> $kept
     */
    public function choices(
        AsCrud $from,
        string $entity,
        ?string $relation = null,
        iterable $kept = [],
        ?object $leftOut = null,
    ): QueryBuilder {
        $query = $this->query($from, $entity, $relation);
        $builder = $query->query;
        // What the scopes narrowed it by (where() and andWhere() keep an Andx); nothing to keep the others besides.
        $scoped = $builder->getDQLPart('where');
        if ($scoped instanceof Andx || $scoped instanceof Orx) {
            $alternatives = [];
            foreach ($kept as $entityKept) {
                $is = $this->is($query, $entityKept);
                if ($is instanceof Andx) {
                    $alternatives[] = $is;
                }
            }
            if ($alternatives !== []) {
                $builder->resetDQLPart('where')->where($builder->expr()->orX($scoped, ...$alternatives));
            }
        }
        $is = $leftOut === null ? null : $this->is($query, $leftOut);
        if ($is instanceof Andx) {
            $builder->andWhere($builder->expr()->not($is));
        }

        return $builder;
    }

    /**
     * The condition the query's "e" is that entity by, its id field by field; null = not one of its entities, or one
     * not saved yet.
     */
    private function is(DoctrineQuery $query, object $entity): ?Andx
    {
        $metadata = $query->metadata;
        if (!$entity instanceof $metadata->name) {
            return null;
        }
        $identifier = $metadata->getIdentifierValues($entity);
        if ($identifier === [] || in_array(null, $identifier, true)) {
            return null;
        }
        $is = $query->query->expr()->andX();
        foreach ($identifier as $field => $value) {
            $parameter = $query->parameter('is');
            $is->add(sprintf('e.%s = :%s', $field, $parameter));
            $query->query->setParameter(
                $parameter,
                $value,
                $metadata->hasField($field) ? $metadata->getTypeOfField($field) : null,
            );
        }

        return $is;
    }

    /**
     * The entity of a relation by its id, if the scopes leave it; null for an id of another kind (a broken uuid).
     *
     * @param class-string $entity
     */
    public function find(AsCrud $from, string $entity, int|string $id, ?string $relation = null): ?object
    {
        $metadata = $this->manager($from, $entity)->getClassMetadata($entity);
        // What the id names (DoctrineIdCodec): its fields; nothing for an id of another kind.
        $criteria = $this->ids->decode($metadata, (string) $id);
        if ($criteria === null) {
            return null;
        }

        $query = $this->query($from, $entity, $relation);
        foreach ($criteria as $field => $value) {
            $parameter = $query->parameter('id');
            $type = $metadata->hasField($field) ? $metadata->getTypeOfField($field) : null;
            $query->query->andWhere(sprintf('e.%s = :%s', $field, $parameter))->setParameter($parameter, $value, $type);
        }
        $query->query->setMaxResults(1);

        try {
            $found = $query->query->getQuery()->getOneOrNullResult();
        } catch (Throwable) {
            return null;
        }

        return is_object($found) ? $found : null;
    }

    /**
     * @param class-string $entity
     */
    private function manager(AsCrud $from, string $entity): EntityManagerInterface
    {
        return ($this->managers ?? new DoctrineManagers($this->doctrine))->of($from, $entity);
    }
}
