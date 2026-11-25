<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Symfony\Component\HttpFoundation\Request;
use Byfareska\Crud\Driver\Doctrine\DoctrineOrder;
use Doctrine\ORM\QueryBuilder;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineIdCodec;
use Byfareska\Crud\Driver\Doctrine\DoctrineIdCodec;
use Byfareska\Crud\Driver\Doctrine\DoctrineRecordLabels;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineQuery;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearch;
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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Routing\CrudRouteLoader;
use Stringable;
use Symfony\UX\Autocomplete\AutocompleteResults;
use Symfony\UX\Autocomplete\Controller\AutocompleteController;
use Symfony\UX\Autocomplete\OptionsAwareAutocompleterInterface;

/**
 * The entities a relation filter of a list or a relation of a guessed form can be set to, found by what is typed
 * (symfony/ux-autocomplete, alias "byfareska_crud_relation"); the field says which resource and relation it is about
 * ({@see AutocompleteRelationFilterField}, {@see AutocompleteRelationFormField}). A filter: only for those who may see
 * that list, and only one the bundle made a field to search in (not one given options of the application's own,
 * RelationFilterField::OWN_CHOICES). A form: only for those whose add or edit form of the record has that field, not
 * disabled ({@see AutocompletedFormFields}).
 *
 * Only those the DoctrineScopes of the related entity's resource (DoctrineSource::$relations, else the first of the
 * same panel first) leave are offered; for an entity without a resource, those the scopes of every resource
 * (DoctrineScope::EVERY) leave - see DoctrineRelations. An entity is shown as the lists name it
 * (DoctrineRecordLabels).
 *
 * What is typed is never looked for where the one asking could not look or see themselves - matching or not would
 * tell it, a letter at a time: every word has to be in one of the fields the list of that related resource searches
 * ("search", with its DoctrineSearch) if they may see that list; else in one of the fields its entities are named by
 * (the title of their resource, DoctrineRecordLabels::titledBy()), which the results show anyway; with neither, the
 * text has to be an id. Never in a field "hidden" by that resource, nor one whose name looks secret
 * (AsCrud::looksSecret()), nor one of a related entity of its own ("parent.name").
 */
final class RelationAutocompleter implements OptionsAwareAutocompleterInterface
{
    public const string ALIAS = CrudRouteLoader::AUTOCOMPLETE_ALIAS;

    public const string RESOURCE = 'resource';

    public const string FIELD = 'field';

    /** The key of "extra_options" of a filter giving how many entities a page of the field has: 1 to 100. */
    public const string PER_PAGE = 'per_page';

    /** Asked by a relation of a guessed form (AutocompleteRelationFormField), not by a filter of a list. */
    public const string FORM = 'form';

    /** The record being edited by that form: never offered as its own relation (a category its own parent). */
    public const string RECORD = 'record';

    private const int DEFAULT_PER_PAGE = 10;

    private const array TEXT_TYPES = ['string', 'text', 'ascii_string'];

    /** @var array<string, mixed> */
    private array $options = [];

    /** Whether the form of the options has the field (AutocompletedFormFields), once a request; null = not asked. */
    private ?bool $inForm = null;

    public function __construct(
        private readonly CrudRegistry $registry,
        private readonly CrudAccess $access,
        private readonly ManagerRegistry $doctrine,
        private readonly CrudValues $values,
        private readonly DoctrineEntityNormalizer $entities,
        private readonly DoctrineRelations $relations,
        private readonly DoctrineSearches $searches,
        private readonly ?RelationChoiceLabels $labels = null,
        private readonly ?DoctrineRecordLabels $recordLabels = null,
        private readonly DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
        private readonly ?RequestStack $requests = null,
        private readonly ?AutocompletedFormFields $formFields = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $extra = $options[AutocompleteController::EXTRA_OPTIONS] ?? [];
        $this->options = [];
        $this->inForm = null;
        foreach (is_array($extra) ? $extra : [] as $name => $value) {
            $this->options[(string) $name] = $value;
        }
    }

    public function isGranted(Security $security): bool
    {
        $crud = $this->crud();

        return $crud instanceof AsCrud && $this->throughPanel($crud) && $this->granted($crud);
    }

    /**
     * Asked through the autocomplete route of the panel of the resource (CrudRouteLoader), under its prefix - in its
     * firewall and access_control - not through the global /autocomplete of symfony/ux-autocomplete, which nothing of
     * the panel guards: a panel kept to the signed in by access_control alone would otherwise tell its entities to
     * anybody with an address of one of its fields.
     */
    private function throughPanel(AsCrud $crud): bool
    {
        $request = $this->requests?->getCurrentRequest();

        return $request instanceof Request
            && $request->attributes->get(CrudRouteLoader::AUTOCOMPLETE) === true
            && $request->attributes->get(Scope::ATTRIBUTE) === $crud->scope;
    }

    /**
     * A filter: who may see the list; a form: who has the field in the add form, or in the edit form of the record
     * (AutocompletedFormFields) - nobody without it.
     */
    private function granted(AsCrud $crud): bool
    {
        if (!$this->isForm()) {
            return $this->access->isGranted($crud, CrudOperation::List);
        }
        $field = $this->option(self::FIELD);

        return $this->inForm ??= $field !== null
            && $this->formFields instanceof AutocompletedFormFields
            && $this->formFields->has($crud, $field, $this->option(self::RECORD));
    }

    private function isForm(): bool
    {
        return $this->option(self::FORM) !== null;
    }

