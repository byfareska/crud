<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Navigation;

use Symfony\Component\HttpFoundation\Request;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Navigation\Navigation;
use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The menu of the panel of the page.
 */
#[AsTwigComponent('Panel:Sidebar', template: 'components/Panel/Sidebar.html.twig')]
final readonly class Sidebar
{
    public function __construct(
        private Navigation $navigation,
        private Scopes $scopes,
        private RequestStack $requestStack,
        private CurrentCrud $currentCrud,
    ) {
    }

    /**
     * What the NavigationSources put into the menu of the panel of the page (its route scope); titles and labels
     * are translation keys.
     *
     * @return array<string, list<array{
     *     route: string,
     *     parameters: array<string, scalar>,
     *     icon: string,
     *     label: string,
     *     active: bool,
     *     url: string|null,
     *     badge: string|null,
     *     badgeVariant: string|null,
     *     labelParameters: array<string, scalar>,
     *     translate: bool,
     *     attributes: array<string, string|int|bool|null>,
     *     item: NavigationItem,
     *     children: list<array<string, mixed>>,
     *     expanded: bool,
     * }>> "item" for a template of a theme of yours, with what an extension gave it; "children" the items under it
     *     (NavigationItem::$parent), "expanded" while one of them is lit
     */
    public function getSections(): array
    {
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->get('_route');
        $given = [];
        if ($request instanceof Request) {
            $routeParameters = $request->attributes->get('_route_params');
            $given = [...$request->query->all(), ...(is_array($routeParameters) ? $routeParameters : [])];
        }
        // Every page of a resource keeps its list lit - a record, its form, a page of the application's about it; a
        // resource listed in another one's list keeps that one lit.
        $crud = $this->currentCrud->get()?->crud;
        $resource = $crud instanceof AsCrud ? $crud->listedIn->resource ?? $crud->name : null;

        $sections = $this->navigation->sections($this->scopes->current()->name);
        // An item of the route of the page lights when the page has its parameters too ("open orders": the orders
        // with ?status=open); of several, those that name the most of them - not "all orders" next to it.
        $matching = static fn (NavigationItem $item): ?int => $item->routeName === $route && array_all(
            $item->routeParameters,
            static fn (mixed $value, string $name): bool => isset($given[$name])
                && is_scalar($given[$name])
                && (string) $given[$name] === (string) $value,
        ) ? count($item->routeParameters) : null;
        $best = -1;
        foreach ($sections as $items) {
            foreach ($items as $item) {
                $best = max($best, $matching($item) ?? -1);
            }
        }

        $links = array_map(static fn (array $items): array => array_map(static fn (NavigationItem $item): array => [
            'route' => $item->routeName,
            'parameters' => $item->routeParameters,
            'icon' => $item->icon,
            'label' => $item->label,
            'active' => $item->url === null && (($best >= 0 && $matching($item) === $best)
                || ($item->resource !== null && $item->resource === $resource)),
            'url' => $item->url,
            'badge' => $item->badgeText(),
            'badgeVariant' => $item->badgeVariant,
            'labelParameters' => $item->labelParameters,
            'translate' => $item->translate,
            'children' => [],
            'expanded' => false,
            'attributes' => array_filter([
                'target' => $item->target,
                'rel' => $item->target === '_blank' ? 'noopener' : null,
            ], static fn (?string $value): bool => $value !== null) + $item->attributes,
            'item' => $item,
        ], $items), $sections);

        return array_map($this->nested(...), $links);
    }

    /**
     * The links of a section with those of a parent (NavigationItem::$parent) under it, however deep ("Sales > Orders >
     * Returns"); a parent not in the section (not for the one looking) leaves its children where they are. A parent
     * is expanded when a link under it, however deep, is the page.
     *
     * @template TLink of array{item: NavigationItem, active: bool, children: list<mixed>, expanded: bool}
     *
     * @param list<TLink> $links
     *
     * @return list<TLink>
     */
    private function nested(array $links): array
    {
        $indexOf = [];
        foreach ($links as $index => $link) {
            $indexOf[$link['item']->routeName] ??= $index;
        }
        $parentOf = [];
        foreach ($links as $index => $link) {
            $parent = $link['item']->parent !== null ? $indexOf[$link['item']->parent] ?? null : null;
            if ($parent !== null && $parent !== $index) {
                $parentOf[$index] = $parent;
            }
        }
        // A parent that is its own ancestor (a mistake of the sources): the circle is broken where it is found, so no
        // link is lost.
        foreach (array_keys($parentOf) as $index) {
            $seen = [$index => true];
            for ($up = $parentOf[$index]; $up !== null; $up = $parentOf[$up] ?? null) {
                if (isset($seen[$up])) {
                    unset($parentOf[$index]);
                    break;
                }
                $seen[$up] = true;
            }
        }
        $childrenOf = [];
        foreach ($parentOf as $index => $parent) {
            $childrenOf[$parent][] = $index;
        }

        $build = static function (int $index) use (&$build, $links, $childrenOf): array {
            $link = $links[$index];
            $children = array_map($build, $childrenOf[$index] ?? []);
            if ($children !== []) {
                $link['children'] = $children;
                $link['expanded'] = array_any(
                    $children,
                    static fn (array $child): bool => $child['active'] === true || $child['expanded'] === true,
                );
            }

            return $link;
        };

        $top = [];
        foreach (array_keys($links) as $index) {
            if (!isset($parentOf[$index])) {
                $top[] = $build($index);
            }
        }

        // @phpstan-ignore return.type (the same links, "children" and "expanded" filled in)
        return $top;
    }
}
