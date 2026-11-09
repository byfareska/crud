<?php

declare(strict_types=1);

namespace Byfareska\Crud\Translation;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Layout\LayoutGroup;
use Byfareska\Crud\Layout\LayoutNode;
use Byfareska\Crud\Resource\CrudRegistry;
use ReflectionClass;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

use function Symfony\Component\String\u;

/**
 * The texts of the resources nobody has translated yet, made of their names: "crud.product.title" is "Products",
 * "crud.product.field.createdAt.name" is "Created at", a menu section "app.nav.catalog" is "Catalog". A catalogue
 * the translator loads before every file of the bundles and of the application (CrudDefaultTextsPass), so a
 * translation of the application always wins; one of no language but the default and English, which every other
 * falls back to.
 *
 * Only what names something is made: no help, no toast (the bundle's own then), no keywords of the palette.
 */
#[AutoconfigureTag('translation.loader', ['alias' => self::FORMAT])]
final readonly class CrudDefaultTexts implements LoaderInterface
{
    public const string FORMAT = 'byfareska_crud';

    /** A key that names a text rather than being one: "app.nav.catalog", not "Catalog". */
    private const string KEY = '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/';

    /**
     * @param iterable<ResourceFieldNames> $fieldNames
     * @param list<class-string> $classes the classes #[AsCrud] is on: a change of one makes the texts anew
     */
    public function __construct(
        private CrudRegistry $registry,
        #[AutowireIterator(ResourceFieldNames::TAG)]
        private iterable $fieldNames = [],
        private array $classes = [],
    ) {
    }

    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $catalogue = new MessageCatalogue($locale);
        if ($domain !== 'messages') {
            return $catalogue;
        }

        $catalogue->add($this->messages(), $domain);
        foreach ($this->classes as $class) {
            $file = new ReflectionClass($class)->getFileName();
            if (is_string($file)) {
                $catalogue->addResource(new FileResource($file));
            }
        }

        return $catalogue;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->registry->declared() as $crud) {
            $messages += $this->of($crud);
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    private function of(AsCrud $crud): array
    {
        $singular = self::humanize($crud->name);
        $messages = [
            $crud->key('title') => self::plural($singular),
            $crud->key('new') => 'New ' . mb_strtolower($singular),
            $crud->key('edit') => 'Edit ' . mb_strtolower($singular),
        ];
        foreach ($this->fieldsOf($crud) as $field) {
            $messages[$crud->key(sprintf('field.%s.name', $field))] = self::humanize($field);
        }
        foreach ([...$crud->formLayout, ...$crud->detailsLayout] as $node) {
            foreach (self::groups($node) as $group) {
                $messages[$crud->key(sprintf('layout.%s.name', $group))] = self::humanize($group);
            }
        }
        foreach ($crud->actions as $action) {
            $messages[$crud->key('action.' . $action->name)] = self::humanize($action->name);
        }
        foreach ($crud->bulkActions as $action) {
            $messages[$crud->key('bulk.' . $action->name)] = self::humanize($action->name);
        }
        foreach ($this->registry->pagesOf($crud->name) as $page) {
            $messages[$crud->key('action.' . $page->name)] = self::humanize($page->name);
        }
        if ($crud->section !== null && preg_match(self::KEY, $crud->section) === 1) {
            $segments = explode('.', $crud->section);
            $messages[$crud->section] = self::humanize((string) end($segments));
        }

        return $messages;
    }

    /**
     * Every field a text names: the columns, the details, the fields of the form, and what the driver knows.
     *
     * @return list<string>
     */
    private function fieldsOf(AsCrud $crud): array
    {
        $fields = [
            ...array_map(static fn ($column): string => $column->field, $crud->columns),
            ...array_map(static fn ($column): string => $column->field, $crud->details),
            ...array_filter($crud->fields, is_string(...)),
            ...array_filter(array_keys($crud->fields), is_string(...)),
        ];
        foreach ($this->fieldNames as $names) {
            $fields = [...$fields, ...$names->of($crud)];
        }

        return array_values(array_unique(array_filter($fields, static fn (string $field): bool => $field !== '')));
    }

    /**
     * @return list<string> the names of the groups (tabs, fieldsets) in a node of a layout
     */
    private static function groups(mixed $node): array
    {
        if (!$node instanceof LayoutNode) {
            return [];
        }
        $groups = [];
        if ($node instanceof LayoutGroup) {
            $name = $node->name();
            if ($name !== null && $name !== '') {
                $groups[] = $name;
            }
            foreach ($node->children() as $child) {
                $groups = [...$groups, ...self::groups($child)];
            }
        }

        return $groups;
    }

    /** "createdAt", "created_at", "unit-price" as words: "Created at", "Unit price". */
    public static function humanize(string $name): string
    {
        $words = u($name)->snake()->replace('_', ' ')->replace('-', ' ')->collapseWhitespace()->lower()->toString();

        return u($words)->title()->toString();
    }

    /** The last word in its plural: "Product" - "Products", "Order line" - "Order lines". */
    private static function plural(string $words): string
    {
        $parts = explode(' ', $words);
        $last = (string) array_pop($parts);
        $plural = new EnglishInflector()->pluralize(mb_strtolower($last))[0] ?? $last;
        $parts[] = $parts === [] ? u($plural)->title()->toString() : $plural;

        return implode(' ', $parts);
    }
}