    public function fetchResults(string $query, int $page): AutocompleteResults
    {
        $crud = $this->crud() ?? throw new NotFoundHttpException('No relation of a list or a form is asked for.');
        if (!$this->throughPanel($crud)) {
            throw new AccessDeniedHttpException(
                'The relations of a panel are searched through the address of the panel.',
            );
        }
        // Also without symfony/security-bundle, which is what asks isGranted().
        if (!$this->isForm()) {
            $this->access->assertGranted($crud, CrudOperation::List);
        } elseif (!$this->granted($crud)) {
            $this->access->assertGranted($crud, $this->option(self::RECORD) === null
                ? CrudOperation::Create
                : CrudOperation::Update);

            throw new AccessDeniedHttpException(sprintf(
                'The form of "%s" has no field "%s" to search in for the one asking.',
                $crud->name,
                (string) $this->option(self::FIELD),
            ));
        }
        $target = $this->target($crud)
            ?? throw new NotFoundHttpException('No relation of a list or a form is asked for.');
        // Within the scopes of the related entity's resource (the one the source names for the filter), else within
        // those of every resource.
        $relation = $this->option(RelationFilterField::FILTER) ?? $this->option(self::FIELD);
        $entities = $this->relations->query($crud, $target, $relation);
        $metadata = $entities->metadata;
        $builder = $entities->query;
        $related = $this->relations->resourceOf($crud, $target, $relation);
        [$searched, $search] = $this->searched($related, $metadata);
        $this->leaveOutRecord($crud, $entities->query, $metadata);
        $text = trim($query);
        if ($text !== '') {
            if ($searched !== []) {
                $search->apply($entities, $searched, $text);
            } elseif (!$this->isId($entities, $text)) {
                return new AutocompleteResults([], false);
            }
        }
        foreach ([...array_slice($searched, 0, 1), ...$metadata->getIdentifierFieldNames()] as $ordered) {
            $builder->addOrderBy('e.' . $ordered, DoctrineOrder::of(false));
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
            $text = $this->labels?->of($entity)
                ?? $this->recordLabels?->of($entity)
                ?? $this->values->normalize($entity);
            $results[] = [
                'value' => $id,
                'text' => is_scalar($text) || $text instanceof Stringable ? (string) $text : (string) $id,
            ];
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
        if ($this->isForm()) {
            // A relation of the entity itself, owned by it - what a guessed form has; none that looks secret.
            $metadata = $this->metadata($source->entity);

            return $metadata->hasAssociation($field)
                && !$metadata->isAssociationInverseSide($field)
                && !AsCrud::looksSecret($field)
                    ? $metadata->getAssociationTargetClass($field)
                    : null;
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
            // One the application chooses or names the entities of itself: a plain choice, not searched here
            // (DoctrineDriver) - an address signed before it was one would offer what it does not.
            $own = $type instanceof FieldFilter
                ? array_intersect_key($type->options, array_flip(RelationFilterField::OWN_CHOICES))
                : [];
            if ($own !== []) {
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
     * What the typed words are looked for in, and how: the search of the related resource's own list (its fields, its
     * DoctrineSearch) for one who may see that list - they could search it there; else the fields its entities are
     * named by, which every result shows, with the application's DoctrineSearch. Only fields of the entity itself, of
     * text; none "hidden" by those resources, none that looks secret.
     *
     * @param ClassMetadata<object> $target
     *
     * @return array{list<string>, DoctrineSearch}
     */
    private function searched(?AsCrud $related, ClassMetadata $target): array
    {
        $source = $related?->source;
        $hidden = $related->hidden ?? [];
        if (
            $related instanceof AsCrud
            && $source instanceof DoctrineSource
            && $source->search !== []
            && $this->access->isGranted($related, CrudOperation::List)
        ) {
            $fields = $source->search;
            $search = $this->searches->of($source);
        } else {
            $titled = $this->recordLabels?->titledBy($target->getName());
            $fields = $titled?->titleFields() ?? [];
            $hidden = [...$hidden, ...($titled->hidden ?? [])];
            $search = $this->searches->of(null);
        }

        $fields = array_values(array_filter(
            $fields,
            static fn (string $field): bool => $target->hasField($field)
                && in_array($target->getTypeOfField($field), self::TEXT_TYPES, true)
                && $target->getFieldMapping($field)->enumType === null
                && !in_array($field, $hidden, true)
                && !AsCrud::looksSecret($field),
        ));

        return [$fields, $search];
    }

    /**
     * Narrows the query to the entity of the id typed - what every result carries anyway; false = no id of it.
     */
    private function isId(DoctrineQuery $query, string $text): bool
    {
        $criteria = $this->ids->decode($query->metadata, $text);
        if ($criteria === null || $criteria === []) {
            return false;
        }
        foreach ($criteria as $field => $value) {
            $parameter = $query->parameter('id');
            $type = $query->metadata->hasField($field) ? $query->metadata->getTypeOfField($field) : null;
            $query->query->andWhere(sprintf('e.%s = :%s', $field, $parameter))->setParameter($parameter, $value, $type);
        }

        return true;
    }

    /** How many entities a page has: "per_page" of the filter's extra_options, 1 to 100. */
    /**
     * A relation to the same entity in the edit form: the record being edited is not offered (a category its own
     * parent).
     *
     * @param ClassMetadata<object> $target
     */
    private function leaveOutRecord(AsCrud $crud, QueryBuilder $builder, ClassMetadata $target): void
    {
        $record = $this->option(self::RECORD);
        $source = $crud->source;
        if (!$this->isForm() || $record === null || !$source instanceof DoctrineSource) {
            return;
        }
        if ($this->metadata($source->entity)->getName() !== $target->getName()) {
            return;
        }
        foreach ($this->ids->decode($target, $record) ?? [] as $name => $value) {
            $parameter = 'crud_record_' . $name;
            $builder->andWhere(sprintf('e.%s != :%s', $name, $parameter))
                ->setParameter($parameter, $value, $target->hasField($name) ? $target->getTypeOfField($name) : null);
        }
    }

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
