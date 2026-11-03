<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\ActionPlace;
use Byfareska\Crud\Resource\CrudRegistry;
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use LogicException;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * Draws an action of a record where it is shown: `crud_action(button, 'row')`. The theme draws it with the first block
 * there is of crud_<resource>_action_<name>_<place>, action_<name>_<place> and action_<place> - looked for in the
 * theme of the resource (AsCrud::$theme) first, then in @CrudTheme/crud/actions.html.twig - given "button": one
 * action of one resource drawn otherwise without copying them all. A button with a component of its own: the
 * component draws it, given its props, "button" and "place".
 */
final readonly class ActionButtonRenderer
{
    public const string THEME = '@CrudTheme/crud/actions.html.twig';

    public function __construct(
        private ComponentRendererInterface $components,
        private ?CrudRegistry $registry = null,
    ) {
    }

    /**
     * @param string $place a value of ActionPlace - "row", "menu" or "bar" - or a place of a theme of yours (a card of
     *     a list drawn as cards, a column of a kanban): the block action_<place> of its actions.html.twig, a component
     *     of an action given "bar"
     */
    #[AsTwigFunction('crud_action', needsEnvironment: true, isSafe: ['html'])]
    public function render(Environment $twig, ActionButton $button, string $place): string
    {
        $where = ActionPlace::tryFrom($place);
        if ($button->component !== null) {
            return $this->components->createAndRender(
                $button->component,
                ['button' => $button, 'place' => $where ?? ActionPlace::Bar] + $button->props,
            );
        }

        $place = $where->value ?? $place;
        $name = preg_replace('/\W/', '_', $button->name);
        $blocks = [sprintf('action_%s_%s', $name, $place), 'action_' . $place];
        $templates = [self::THEME];
        if ($button->resource !== null) {
            array_unshift($blocks, sprintf('crud_%s_action_%s_%s', $button->resource, $name, $place));
            $own = $this->registry?->get($button->resource)->theme;
            if ($own !== null) {
                array_unshift($templates, $own);
            }
        }
        foreach ($templates as $template) {
            $theme = $twig->load($template);
            foreach ($blocks as $block) {
                if ($theme->hasBlock($block)) {
                    return $theme->renderBlock($block, ['button' => $button]);
                }
            }
        }

        throw new LogicException(sprintf(
            'No block "action_%s" in %s: a place of your own has its block in the actions.html.twig of your theme.',
            $place,
            self::THEME,
        ));
    }
}
