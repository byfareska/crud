<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Dashboard;

use Byfareska\Crud\Dashboard\Dashboards;
use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Dashboard\PlacementView;
use Byfareska\Crud\Dashboard\WidgetSettingRefused;
use Byfareska\Crud\Panel\ScopedComponent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The dialog of the settings of one widget of a dashboard, drawn from what the widget declares (WidgetSetting).
 * Opened by the dashboard-grid controller (live action "open" with the id of the placement).
 */
#[AsLiveComponent('Panel:WidgetSettings', template: 'components/Panel/WidgetSettings.html.twig')]
final class WidgetSettings implements ScopedComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public string $placement = '';

    /** The dashboard the widget is on (the name of its panel). */
    #[LiveProp]
    public string $board = '';

    /** @var array<string, string|list<string>> */
    #[LiveProp(writable: true)]
    public array $values = [];

    /**
     * The place read for $placement in this request, by it: the dialog asks for it several times when it is drawn,
     * and each is a reading of the layout (a query with the doctrine storage).
     *
     * @var array<string, PlacementView|null>
     */
    private array $placed = [];

    public function __construct(
        private readonly Dashboards $dashboards,
    ) {
    }

    public function panelScope(): ?string
    {
        return $this->board === '' ? null : $this->board;
    }

    #[LiveAction]
    public function open(#[LiveArg] string $id): void
    {
        $this->placement = $id;
        $this->values = $this->getPlaced()->settings ?? [];
    }

    /**
     * What a validator refused at the last save, by the setting: the dialog stays open and shows it.
     *
     * @var array<string, WidgetSettingRefused>
     */
    public array $refused = [];

    #[LiveAction]
    public function save(): void
    {
        // The layout changes now: what was read of it is not what it will be.
        $this->placed = [];
        try {
            $this->dashboards->configure($this->board, $this->placement, $this->values);
        } catch (NotFoundHttpException) {
            // Removed in another tab: the page shows what there is.
        } catch (WidgetSettingRefused $refused) {
            $this->refused[$refused->setting ?? ''] = $refused;

            return;
        }
        $this->dispatchBrowserEvent('dashboard:changed');
    }

    /**
     * The widget's place on the dashboard. Not getPlacement(): the property accessor would read it for the LiveProp.
     */
    public function getPlaced(): ?PlacementView
    {
        if ($this->placement === '') {
            return null;
        }
        $key = $this->board . "\0" . $this->placement;
        if (!array_key_exists($key, $this->placed)) {
            $this->placed[$key] = $this->dashboards->placement($this->board, $this->placement);
        }

        return $this->placed[$key];
    }

    public function getWidget(): ?DashboardWidget
    {
        return $this->getPlaced()?->widget;
    }
}
