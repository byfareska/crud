<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Byfareska\Crud\Layout\Column;
use Byfareska\Crud\Layout\CrudLayoutView;
use Byfareska\Crud\Layout\Field;
use Byfareska\Crud\Layout\LayoutGroup;
use Byfareska\Crud\Layout\LayoutNode;
use Byfareska\Crud\Layout\NamedLayoutNode;
use Byfareska\Crud\Layout\Tab;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * Draws the layout of a page of a #[AsCrud] resource (see CrudLayout) with Twig blocks, the way crud_value() draws
 * a value: for a group named "password" of the resource "user", the first of
 *
 *   crud_user_layout_password: this group of this resource
 *   layout_fieldset: every fieldset (layout_tab, layout_column, layout_row, layout_field)
 *
 * looked for in the resource's own theme and then in @CrudTheme/crud/layout.html.twig. Two more blocks draw what
 * stands together: layout_tabs (the bar and the panes) and layout_columns (columns side by side).
 *
 * A block gets: layout (the CrudLayoutView), node, crud, label and help (translation keys of a named group; help is
 * null when there is no such text), and what the block above passes on (active, alone).
 */
final readonly class CrudLayoutRenderer
{
    public const string DEFAULT_THEME = '@CrudTheme/crud/layout.html.twig';

    /**
     * @param list<string> $themes templates of blocks of the application looked into for every resource, after its own
     *     theme (byfareska_crud.value_themes)
     */
    public function __construct(
        private TranslatorInterface $translator,
        #[Autowire(param: 'byfareska_crud.value_themes')]
        private array $themes = [],
    ) {
    }

    /**
     * Draws parts of a layout - all of it, by default.
     *
     * @param list<LayoutNode|string>|null $nodes
     */
    #[AsTwigFunction('crud_layout', needsEnvironment: true, isSafe: ['html'])]
    public function render(Environment $twig, CrudLayoutView $layout, ?array $nodes = null): string
    {
        $nodes = array_map(
            static fn (LayoutNode|string $node): LayoutNode => is_string($node) ? new Field($node) : $node,
            $nodes ?? $layout->nodes,
        );

        // Tabs are all or nothing; with a single one there is nothing to switch between.
        $tabs = array_filter($nodes, static fn (LayoutNode $node): bool => $node instanceof Tab);
        if (count($tabs) === 1) {
            return $this->render($twig, $layout, array_values($tabs)[0]->children());
        }
        if ($tabs !== []) {
            return $this->block($twig, $layout, ['layout_tabs'], ['tabs' => array_values($tabs)]);
        }

        $html = '';
        $columns = [];
        foreach ([...$nodes, null] as $node) {
            // Columns that follow each other stand side by side, in one row of the grid.
            if ($node instanceof Column) {
                $columns[] = $node;
                continue;
            }
            if ($columns !== []) {
                $html .= $this->block($twig, $layout, ['layout_columns'], ['columns' => $columns]);
                $columns = [];
            }
            if ($node !== null) {
                $html .= $this->part($twig, $layout, $node);
            }
        }

        return $html;
    }

    /**
     * Draws one part of a layout by its own block.
     *
     * @param array<string, mixed> $context passed on to the block
     */
    #[AsTwigFunction('crud_layout_part', needsEnvironment: true, isSafe: ['html'])]
    public function part(Environment $twig, CrudLayoutView $layout, LayoutNode $node, array $context = []): string
    {
        // By its short class name, or by the name it gives its block (NamedLayoutNode).
        $kind = $node instanceof NamedLayoutNode
            ? $node::blockName()
            : strtolower(substr($node::class, (int) strrpos($node::class, '\\') + 1));
        $blocks = ['layout_' . $kind];
        $context['node'] = $node;

        if ($node instanceof LayoutGroup && $node->name() !== null) {
            $name = $node->name();
            array_unshift($blocks, sprintf('crud_%s_layout_%s', $layout->crud->name, $name));
            $context['label'] = $layout->label($node);
            $help = $layout->crud->key(sprintf('layout.%s.help', $name));
            $hasHelp = $this->translator instanceof TranslatorBagInterface
                && $this->translator->getCatalogue()->has($help);
            $context['help'] = $hasHelp ? $help : null;
        }

        return $this->block($twig, $layout, $blocks, $context);
    }

    /**
     * @param list<string> $blocks most specific first
     * @param array<string, mixed> $context
     */
    private function block(Environment $twig, CrudLayoutView $layout, array $blocks, array $context): string
    {
        $context += ['layout' => $layout, 'crud' => $layout->crud, 'label' => null, 'help' => null];
        $themes = array_map(
            $twig->load(...),
            array_values(array_filter([$layout->crud->theme, ...$this->themes, self::DEFAULT_THEME])),
        );
        foreach ($blocks as $block) {
            foreach ($themes as $theme) {
                if ($theme->hasBlock($block, $context)) {
                    return $theme->renderBlock($block, $context);
                }
            }
        }

        throw new LogicException(sprintf('No theme has the block "%s".', end($blocks)));
    }
}
