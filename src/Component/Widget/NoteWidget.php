<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Widget;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Dashboard\DashboardWidgetProps;
use Byfareska\Crud\Dashboard\Dashboards;
use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSettingType;
use Byfareska\Crud\Dashboard\WidgetSize;
use Byfareska\Crud\Panel\ScopedComponent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * A sticky note on the dashboard: plain text with its line breaks, kept as it is typed (after a pause) in the settings
 * of its place - so every user has their own, also when the layout was the one for everybody until then.
 */
#[AsDashboardWidget(
    key: 'panel.note',
    title: 'panel.widget.note.title',
    description: 'panel.widget.note.description',
    icon: 'sticky',
    accent: '#eab308',
    size: new WidgetSize(3, 2),
    minSize: new WidgetSize(2, 2),
    maxSize: new WidgetSize(6, 4),
    priority: 900,
    // Typed into the widget itself, not into the dialog of the settings.
    // Personal: a layout shared with others keeps the note, not its text.
    settings: [new WidgetSetting('text', 'panel.widget.note.title', WidgetSettingType::Hidden, personal: true)],
    lazy: false,
    tone: 'yellow',
)]
#[AsLiveComponent('Panel:NoteWidget', template: 'components/Panel/NoteWidget.html.twig')]
final class NoteWidget implements ScopedComponent
{
    use DashboardWidgetProps;
    use DefaultActionTrait;

    public const int MAX_LENGTH = 5000;

    #[LiveProp(writable: true, onUpdated: 'save')]
    public string $text = '';

    public bool $saved = false;

    public function __construct(
        private readonly Dashboards $dashboards,
    ) {
    }

    /**
     * @param array<string, string|list<string>> $settings
     */
    public function mount(string $placement = '', array $settings = [], string $board = ''): void
    {
        $this->placement = $placement;
        $this->board = $board;
        $this->settings = $settings;
        $this->text = $this->setting('text');
    }

    public function save(): void
    {
        $this->text = mb_substr($this->text, 0, self::MAX_LENGTH);
        try {
            $this->dashboards->configure($this->board, $this->placement, ['text' => $this->text]);
            $this->saved = true;
        } catch (NotFoundHttpException) {
            // Taken off the dashboard in another tab meanwhile.
        }
    }
}
