<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use LogicException;

/**
 * A query of the entities of a {@see DoctrineSource} while it is built: what a {@see DoctrineScope} narrows - the list
 * ({@see DoctrineListQuery}), a record found by its id, the entities a relation filter offers. The entity is "e".
 */
class DoctrineQuery
{
    private int $parameters = 0;

    /**
     * @param ClassMetadata<object> $metadata of the entity
     */
    public function __construct(
        public readonly AsCrud $crud,
        public readonly QueryBuilder $query,
        public readonly ClassMetadata $metadata,
    ) {
    }

    /**
     * The expression of a field of the entity ("e.price"), of an embedded object of it ("address.city":
     * "e.address.city"), or of a related entity, however far ("category.name", "customer.country.name"), each relation
     * of the way joined once ("r_customer", "r_customer_country").
     *
     * @throws LogicException the part before the dot is no relation nor embedded object
     */
    public function path(string $field): string
    {
        if (!str_contains($field, '.') || $this->metadata->hasField($field)) {
            return 'e.' . $field;
        }

        $parts = explode('.', $field);
        $metadata = $this->metadata;
        $alias = null;
        while (count($parts) > 1 && $metadata->hasAssociation($parts[0])) {
            $relation = array_shift($parts);
            $alias = $alias === null ? $this->join($relation) : $this->joinFrom($alias, $relation);
            $metadata = $this->query->getEntityManager()->getClassMetadata(
                $metadata->getAssociationTargetClass($relation),
            );
        }
        if ($alias === null) {
            throw new LogicException(sprintf('"%s" of "%s" is no relation.', $parts[0], $this->metadata->getName()));
        }

        return $alias . '.' . implode('.', $parts);
    }

    /**
     * The metadata of the entity that has the field and its name there: "customer.country.name" is "name" of the
     * country, "address.city" (embedded) is itself.
     *
     * @return array{ClassMetadata<object>, string}
     */
    public function metadataOf(string $field): array
    {
        $metadata = $this->metadata;
        $parts = explode('.', $field);
        while (
            count($parts) > 1
            && !$metadata->hasField(implode('.', $parts))
            && $metadata->hasAssociation($parts[0])
        ) {
            $metadata = $this->query->getEntityManager()->getClassMetadata(
                $metadata->getAssociationTargetClass(array_shift($parts)),
            );
        }

        return [$metadata, implode('.', $parts)];
    }

    /**
     * A relation of a joined entity ("r_customer" . "country": "r_customer_country"), left-joined once.
     */
    private function joinFrom(string $alias, string $relation): string
    {
        $joined = $alias . '_' . $relation;
        if (!in_array($joined, $this->query->getAllAliases(), true)) {
            $this->query->leftJoin($alias . '.' . $relation, $joined);
        }

        return $joined;
    }

    /**
     * The alias of a relation of the entity ("r_category"), left-joined once.
     *
     * @throws LogicException it is no relation
     */
    public function join(string $relation): string
    {
        if (!$this->metadata->hasAssociation($relation)) {
            throw new LogicException(sprintf('"%s" of "%s" is no relation.', $relation, $this->metadata->getName()));
        }
        $alias = 'r_' . $relation;
        if (!in_array($alias, $this->query->getAllAliases(), true)) {
            $this->query->leftJoin('e.' . $relation, $alias);
        }

        return $alias;
    }

    /** Whether path() can tell the expression of the field. */
    public function knows(string $field): bool
    {
        if ($this->metadata->hasField($field) || $this->metadata->hasAssociation($field)) {
            return true;
        }
        [$metadata, $name] = $this->metadataOf($field);

        return $metadata !== $this->metadata && ($metadata->hasField($name) || $metadata->hasAssociation($name));
    }

    /**
     * The Doctrine type of a field of the entity, of a related one ("category.name") or of an embedded object
     * ("address.city"); null for a relation itself or for what is no field.
     */
    public function typeOf(string $field): ?string
    {
        [$metadata, $name] = $this->metadataOf($field);

        return $metadata->hasField($name) ? $metadata->getTypeOfField($name) : null;
    }

    /** A name of a query parameter not used yet, e.g. "price_from_3". */
    public function parameter(string $hint = 'p'): string
    {
        return sprintf('%s_%d', preg_replace('/\W/', '_', $hint), ++$this->parameters);
    }

    /**
     * Whether an entity can come in more than one row: a join of a collection, of an entity by a condition
     * ("JOIN App\Entity\Order o WITH o.product = e"), another root. A page of such a query is one of distinct entities.
     */
    public function joinsMany(): bool
    {
        if (count($this->query->getRootAliases()) > 1) {
            return true;
        }
        $manager = $this->query->getEntityManager();
        $classes = [$this->query->getRootAliases()[0] => $this->metadata];
        /** @var array<string, list<Join>> $joins */
        $joins = $this->query->getDQLPart('join');
        foreach ($joins as $ofRoot) {
            foreach ($ofRoot as $join) {
                $path = explode('.', $join->getJoin(), 2);
                $owner = $classes[$path[0]] ?? null;
                $relation = $path[1] ?? null;
                if (
                    !$owner instanceof ClassMetadata
                    || $relation === null
                    || !$owner->hasAssociation($relation)
                    || $owner->isCollectionValuedAssociation($relation)
                ) {
                    return true;
                }
                $classes[(string) $join->getAlias()] = $manager->getClassMetadata(
                    $owner->getAssociationTargetClass($relation),
                );
            }
        }

        return false;
    }
}
