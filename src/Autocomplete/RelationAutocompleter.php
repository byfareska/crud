<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearches;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\FieldFilter;
use Byfareska\Crud\Driver\Doctrine\RelationFilterField;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Value\CrudValues;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\Autocomplete\AutocompleteResults;
use Symfony\UX\Autocomplete\Controller\AutocompleteController;
use Symfony\UX\Autocomplete\OptionsAwareAutocompleterInterface;

/**
 * The entities a relation filter of a list can be set to, found by what is typed (symfony/ux-autocomplete, alias
 * "byfareska_crud_relation"); the field says which resource and relation it is about
 * ({@see AutocompleteRelationFilterField}). Only for those who may see that list.
 *
 * Every word typed has to be in one of the fields the list of the related entity searches (its #[AsCrud] with a
 * DoctrineSource, of the same panel first), else in one of its texts. An entity is shown as its text (__toString()).
 * Only those the DoctrineScopes of the related entity's resource (of the same panel first) leave are offered; for an
 * entity without a resource, those the scopes of every resource (DoctrineScope::EVERY) leave - see DoctrineRelations.
 * Without a list to go by, no text that looks secret (AsCrud::looksSecret()) is searched.
 */
final class RelationAutocompleter implements OptionsAwareAutocompleterInterface
{
    public const string ALIAS = 'byfareska_crud_relation';

    public const string RESOURCE = 'resource';

    public const string FIELD = 'field';

    /** The key of "extra_options" of a filter giving how many entities a page of the field has: 1 to 100. */
    public const string PER_PAGE = 'per_page';

    private const int DEFAULT_PER_PAGE = 10;

    private const array TEXT_TYPES = ['string', 'text', 'ascii_string'];

    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(
        private readonly CrudRegistry $registry,
        private readonly CrudAccess $access,
        private readonly ManagerRegistry $doctrine,
        private readonly CrudValues $values,
        private readonly DoctrineEntityNormalizer $entities,
        private readonly DoctrineRelations $relations,
        private readonly DoctrineSearches $searches,
        private readonly ?RelationChoiceLabels $labels = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $extra = $options[AutocompleteController::EXTRA_OPTIONS] ?? [];
        $this->options = [];
        foreach (is_array($extra) ? $extra : [] as $name => $value) {
            $this->options[(string) $name] = $value;
        }
    }

    public function isGranted(Security $security): bool
    {
        $crud = $this->crud();

        return $crud instanceof AsCrud && $this->access->isGranted($crud, CrudOperation::List);
    }

    public function fetchResults(string $query, int $page): AutocompleteResults
    {
        $crud = $this->crud() ?? throw new NotFoundHttpException('No relation filter of a list is asked for.');
        // Also without symfony/security-bundle, which is what asks isGranted().
        $this->access->assertGranted($crud, CrudOperation::List);
        $target = $this->target($crud) ?? throw new NotFoundHttpException('No relation filter of a list is asked for.');
        // Within the scopes of the related entity's resource (the one the source names for the filter), else within
        // those of every resource.
        $relation = $this->option(RelationFilterField::FILTER) ?? $this->option(self::FIELD);
        $entities = $this->relations->query($crud, $target, $relation);
        $metadata = $entities->metadata;
        $builder = $entities->query;
        $searched = $this->searched($crud, $metadata);
        $related = $this->relations->resourceOf($crud, $target, $relation);
        // Found as its own list finds it: with the search of the list (DoctrineSearch).
        $text = trim($query);
        if ($searched !== [] && $text !== '') {
            $source = $related?->source;
            $this->searches->of($source instanceof DoctrineSource ? $source : null)->apply($entities, $searched, $text);
        }
        foreach ([...array_slice($searched, 0, 1), ...$metadata->getIdentifierFieldNames()] as $ordered) {
            $builder->addOrderBy('e.' . $ordered, 'ASC');
        }
        // One more than a page: whether there is a next one, without counting them all.
        $page = max(1, $page);
        $perPage = $this->perPage();
        $builder->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage + 1);
        /** @var list<object> $found */
        $found = $builder->getQuery()->getResult();

