<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Byfareska\Crud\Security\Actor;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The widgets of every DashboardWidgetSource (#[AsDashboardWidget] among them), those the one acting may have, but
 * for those byfareska_crud.dashboard.disabled_widgets takes out and those a DashboardWidgetFilter leaves out for the
 * request. Read once a request (a widget may depend on who is asking: the next request is read anew - reset()).
 */
final class DashboardWidgets implements ResetInterface
{
    /** @var list<DashboardWidget>|null */
    private ?array $all = null;

    /** @var array<string, array<string, DashboardWidget>> by board */
    private array $available = [];

    /**
     * @param iterable<DashboardWidgetSource> $sources
     * @param list<string> $disabled keys of the widgets left out
     * @param iterable<DashboardWidgetFilter> $filters
     */
    public function __construct(
        #[AutowireIterator(DashboardWidgetSource::TAG)]
        private readonly iterable $sources,
        private readonly Actor $actor,
        #[AutowireLocator(WidgetChoices::TAG)]
        private readonly ContainerInterface $choices,
        private readonly array $disabled = [],
        #[AutowireIterator(DashboardWidgetFilter::TAG)]
        private readonly iterable $filters = [],
    ) {
    }

    /**
     * @return array<string, DashboardWidget> what the one acting may put on the dashboard, by key, by priority - with
     *     the choices of its settings for this dashboard
     */
    public function available(string $board): array
    {
        if (isset($this->available[$board])) {
            return $this->available[$board];
        }

        $filters = [...$this->filters];
        $widgets = [];
        foreach ($this->all() as $widget) {
            if (
                $widget->isOn($board)
                && ($widget->role === null || $this->actor->isGranted($widget->role))
                && array_all(
                    $filters,
                    static fn (DashboardWidgetFilter $filter): bool => $filter->allows($widget, $board),
                )
            ) {
                $widgets[$widget->key] = $this->withChoices($widget, $board);
            }
        }

        return $this->available[$board] = $widgets;
    }

    /**
     * Every widget declared and not disabled, whoever may have it, by priority.
     *
     * @return list<DashboardWidget>
     */
    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }

        $widgets = [];
        foreach ($this->sources as $source) {
            foreach ($source->widgets() as $widget) {
                if (!in_array($widget->key, $this->disabled, true)) {
                    $widgets[] = $this->withChoices($widget, null);
                }
            }
        }
        usort($widgets, static fn (DashboardWidget $a, DashboardWidget $b): int => $a->priority <=> $b->priority);

        return $this->all = $widgets;
    }

    public function reset(): void
    {
        $this->all = null;
        $this->available = [];
    }

    public function find(string $key, string $board): ?DashboardWidget
    {
        return $this->available($board)[$key] ?? null;
    }

    /**
     * The widget with the choices of the WidgetChoices its settings name - for the dashboard, when they depend on it.
     */
    private function withChoices(DashboardWidget $widget, ?string $board): DashboardWidget
    {
        if (!array_any($widget->settings, static fn (WidgetSetting $setting): bool => $setting->choicesFrom !== null)) {
            return $widget;
        }

        return $widget->withSettings(array_map(function (WidgetSetting $setting) use ($widget, $board): WidgetSetting {
            if ($setting->choicesFrom === null) {
                return $setting;
            }
            if (!$this->choices->has($setting->choicesFrom)) {
                throw new LogicException(sprintf(
                    'The setting "%s" of the widget "%s" takes its choices from "%s": no service tagged "%s" has '
                    . 'that index.',
                    $setting->name,
                    $widget->key,
                    $setting->choicesFrom,
                    WidgetChoices::TAG,
                ));
            }
            $choices = $this->choices->get($setting->choicesFrom);
            assert($choices instanceof WidgetChoices);

            return $setting->withChoices(
                $board !== null && $choices instanceof BoardWidgetChoices
                    ? $choices->choicesFor($board)
                    : $choices->choices(),
            );
        }, $widget->settings));
    }
}
