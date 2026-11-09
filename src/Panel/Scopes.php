<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Security\Actor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The panels (route scopes) of byfareska_crud.scopes, and the one of the page being shown.
 */
final readonly class Scopes
{
    /**
     * @param array<string, array{
     *     label: string|null,
     *     icon: string,
     *     layout: string|null,
     *     theme: string|null,
     *     entries: list<string>,
     *     role: string|null,
     *     dashboard: bool,
     *     home: string|null,
     * }> $scopes
     * @param list<string> $entries
     * @param iterable<ScopeExtension> $extensions
     */
    public function __construct(
        #[Autowire(param: 'byfareska_crud.scopes')]
        private array $scopes,
        #[Autowire(param: 'byfareska_crud.layout')]
        private string $layout,
        #[Autowire(param: 'byfareska_crud.theme')]
        private string $theme,
        #[Autowire(param: 'byfareska_crud.entries')]
        private array $entries,
        private RequestStack $requestStack,
        private Actor $actor,
        private CurrentCrud $currentCrud,
        #[AutowireIterator(ScopeExtension::TAG)]
        private iterable $extensions = [],
    ) {
    }

    /**
     * A scope of the configuration, or one that only CRUD resources name (a menu, a layout and a dashboard) - as the
     * ScopeExtensions make it for the request.
     */
    public function get(string $name): Scope
    {
        $scope = $this->declared($name);
        foreach ($this->extensions as $extension) {
            if (in_array($extension->scope(), [$name, ScopeExtension::EVERY], true)) {
                $scope = $extension->extend($scope);
                if ($scope->name !== $name) {
                    throw new \LogicException(sprintf(
                        '%s made of the panel "%s" another one.',
                        $extension::class,
                        $name,
                    ));
                }
            }
        }

        return $scope;
    }

    /**
     * The boards of the configuration of a panel, each with its defaults.
     *
     * @param array<string, array<string, string|null>> $boards
     *
     * @return array<string, array{label: string, icon: string, path: string, role: string|null, template: string|null}>
     */
    private function boardsOf(array $boards): array
    {
        $made = [];
        foreach ($boards as $board => $settings) {
            $made[$board] = [
                'label' => $settings['label'] ?? 'panel.board.' . $board,
                'icon' => $settings['icon'] ?? 'grid-1x2',
                'path' => $settings['path'] ?? '/' . $board,
                'role' => $settings['role'] ?? null,
                'template' => $settings['template'] ?? null,
            ];
        }

        return $made;
    }

    /**
     * The panel a dashboard is of - the panel itself, or the one that has it among its boards; null = none has it.
     */
    public function ofBoard(string $board): ?Scope
    {
        foreach (array_keys($this->scopes) as $name) {
            $scope = $this->get($name);
            if ($scope->hasBoard($board)) {
                return $scope;
            }
        }
        $scope = $this->get($board);

        return $scope->hasBoard($board) ? $scope : null;
    }

    /** Whether the configuration has the scope. */
    public function isConfigured(string $name): bool
    {
        return isset($this->scopes[$name]);
    }

    /**
     * A scope as configured, whatever the request: what its routes are made of.
     */
    public function declared(string $name): Scope
    {
        $config = $this->scopes[$name] ?? null;

        return new Scope(
            $name,
            $config['label'] ?? 'panel.scope.' . $name,
            $config['icon'] ?? 'window',
            $config['layout'] ?? $this->layout,
            $config['theme'] ?? $this->theme,
            array_values(array_unique([...$this->entries, ...$config['entries'] ?? []])),
            $config['role'] ?? null,
            $config['dashboard'] ?? true,
            $config['home'] ?? null,
            $config['brand'] ?? null,
            $this->boardsOf($config['boards'] ?? []),
        );
    }

    /**
     * @return array<string, Scope> every configured scope, in the order of the configuration
     */
    public function all(): array
    {
        $scopes = [];
        foreach (array_keys($this->scopes) as $name) {
            $scopes[$name] = $this->get($name);
        }

        return $scopes;
    }

    /**
     * The scope of the page being shown (of the main request): its route default "_scope", else the scope of the
     * resource the page is of (a page of the application's with #[MapCrud]), else the first one configured, else the
     * default of #[AsCrud].
     */
    public function current(): Scope
    {
        $name = $this->requestStack->getMainRequest()?->attributes->get(Scope::ATTRIBUTE);
        if (!is_string($name) || $name === '') {
            $name = $this->currentCrud->get()?->crud->scope ?? array_key_first($this->scopes) ?? AsCrud::DEFAULT_SCOPE;
        }

        return $this->get($name);
    }

    /**
     * The other panels the one looking may go to (the panel switch).
     *
     * @return list<Scope>
     */
    public function others(): array
    {
        $current = $this->current()->name;

        return array_values(array_filter(
            $this->all(),
            fn (Scope $scope): bool => $scope->name !== $current
                && $scope->homeRoute() !== null
                && $this->isGranted($scope),
        ));
    }

    public function isGranted(Scope $scope): bool
    {
        // The panel as the subject: a voter of the application knows which one it is asked about.
        return $scope->role === null || $this->actor->isGranted($scope->role, $scope);
    }

    /**
     * Whether the one acting may use something of a panel that may need a role of its own: a resource, a page of the
     * menu, an entry of the palette. It needs the role of the panel too - what is in a panel is no more open than the
     * panel. Something of every panel ($scope null) needs only its own role.
     *
     * @param mixed $subject what the role is asked about (a resource: its AsCrud), for a voter of the application
     */
    public function isGrantedIn(?string $scope, ?string $role = null, mixed $subject = null): bool
    {
        return ($role === null || $this->actor->isGranted($role, $subject))
            && ($scope === null || $this->isGranted($this->get($scope)));
    }
}
