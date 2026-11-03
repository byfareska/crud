<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Dashboard;

use Byfareska\Crud\Dashboard\WidgetView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The gallery of widgets of the dashboard, by category, with the sizes each can be added in. Searched and added
 * by the dashboard-grid controller of the page it is in.
 */
#[AsTwigComponent('Panel:WidgetGallery', template: 'components/Panel/WidgetGallery.html.twig')]
final class WidgetGallery
{
    /** @var list<WidgetView> */
    public array $widgets = [];

    /**
     * @return array<string, list<WidgetView>> by the translation key of the category, in the order of the first widget
     *     of each
     */
    public function getCategories(): array
    {
        $categories = [];
        foreach ($this->widgets as $widget) {
            $categories[$widget->widget->category][] = $widget;
        }

        return $categories;
    }
}
