<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Resource\Column as ListColumn;
use Byfareska\Crud\Resource\CrudFill;
use LogicException;
use Symfony\Component\Form\FormView;

/**
 * The layout of one page of a resource as it is drawn: what is left of it on that page (CrudLayout::resolve()), the
 * tab in view and what the blocks of @CrudTheme/crud/layout.html.twig need to draw a field - the form, or the record
 * and the sections other parts of the application add under its details. Made by CrudLayouts, drawn by crud_layout().
 */
final readonly class CrudLayoutView
{
    /** The tab in view; null without tabs. */
    public ?string $activeTab;

    /**
     * @param list<LayoutNode> $nodes resolved for this page
     * @param string|null $id of the record; null when adding one
     * @param array<string, array{column: ListColumn, value: mixed}> $rows the values of the record by field (details)
     * @param array<string, mixed> $item the whole record, for crud_value() and badges
     * @param array<string, AsCrudDetailsSection> $sections placed in the layout, by name (details)
     * @param bool $compact the preview drawer: everything one under another
     * @param array<string, array{name: string, input: string, filled: CrudFill|null, used: bool}> $fillers of the form,
     *     by name
     */
    public function __construct(
        public AsCrud $crud,
        public LayoutPage $view,
        public array $nodes,
        public ?string $id = null,
        public ?FormView $form = null,
        public array $rows = [],
        public array $item = [],
        public array $sections = [],
        public bool $compact = false,
        ?string $tab = null,
        public array $fillers = [],
    ) {
        $this->activeTab = $this->chooseTab($tab);
    }

    /**
     * Whether the page is only fields one under another - no layout, or one without groups.
     */
    public function isPlain(): bool
    {
        return array_all($this->nodes, static fn (LayoutNode $node): bool => $node instanceof Field);
    }

    /**
     * @return list<Tab>
     */
    public function tabs(): array
    {
        return array_values(array_filter($this->nodes, static fn (LayoutNode $node): bool => $node instanceof Tab));
    }

    public function isActive(Tab $tab): bool
    {
        return $tab->name === $this->activeTab;
    }

    /**
     * The translation key of the heading of a group.
     */
    public function label(LayoutGroup $group): string
    {
        return $this->crud->key(sprintf('layout.%s.name', $group->name()));
    }

    /**
     * The id of a tab in the page.
     */
    public function domId(Tab $tab): string
    {
        return sprintf('crud-%s-%s-%s', $this->crud->name, $this->view->value, $tab->name);
    }

    /**
     * How many errors the form has in the fields of a group (or in one field).
     */
    public function errors(LayoutNode $node): int
    {
        if (!$this->form instanceof FormView) {
            return 0;
        }

        $count = 0;
        foreach (CrudLayout::fields([$node]) as $field) {
            if (isset($this->form->children[$field])) {
                $count += $this->errorsIn($this->form->children[$field]);
            }
        }

        return $count;
    }

    /**
     * What the badge of a tab shows: the property of the record it names when that is a number or a text that is
     * not empty.
     */
    public function badge(Tab $tab): string|int|float|null
    {
        $value = $tab->badge === null ? null : ($this->item[$tab->badge] ?? null);

        return is_int($value) || is_float($value) || (is_string($value) && $value !== '') ? $value : null;
    }

    /**
     * @return array{column: ListColumn, value: mixed}
     */
    public function row(string $field): array
    {
        return $this->rows[$field]
            ?? throw new LogicException(sprintf('The record of "%s" has no field "%s".', $this->crud->name, $field));
    }

    /**
     * A filler of the form (CrudFormFiller) the layout names, with what was pasted into it and what it made of it.
     *
     * @return array{name: string, input: string, filled: CrudFill|null, used: bool}|null
     */
    public function filler(string $name): ?array
    {
        return $this->fillers[$name] ?? null;
    }

    public function section(string $name): ?AsCrudDetailsSection
    {
        return $this->sections[$name] ?? null;
    }

    /**
     * Whether a section another part of the application adds is drawn by the layout, rather than under it.
     */
    public function places(string $name): bool
    {
        return in_array($name, CrudLayout::fields($this->nodes), true);
    }

    private function errorsIn(FormView $view): int
    {
        $errors = $view->vars['errors'] ?? [];
        $count = is_countable($errors) ? count($errors) : 0;
        foreach ($view->children as $child) {
            $count += $this->errorsIn($child);
        }

        return $count;
    }

    /**
     * The tab asked for when there is one by that name, else the first - and in a form refused with errors, the
     * first tab that has some, unless the one asked for has.
     */
    private function chooseTab(?string $asked): ?string
    {
        $tabs = $this->tabs();
        if ($tabs === []) {
            return null;
        }

        $chosen = array_find($tabs, static fn (Tab $tab): bool => $tab->name === $asked) ?? $tabs[0];
        if ($this->errors($chosen) === 0) {
            $chosen = array_find($tabs, fn (Tab $tab): bool => $this->errors($tab) > 0) ?? $chosen;
        }

        return $chosen->name;
    }
}
