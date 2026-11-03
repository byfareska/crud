<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Attribute\AsTopbarWidget;
use Byfareska\Crud\Dashboard\AttributeDashboardWidgets;
use Byfareska\Crud\DependencyInjection\CollectWidgetsPass;
use Byfareska\Crud\Navigation\AttributeTopbarWidgets;
use Byfareska\Crud\Navigation\TopbarWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\BellWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\CounterWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\NotesWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\OtherSalesWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\SalesWidget;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CollectWidgetsPassTest extends TestCase
{
    public function testNamesTheComponentsByTheirTwigComponentOrTheAttribute(): void
    {
        $container = $this->container();
        $container->register(SalesWidget::class, SalesWidget::class)
            ->addTag(AsDashboardWidget::TAG)
            ->addTag('twig.component', ['key' => 'Test:Sales']);
        $container->register(NotesWidget::class, NotesWidget::class)
            ->addTag(AsDashboardWidget::TAG)
            ->addTag('twig.component', ['key' => 'Ignored:Name']);
        $container->register(BellWidget::class, BellWidget::class)
            ->addTag(AsTopbarWidget::TAG)
            ->addTag('twig.component', ['key' => 'Test:Bell']);

        new CollectWidgetsPass()->process($container);

        $components = $container->getDefinition(AttributeDashboardWidgets::class)->getArgument('$components');
        self::assertSame([NotesWidget::class => 'Test:Notes', SalesWidget::class => 'Test:Sales'], $components);

        $widgets = [...new AttributeDashboardWidgets($components)->widgets()];
        self::assertSame(
            ['test.notes', 'test.sales'],
            array_map(static fn (DashboardWidget $widget): string => $widget->key, $widgets),
        );
        self::assertSame('Test:Sales', $widgets[1]->component);
        self::assertSame(20, $widgets[1]->priority);

        $topbar = $container->getDefinition(AttributeTopbarWidgets::class)->getArgument('$components');
        self::assertSame([BellWidget::class => 'Test:Bell'], $topbar);
        self::assertEquals([new TopbarWidget('Test:Bell', 5)], [...new AttributeTopbarWidgets($topbar)->widgets()]);
    }

    public function testOneComponentIsSeveralWidgetsOfTheirOwnProps(): void
    {
        $container = $this->container();
        $container->register(CounterWidget::class, CounterWidget::class)
            ->addTag(AsDashboardWidget::TAG)
            ->addTag('twig.component', ['key' => 'Test:Counter']);

        new CollectWidgetsPass()->process($container);

        /** @var array<class-string, string> $components */
        $components = $container->getDefinition(AttributeDashboardWidgets::class)->getArgument('$components');
        $widgets = [...new AttributeDashboardWidgets($components)->widgets()];
        self::assertSame(
            ['test.orders', 'test.tickets'],
            array_map(static fn (DashboardWidget $widget): string => $widget->key, $widgets),
        );
        self::assertSame(
            ['Test:Counter', 'Test:Counter'],
            array_map(static fn (DashboardWidget $widget): string => $widget->component, $widgets),
        );
        self::assertSame(['counts' => 'tickets'], $widgets[1]->props);
    }

    public function testAComponentWithoutANameIsAnError(): void
    {
        $container = $this->container();
        $container->register(SalesWidget::class, SalesWidget::class)
            ->addTag(AsDashboardWidget::TAG)
            ->addTag('twig.component');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs the name of its component');

        new CollectWidgetsPass()->process($container);
    }

    public function testAKeyIsOneWidget(): void
    {
        $container = $this->container();
        foreach ([SalesWidget::class, OtherSalesWidget::class] as $class) {
            $container->register($class, $class)
                ->addTag(AsDashboardWidget::TAG)
                ->addTag('twig.component', ['key' => $class]);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"test.sales"');

        new CollectWidgetsPass()->process($container);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(AttributeDashboardWidgets::class, AttributeDashboardWidgets::class);
        $container->register(AttributeTopbarWidgets::class, AttributeTopbarWidgets::class);

        return $container;
    }
}
