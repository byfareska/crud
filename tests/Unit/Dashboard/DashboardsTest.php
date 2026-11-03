<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\Dashboard\AttributeDashboardWidgets;
use Byfareska\Crud\Dashboard\DashboardLayouts;
use Byfareska\Crud\Dashboard\Dashboards;
use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Dashboard\DashboardWidgetFilter;
use Byfareska\Crud\Dashboard\DashboardWidgets;
use Byfareska\Crud\Dashboard\LayoutNormalizer;
use Byfareska\Crud\Dashboard\LayoutPacker;
use Byfareska\Crud\Dashboard\RoleDashboardPolicy;
use Byfareska\Crud\Dashboard\WidgetChoices;
use Byfareska\Crud\Dashboard\WidgetPlacement;
use Byfareska\Crud\Dashboard\WidgetSettingRefused;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\FeedUrl;
use Byfareska\Crud\Tests\Unit\Fixtures\FeedWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\NotesWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\SalesWidget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardsTest extends TestCase
{
    public function testAWidgetLeftOutForAWhileKeepsItsPlaceWhenTheLayoutChanges(): void
    {
        $layouts = $this->layouts();
        $layouts->save('1', 'panel', [
            new WidgetPlacement('sales', 'test.sales', 0, 0, 4, 2, ['period' => '30', 'city' => 'krk']),
            new WidgetPlacement('notes', 'test.notes', 4, 0, 4, 2, ['text' => 'mine']),
        ]);

        // The notes are left out for the request (a feature flag): the one acting moves the sales.
        $this->dashboards($layouts, withoutNotes: true)
            ->move('panel', ['sales' => ['x' => 0, 'y' => 3, 'w' => 4, 'h' => 2]]);

        $stored = $layouts->find('1', 'panel');
        self::assertNotNull($stored);
        $byId = [];
        foreach ($stored as $placement) {
            $byId[$placement->id] = $placement;
        }
        self::assertSame(3, $byId['sales']->y);
        self::assertArrayHasKey('notes', $byId, 'Not lost while it was left out.');
        self::assertSame(['text' => 'mine'], $byId['notes']->settings);

        // Back again: on the dashboard, with what it had.
        [$placements] = $this->dashboards($layouts)->current('panel');
        $ids = array_map(static fn (WidgetPlacement $placement): string => $placement->id, $placements);
        self::assertContains('notes', $ids);
    }

    public function testAValueItsValidatorRefusesIsNotSaved(): void
    {
        $layouts = $this->layouts();
        $feed = new WidgetPlacement('feed', 'test.feed', 0, 0, 4, 2, ['url' => 'https://a.example/rss']);
        $layouts->save('1', 'panel', [$feed]);
        $dashboards = $this->dashboards($layouts);

        try {
            $dashboards->configure('panel', 'feed', ['url' => 'http://b.example/rss']);
            self::fail('Not refused.');
        } catch (WidgetSettingRefused $refused) {
            self::assertSame(['url', 'app.feed.not_https'], [$refused->setting, $refused->translationKey]);
        }
        self::assertSame(['url' => 'https://a.example/rss'], $layouts->find('1', 'panel')[0]->settings ?? null);
    }

    private function dashboards(DashboardLayouts $layouts, bool $withoutNotes = false): Dashboards
    {
        $actor = $this->actor();
        $filters = $withoutNotes ? [new class implements DashboardWidgetFilter {
            public function allows(DashboardWidget $widget, string $board): bool
            {
                return $widget->key !== 'test.notes';
            }
        }] : [];
        $cities = new class implements WidgetChoices {
            public function choices(): array
            {
                return ['waw' => 'Warsaw', 'krk' => 'Kraków'];
            }
        };
        $widgets = new DashboardWidgets(
            [new AttributeDashboardWidgets([
                SalesWidget::class => 'Test:Sales',
                NotesWidget::class => 'Test:Notes',
                FeedWidget::class => 'Test:Feed',
            ])],
            $actor,
            new ServiceLocator(['test.cities' => static fn (): object => $cities]),
            filters: $filters,
        );
        $requests = new RequestStack();
        $scopes = new Scopes(
            ['panel' => [
                'label' => null,
                'icon' => 'house',
                'layout' => null,
                'theme' => null,
                'entries' => [],
                'role' => null,
                'dashboard' => true,
                'home' => null,
            ]],
            'layout.html.twig',
            'bootstrap5',
            ['app'],
            $requests,
            $actor,
            new CurrentCrud($requests),
        );

        return new Dashboards(
            $layouts,
            $widgets,
            new LayoutNormalizer(),
            new LayoutPacker(),
            $actor,
            $scopes,
            new RoleDashboardPolicy($actor),
            new ServiceLocator([FeedUrl::class => static fn (): FeedUrl => new FeedUrl()]),
        );
    }

    private function layouts(): DashboardLayouts
    {
        return new class implements DashboardLayouts {
            /** @var array<string, list<WidgetPlacement>> */
            private array $kept = [];

            public function find(?string $owner, string $board): ?array
            {
                return $this->kept[$owner . '|' . $board] ?? null;
            }

            public function save(?string $owner, string $board, array $placements): void
            {
                $this->kept[$owner . '|' . $board] = $placements;
            }

            public function delete(?string $owner, string $board): void
            {
                unset($this->kept[$owner . '|' . $board]);
            }
        };
    }

    private function actor(): Actor
    {
        return new class implements Actor {
            public function id(): string
            {
                return '1';
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return true;
            }
        };
    }
}
