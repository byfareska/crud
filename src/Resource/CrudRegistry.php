<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Attribute\AsCrudFormSection;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\DependencyInjection\CollectCrudDeclarationsPass;
use Byfareska\Crud\Security\Actor;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Every resource declared with #[AsCrud] (one class may declare several), by its name, and what #[AsCrudFormSection]
 * adds to their forms, #[AsCrudDetailsSection] to their details pages and #[AsCrudPage] to their records. The classes
 * are found when the container is built ({@see CollectCrudDeclarationsPass}). Resources declared by code come from the
 * {@see CrudResourceProvider} services.
 *
 * A resource is what it is in the request being answered: as declared, then changed by the
 * {@see CrudDefinitionExtension}s of it (get(), all()). The routes are made of what is declared (declared()).
 */
final class CrudRegistry implements ResetInterface
{
    /** @var array<string, AsCrud>|null */
    private ?array $declared = null;

    /**
     * The resources of this request, changed by their extensions; forgotten after the request (reset()).
     *
     * @var array<string, AsCrud>
     */
    private array $extended = [];

    /**
     * What #[AsCrudPage], #[AsCrudFormSection] and #[AsCrudDetailsSection] (and the CrudAdditionsProviders) add to
     * each resource, once read: asked for every row of a list. The pages are the same for everybody and kept for good;
     * the sections go by the role of the one asking and are forgotten after the request (reset()).
     *
     * @var array{
     *     pages: array<string, array<string, AsCrudPage>>,
     *     form: array<string, array<string, AsCrudFormSection>>,
     *     details: array<string, array<string, AsCrudDetailsSection>>,
     * }
     */
    private array $added = ['pages' => [], 'form' => [], 'details' => []];

    /**
     * @param list<class-string> $resources
     * @param list<class-string> $formSections
     * @param list<class-string> $detailsSections
     * @param list<class-string> $pages
     * @param iterable<CrudResourceProvider> $providers
     * @param iterable<CrudDefinitionExtension> $extensions
     * @param iterable<CrudAdditionsProvider> $additions
     */
    public function __construct(
        private readonly array $resources = [],
        private readonly array $formSections = [],
        private readonly array $detailsSections = [],
        private readonly array $pages = [],
        #[AutowireIterator(CrudResourceProvider::TAG)]
        private readonly iterable $providers = [],
        #[AutowireIterator(CrudDefinitionExtension::TAG)]
        private readonly iterable $extensions = [],
        private readonly ?Actor $actor = null,
        #[AutowireIterator(CrudAdditionsProvider::TAG)]
        private readonly iterable $additions = [],
    ) {
    }

    /**
     * A resource as it is in this request - see CrudDefinitionExtension.
     *
     * @throws InvalidArgumentException when nothing declares a resource with that name
     * @throws LogicException when an extension changes what it may not
     */
    public function get(string $name): AsCrud
    {
        if (isset($this->extended[$name])) {
            return $this->extended[$name];
        }

        $declared = $this->declared()[$name]
            ?? throw new InvalidArgumentException(sprintf('There is no CRUD resource "%s".', $name));

        return $this->extended[$name] = $this->extend($declared);
    }

    /**
     * What other parts of the application add to the edit form of a resource, by the name of the section.
     *
     * @return array<string, AsCrudFormSection>
     */
    public function formSectionsOf(string $resource): array
    {
        if (isset($this->added['form'][$resource])) {
            return $this->added['form'][$resource];
        }

        $sections = [];
        foreach ($this->declaredFormSections() as $section) {
            if (
                ($section->resource === $resource || $section->resource === AsCrudDetailsSection::EVERY)
                && $this->mayHave($section->role, $resource)
            ) {
                $sections[$section->name] = $section;
            }
        }

        // Lower first; uasort() is stable, so equal ones keep the order of their classes.
        uasort($sections, static fn (AsCrudFormSection $a, AsCrudFormSection $b): int => $a->priority <=> $b->priority);

        return $this->added['form'][$resource] = $sections;
    }

    /**
     * What is shown under the details of a record of a resource, by the name of the section.
     *
     * @return array<string, AsCrudDetailsSection>
     */
    public function detailsSectionsOf(string $resource): array
    {
        if (isset($this->added['details'][$resource])) {
            return $this->added['details'][$resource];
        }

        $sections = [];
        foreach ($this->declaredDetailsSections() as $section) {
            if (
                ($section->resource === $resource || $section->resource === AsCrudDetailsSection::EVERY)
                && $this->mayHave($section->role, $resource)
            ) {
                $sections[$section->name] = $section;
            }
        }

        uasort(
            $sections,
            static fn (AsCrudDetailsSection $a, AsCrudDetailsSection $b): int => $a->priority <=> $b->priority,
        );

        return $this->added['details'][$resource] = $sections;
    }

    /**
     * Whether the one acting may have a section of a resource that names a role: asked with the resource as the
     * subject. Without an Actor to ask, nobody may.
     */
    private function mayHave(?string $role, string $resource): bool
    {
        return $role === null || ($this->actor?->isGranted($role, $this->get($resource)) ?? false);
    }

