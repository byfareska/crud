<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\Dashboard\AttributeDashboardWidgets;
use Byfareska\Crud\Dashboard\BoardWidgetChoices;
use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Dashboard\DashboardWidgetFilter;
use Byfareska\Crud\Dashboard\DashboardWidgets;
use Byfareska\Crud\Dashboard\WidgetChoices;
use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\NotesWidget;
use Byfareska\Crud\Tests\Unit\Fixtures\SalesWidget;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DashboardWidgetsTest extends TestCase
{
    public function testFillsInTheChoicesOfServices(): void
    {
        $sales = $this->widgets()->find('test.sales', 'panel');

        self::assertNotNull($sales);
        self::assertSame(['7' => 'week', '30' => 'month'], $sales->settings[0]->choices);
        self::assertSame(['waw' => 'Warsaw', 'krk' => 'Kraków'], $sales->settings[1]->choices);
        self::assertSame(['period' => '30', 'city' => 'krk'], $sales->settingsOf(['period' => '30', 'city' => 'krk']));
    }

    public function testChoicesThatDependOnTheDashboard(): void
    {
        $cities = new class implements BoardWidgetChoices {
            public function choices(): array
            {
                return ['waw' => 'Warsaw', 'krk' => 'Kraków'];
            }

            public function choicesFor(string $board): array
            {
                return $board === 'admin' ? ['gda' => 'Gdańsk'] : $this->choices();
            }
        };
        $widgets = new DashboardWidgets(
            [$this->source()],
            $this->actor(),
            new ServiceLocator(['test.cities' => static fn (): object => $cities]),
        );

        self::assertSame(['gda' => 'Gdańsk'], $widgets->find('test.sales', 'admin')?->settings[1]->choices);
        self::assertSame(
            ['waw' => 'Warsaw', 'krk' => 'Kraków'],
            $widgets->find('test.sales', 'panel')?->settings[1]->choices,
        );
        self::assertSame('test.cities', $widgets->all()[1]->settings[1]->choicesFrom ?? null);
    }

    public function testLeavesOutTheDisabled(): void
    {
        self::assertSame(['test.notes', 'test.sales'], array_keys($this->widgets()->available('panel')));
        self::assertSame(['test.sales'], array_keys($this->widgets(['test.notes'])->available('panel')));
    }

    public function testAFilterLeavesWidgetsOutForTheRequest(): void
    {
        $noNotes = new class implements DashboardWidgetFilter {
            public function allows(DashboardWidget $widget, string $board): bool
            {
                return $widget->key !== 'test.notes';
            }
        };
        $widgets = new DashboardWidgets(
            [$this->source()],
            $this->actor(),
            $this->choices(),
            filters: [$noNotes],
        );

        self::assertSame(['test.sales'], array_keys($widgets->available('panel')));
        self::assertSame($widgets->available('panel'), $widgets->available('panel'), 'Read once a request.');
    }

    public function testAWidgetChangedKeepsWhatIsNotChanged(): void
    {
        $widget = new DashboardWidget(
            'stock.warehouse_5',
            'App:Stock',
            'Magazyn 5',
            'Stany',
            'box',
            settings: [new WidgetSetting('period', 'app.period', choicesFrom: 'app.periods')],
            props: ['warehouse' => 5],
        );

        $chosen = $widget->withSettings([new WidgetSetting('period', 'app.period', choices: ['7' => 'app.week'])]);

        self::assertSame(['warehouse' => 5], $chosen->props, 'Its props stay: one widget per record.');
        self::assertSame(['7'], $chosen->settings[0]->values());
        self::assertSame('Inny', $widget->with(title: 'Inny')->title);

        $this->expectException(InvalidArgumentException::class);
        $widget->with(colour: 'red');
    }

    public function testUnknownChoicesAreAnError(): void
    {
        $widgets = new DashboardWidgets([$this->source()], $this->actor(), new ServiceLocator([]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"test.cities"');

        $widgets->all();
    }

    /**
     * @param list<string> $disabled
     */
    private function widgets(array $disabled = []): DashboardWidgets
    {
        return new DashboardWidgets([$this->source()], $this->actor(), $this->choices(), $disabled);
    }

    /**
     * @return ServiceLocator<WidgetChoices>
     */
    private function choices(): ServiceLocator
    {
        $cities = new class implements WidgetChoices {
            public function choices(): array
            {
                return ['waw' => 'Warsaw', 'krk' => 'Kraków'];
            }
        };

        return new ServiceLocator(['test.cities' => static fn (): object => $cities]);
    }

    private function source(): AttributeDashboardWidgets
    {
        return new AttributeDashboardWidgets([SalesWidget::class => 'Test:Sales', NotesWidget::class => 'Test:Notes']);
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
