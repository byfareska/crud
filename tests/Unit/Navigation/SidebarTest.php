<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Navigation;

use Byfareska\Crud\CommandPalette\NavigationPaletteSource;
use Byfareska\Crud\Component\Navigation\Sidebar;
use Byfareska\Crud\Navigation\Navigation;
use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\Crud\Navigation\NavigationSource;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SidebarTest extends TestCase
{
    public function testOfItemsOfOneRouteThoseWithTheParametersOfThePageLight(): void
    {
        self::assertSame(['all' => false, 'open' => true, 'closed' => false], $this->active('/orders?status=open'));
        self::assertSame(['all' => true, 'open' => false, 'closed' => false], $this->active('/orders'));
    }

    public function testItemsOfOneRouteAreEntriesOfTheirOwnInThePalette(): void
    {
        $keys = [];
        foreach (new NavigationPaletteSource($this->navigation(new RequestStack()))->entries() as $entry) {
            $keys[] = $entry->key;
        }

        self::assertSame(['nav.app_orders', 'nav.app_orders?status=open', 'nav.app_orders?status=closed'], $keys);
    }

    public function testAnEntryOfThePaletteIsNamedAsTheMenuNamesIt(): void
    {
        $source = new class implements NavigationSource {
            public function items(): iterable
            {
                $section = NavigationItem::SECTION_MAIN;
                yield new NavigationItem($section, 'app_inbox', 'app.nav.inbox', 'inbox', labelParameters: ['n' => 3]);
                yield new NavigationItem($section, 'app_acme', 'ACME sp. z o.o.', 'building', translate: false);
            }
        };
        $requests = new RequestStack();
        $entries = iterator_to_array(
            new NavigationPaletteSource(new Navigation([$source], $this->scopes($requests)))->entries(),
            false,
        );

        self::assertSame(['n' => 3], $entries[0]->nameParameters);
        self::assertTrue($entries[0]->translate);
        self::assertSame('ACME sp. z o.o.', $entries[1]->name);
        self::assertFalse($entries[1]->translate, 'A finished text: not looked up as a key.');
    }

    public function testItemsUnderAParentAreShownUnderIt(): void
    {
        $source = new class implements NavigationSource {
            public function items(): iterable
            {
                $section = NavigationItem::SECTION_MAIN;
                yield new NavigationItem($section, 'app_sales', 'Sprzedaż', 'cart', translate: false);
                yield new NavigationItem(
                    $section,
                    'app_orders',
                    'app.nav.orders',
                    'list',
                    badge: 5,
                    parent: 'app_sales',
                    badgeVariant: 'danger',
                );
                yield new NavigationItem(
                    $section,
                    'app_returns',
                    'app.nav.returns',
                    'arrow-return-left',
                    parent: 'app_sales',
                );
                // A parent nobody has: where it is.
                yield new NavigationItem($section, 'app_stock', 'app.nav.stock', 'box', parent: 'app_warehouse');
            }
        };
        $request = Request::create('/orders');
        $request->attributes->set('_route', 'app_orders');
        $requests = new RequestStack();
        $requests->push($request);
        $sidebar = new Sidebar(
            new Navigation([$source], $this->scopes($requests)),
            $this->scopes($requests),
            $requests,
            new CurrentCrud($requests),
        );

        $links = $sidebar->getSections()[NavigationItem::SECTION_MAIN];

        self::assertSame(['Sprzedaż', 'app.nav.stock'], array_column($links, 'label'));
        self::assertFalse($links[0]['translate']);
        self::assertSame(['app.nav.orders', 'app.nav.returns'], array_column($links[0]['children'], 'label'));
        self::assertTrue($links[0]['expanded'], 'Open while one under it is lit.');
        self::assertSame('danger', $links[0]['children'][0]['badgeVariant']);
    }

    public function testItemsUnderAParentOfAParentAreShownUnderIt(): void
    {
        $source = new class implements NavigationSource {
            public function items(): iterable
            {
                $section = NavigationItem::SECTION_MAIN;
                yield new NavigationItem($section, 'app_sales', 'app.nav.sales', 'cart');
                yield new NavigationItem($section, 'app_orders', 'app.nav.orders', 'list', parent: 'app_sales');
                yield new NavigationItem($section, 'app_returns', 'app.nav.returns', 'reply', parent: 'app_orders');
                // Each the parent of the other: the circle is broken, neither is lost.
                yield new NavigationItem($section, 'app_a', 'app.nav.a', 'a', parent: 'app_b');
                yield new NavigationItem($section, 'app_b', 'app.nav.b', 'b', parent: 'app_a');
            }
        };
        $request = Request::create('/returns');
        $request->attributes->set('_route', 'app_returns');
        $requests = new RequestStack();
        $requests->push($request);
        $sidebar = new Sidebar(
            new Navigation([$source], $this->scopes($requests)),
            $this->scopes($requests),
            $requests,
            new CurrentCrud($requests),
        );

        $links = $sidebar->getSections()[NavigationItem::SECTION_MAIN];

        self::assertSame(['app.nav.sales', 'app.nav.a'], array_column($links, 'label'));
        self::assertSame(['app.nav.b'], array_column($links[1]['children'], 'label'));
        $orders = $links[0]['children'][0];
        self::assertIsArray($orders);
        self::assertSame('app.nav.orders', $orders['label']);
        self::assertIsArray($orders['children']);
        self::assertSame(['app.nav.returns'], array_column($orders['children'], 'label'));
        self::assertTrue($links[0]['expanded'], 'Open while one under it, however deep, is lit.');
        self::assertTrue($orders['expanded']);
    }

    /**
     * @return array<string, bool> by label
     */
    private function active(string $uri): array
    {
        $request = Request::create($uri);
        $request->attributes->set('_route', 'app_orders');
        $request->attributes->set('_route_params', []);
        $requests = new RequestStack();
        $requests->push($request);
        $sidebar = new Sidebar(
            $this->navigation($requests),
            $this->scopes($requests),
            $requests,
            new CurrentCrud($requests),
        );

        $active = [];
        foreach ($sidebar->getSections() as $items) {
            foreach ($items as $item) {
                $active[$item['label']] = $item['active'];
            }
        }

        return $active;
    }

    private function navigation(RequestStack $requests): Navigation
    {
        $source = new class implements NavigationSource {
            public function items(): iterable
            {
                $section = NavigationItem::SECTION_MAIN;
                yield new NavigationItem($section, 'app_orders', 'all', 'list', priority: 1);
                foreach (['open' => 2, 'closed' => 3] as $status => $priority) {
                    yield new NavigationItem(
                        $section,
                        'app_orders',
                        $status,
                        'list',
                        priority: $priority,
                        routeParameters: ['status' => $status],
                    );
                }
            }
        };

        return new Navigation([$source], $this->scopes($requests));
    }

    private function scopes(RequestStack $requests): Scopes
    {
        $actor = new class implements Actor {
            public function id(): ?string
            {
                return null;
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return true;
            }
        };

        return new Scopes(
            ['panel' => [
                'label' => null,
                'icon' => 'house',
                'layout' => null,
                'theme' => null,
                'entries' => [],
                'role' => null,
                'dashboard' => false,
                'home' => null,
            ]],
            'layout.html.twig',
            'bootstrap5',
            ['app'],
            $requests,
            $actor,
            new CurrentCrud($requests),
        );
    }
}