        $results = [];
        foreach (array_slice($found, 0, $perPage) as $entity) {
            $id = $this->entities->idOf($metadata, $entity, $this->values);
            // "Surname, Name (VAT number)": a RelationChoiceLabel of the entity, else its text (__toString()).
            $text = $this->labels?->of($entity) ?? $this->values->normalize($entity);
            $results[] = ['value' => $id, 'text' => is_scalar($text) ? (string) $text : (string) $id];
        }

        return new AutocompleteResults($results, count($found) > $perPage);
    }

    /**
     * The resource whose relation filter asks - one of entities, with that relation among its filters.
     */
    private function crud(): ?AsCrud
    {
        $resource = $this->option(self::RESOURCE);
        $field = $this->option(self::FIELD);
        if ($resource === null || $field === null) {
            return null;
        }

        try {
            $crud = $this->registry->get($resource);
        } catch (InvalidArgumentException) {
            return null;
        }
        return $this->target($crud) !== null ? $crud : null;
    }

    /**
     * The entity the relation filter is a choice of: of the filter of the resource whose relation the field is - a
     * filter named by its relation ("category"), one of another name (`'author' => new FieldFilter('createdBy')`) or
     * of a relation further away (`new FieldFilter('category.parent')`), each relation of the path walked from the
     * resource's entity.
     *
     * @return class-string|null
     */
    private function target(AsCrud $crud): ?string
    {
        $field = $this->option(self::FIELD);
        $source = $crud->source;
        if ($field === null || !$source instanceof DoctrineSource) {
            return null;
        }

        // The filter that asks, by its name; without one (a field of the application's own), the first one of the
        // relation.
        $filter = $this->option(RelationFilterField::FILTER);
        foreach ($source->filterTypes() as $name => $type) {
            if ($filter !== null && (string) $name !== $filter) {
                continue;
            }
            $path = explode('.', $type instanceof FieldFilter ? $type->field : (string) $name);
            if (end($path) !== $field) {
                continue;
            }
            $entity = $source->entity;
            foreach ($path as $relation) {
                $metadata = $this->metadata($entity);
                if (!$metadata->hasAssociation($relation)) {
                    continue 2;
                }
                $entity = $metadata->getAssociationTargetClass($relation);
            }

            return $entity;
        }

        return null;
    }

    /**
     * What the typed words are looked for in: the search of the related entity's own list, else its texts.
     *
     * @param ClassMetadata<object> $target
     *
     * @return list<string>
     */
    private function searched(AsCrud $crud, ClassMetadata $target): array
    {
        $lists = array_filter(
            $this->relations->resourcesOf($crud, $target->getName()),
            static fn (AsCrud $other): bool => $other->source instanceof DoctrineSource
                && $other->source->search !== [],
        );
        $source = (array_values($lists)[0] ?? null)?->source;
        // A field of a related entity of its own ("parent.name") is not looked into here. Without a list to go by,
        // its texts - but none that looks secret (a password hash, a token): typing would tell what they start with.
        $fields = $source instanceof DoctrineSource
            ? $source->search
            : array_values(array_filter(
                $target->getFieldNames(),
                static fn (string $field): bool => !AsCrud::looksSecret($field),
            ));

        return array_values(array_filter($fields, static fn (string $field): bool => $target->hasField($field)
            && in_array($target->getTypeOfField($field), self::TEXT_TYPES, true)
            && $target->getFieldMapping($field)->enumType === null));
    }

    /** How many entities a page has: "per_page" of the filter's extra_options, 1 to 100. */
    private function perPage(): int
    {
        $given = $this->options[self::PER_PAGE] ?? null;

        return is_int($given) && $given >= 1 && $given <= 100 ? $given : self::DEFAULT_PER_PAGE;
    }

    private function option(string $name): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param class-string $entity
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $entity): ClassMetadata
    {
        return $this->manager($entity)->getClassMetadata($entity);
    }

    /**
     * @param class-string $entity
     */
    private function manager(string $entity): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass($entity);

        return $manager instanceof EntityManagerInterface
            ? $manager
            : throw new LogicException(sprintf('"%s" is not an entity of Doctrine ORM.', $entity));
    }
}
