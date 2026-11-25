<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Panel;

use Byfareska\Crud\Dashboard\DashboardWidgetProps;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\ComponentPanel;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The panel a live component is answered under: the one it names, the panel of the board a widget is on.
 */
final class ComponentPanelTest extends TestCase
{
    public function testAComponentOfAPanelIsOfThatPanel(): void
    {
        self::assertSame('admin', $this->panels()->of($this->scoped('admin')));
        self::assertSame('partner', $this->panels()->of($this->scoped('partner')), 'Only named by resources.');
    }

    public function testAWidgetOfABoardIsOfThePanelOfTheBoard(): void
    {
        self::assertSame('admin', $this->panels()->of($this->scoped('sales')));
        self::assertSame('admin', $this->panels()->of($this->widget('sales')), 'Without saying ScopedComponent.');
        self::assertSame('admin', $this->panels()->of($this->widget('admin')));
    }

    public function testAComponentThatKnowsNoPanelIsOfNone(): void
    {
        self::assertNull($this->panels()->of($this->scoped(null)));
        self::assertNull($this->panels()->of($this->scoped('')));
        self::assertNull($this->panels()->of($this->widget('')));
        self::assertNull($this->panels()->of(new \stdClass()));
    }

    private function panels(): ComponentPanel
    {
        $requests = new RequestStack();

        return new ComponentPanel(new Scopes(
            ['admin' => [
                'label' => null,
                'icon' => 'shield',
                'layout' => null,
                'theme' => null,
                'entries' => [],
                'role' => null,
                'dashboard' => true,
                'home' => null,
                'boards' => ['sales' => []],
            ]],
            'layout.html.twig',
            'bootstrap5',
            [],
            $requests,
            new RecordingActor([]),
            new CurrentCrud($requests),
        ));
    }

    private function scoped(?string $scope): ScopedComponent
    {
        return new readonly class ($scope) implements ScopedComponent {
            public function __construct(private ?string $scope)
            {
            }

            public function panelScope(): ?string
            {
                return $this->scope;
            }
        };
    }

    private function widget(string $board): object
    {
        $widget = new class {
            use DashboardWidgetProps;
        };
        $widget->board = $board;

        return $widget;
    }
}