    /**
     * The pages other parts of the application add to the records of a resource, by name.
     *
     * @return array<string, AsCrudPage>
     *
     * @throws LogicException when two pages of the resource share a name: one would silently take the other's place
     */
    public function pagesOf(string $resource): array
    {
        if (isset($this->added['pages'][$resource])) {
            return $this->added['pages'][$resource];
        }

        $pages = [];
        foreach ($this->declaredPages() as $class => $page) {
            if ($page->resource !== $resource && $page->resource !== AsCrudPage::EVERY) {
                continue;
            }
            if (isset($pages[$page->name])) {
                throw new LogicException(sprintf(
                    'The records of "%s" get the page "%s" twice (%s).',
                    $resource,
                    $page->name,
                    $class,
                ));
            }
            $pages[$page->name] = $page;
        }

        uasort($pages, static fn (AsCrudPage $a, AsCrudPage $b): int => $a->priority <=> $b->priority);

        return $this->added['pages'][$resource] = $pages;
    }

    /**
     * The files the resources and their pages are declared in (the routes are built again when one changes).
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = [];
        foreach ([...$this->resources, ...$this->pages] as $class) {
            $file = new ReflectionClass($class)->getFileName();
            if ($file !== false) {
                $files[] = $file;
            }
        }
        foreach ([...$this->providers, ...$this->additions] as $provider) {
            $file = new ReflectionObject($provider)->getFileName();
            if ($file !== false) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Every resource as it is in this request - see CrudDefinitionExtension.
     *
     * @return array<string, AsCrud>
     *
     * @throws LogicException when two declarations share a name, or an extension changes what it may not
     */
    public function all(): array
    {
        $all = [];
        foreach (array_keys($this->declared()) as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /**
     * Every resource as declared - by #[AsCrud] (a class may declare several, it is repeatable) and by the
     * CrudResourceProviders - the same for everybody: what the routes are made of.
     *
     * @return array<string, AsCrud>
     *
     * @throws LogicException when two declarations share a name: routes, texts and sections go by it
     */
    public function declared(): array
    {
        if ($this->declared !== null) {
            return $this->declared;
        }

        $definitions = [];
        $add = static function (AsCrud $definition, string $where) use (&$definitions): void {
            if (isset($definitions[$definition->name])) {
                throw new LogicException(sprintf(
                    'CRUD resource "%s" is declared twice (%s).',
                    $definition->name,
                    $where,
                ));
            }
            $definitions[$definition->name] = $definition;
        };
        foreach ($this->resources as $class) {
            foreach (new ReflectionClass($class)->getAttributes(AsCrud::class) as $attribute) {
                $add($attribute->newInstance(), $class);
            }
        }
        foreach ($this->providers as $provider) {
            foreach ($provider->resources() as $definition) {
                if (!$definition instanceof AsCrud) {
                    throw new LogicException(sprintf(
                        '%s yielded %s, a resource is an %s.',
                        $provider::class,
                        get_debug_type($definition),
                        AsCrud::class,
                    ));
                }
                $add($definition, $provider::class);
            }
        }

        return $this->declared = $definitions;
    }

    /**
     * The extensions of the next request may see another user.
     */
    public function reset(): void
    {
        $this->extended = [];
        // The sections of a role are those of the one who asked: the next request may be somebody else's.
        $this->added['form'] = [];
        $this->added['details'] = [];
    }

    /**
     * The pages of the attributes and of the CrudAdditionsProviders, by where they come from.
     *
     * @return iterable<string, AsCrudPage>
     */
    private function declaredPages(): iterable
    {
        foreach ($this->pages as $class) {
            foreach (new ReflectionClass($class)->getAttributes(AsCrudPage::class) as $attribute) {
                yield $class => $attribute->newInstance();
            }
        }
        foreach ($this->additions as $provider) {
            foreach ($provider->pages() as $page) {
                yield $provider::class => $page;
            }
        }
    }

    /**
     * @return iterable<AsCrudFormSection>
     */
    private function declaredFormSections(): iterable
    {
        foreach ($this->formSections as $class) {
            foreach (new ReflectionClass($class)->getAttributes(AsCrudFormSection::class) as $attribute) {
                yield $attribute->newInstance();
            }
        }
        foreach ($this->additions as $provider) {
            yield from $provider->formSections();
        }
    }

    /**
     * @return iterable<AsCrudDetailsSection>
     */
    private function declaredDetailsSections(): iterable
    {
        foreach ($this->detailsSections as $class) {
            foreach (new ReflectionClass($class)->getAttributes(AsCrudDetailsSection::class) as $attribute) {
                yield $attribute->newInstance();
            }
        }
        foreach ($this->additions as $provider) {
            yield from $provider->detailsSections();
        }
    }

    /**
     * A resource changed by its extensions, one after another.
     *
     * @throws LogicException an extension changed what the routes are made of, the roles, or gave back what was refused
     */
    private function extend(AsCrud $crud): AsCrud
    {
        foreach ($this->extensions as $extension) {
            if (!in_array($extension->resource(), [$crud->name, CrudDefinitionExtension::EVERY], true)) {
                continue;
            }
            $extended = $extension->extend($crud);
            if (!$crud->isSameResourceAs($extended)) {
                throw new LogicException(sprintf(
                    '%s changed the resource "%s" into another one: name, path, scope, own, source, listedIn, role and '
                    . 'roles stay as declared, and what is refused is not given back - change the rest with '
                    . 'AsCrud::with(), take operations away with AsCrud::refusing().',
                    $extension::class,
                    $crud->name,
                ));
            }
            $crud = $extended;
        }

        return $crud;
    }
}
