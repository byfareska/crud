<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Layout\CrudLayout;
use Byfareska\Crud\Layout\CrudLayoutView;
use Byfareska\Crud\Layout\Field;
use Byfareska\Crud\Layout\Fieldset;
use Byfareska\Crud\Layout\LayoutCondition;
use Byfareska\Crud\Layout\LayoutGroup;
use Byfareska\Crud\Layout\LayoutNode;
use Byfareska\Crud\Layout\LayoutPage;
use Byfareska\Crud\Layout\PageLayoutCondition;
use Byfareska\Crud\Layout\Tab;
use Byfareska\Crud\Layout\When;
use Closure;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFunction;

/**
 * The layout of a page of a resource, ready to be drawn by crud_layout(): the form, or the details of a record.
 *
 * @phpstan-import-type RecordDetails from CrudRecords
 */
final readonly class CrudLayouts
{
    public function __construct(
        private CrudRegistry $registry,
        private CrudRecords $records,
        #[AutowireLocator(LayoutCondition::TAG)]
        private ContainerInterface $conditions = new ServiceLocator([]),
    ) {
    }

    /**
     * The add (no id) or edit form. Its fields, the sections other parts of the application add to it and its fillers
     * (CrudFormFiller) are what the layout can place; a field it does not is drawn after it by form_rest(), a filler
     * above the form.
     *
     * @param array<string, array{name: string, input: string, filled: CrudFill|null, used: bool}> $fillers by name
     */
    public function form(
        AsCrud $crud,
        FormView $form,
        ?string $id,
        ?string $tab = null,
        array $fillers = [],
    ): CrudLayoutView {
        // The record the conditions of When go by: read once, only when one asks; none on the add form.
        $record = null;
        $nodes = CrudLayout::resolve(
            $crud->formLayout,
            $form->children + $fillers,
            $this->shows($crud, LayoutPage::Form, function () use ($crud, $id, &$record): ?RecordView {
                return $id === null ? null : $record ??= $this->records->view($crud, $id, RecordScreen::Form);
            }),
        );

        // A tab's badge is a value of the record - read only when one is asked for.
        $item = [];
        $badges = array_filter($nodes, static fn (object $node): bool => $node instanceof Tab && $node->badge !== null);
        if ($badges !== [] && $id !== null && $crud->can(CrudOperation::Read)) {
            $item = $this->records->read($crud, $id);
        }

        return new CrudLayoutView(
            $crud,
            LayoutPage::Form,
            $nodes,
            $id,
            form: $form,
            item: $item,
            tab: $tab,
            fillers: $fillers,
        );
    }

    /**
     * The fields (and sections) of the form a When of the form's layout keeps off the page for this record: not to be
     * in the form at all, so nothing sent for them is taken.
     *
     * @param RecordView|null $record the record being edited; null on the add form
     *
     * @return list<string>
     */
    public function hiddenInForm(AsCrud $crud, ?RecordView $record): array
    {
        return CrudLayout::hidden(
            $crud->formLayout,
            $this->shows($crud, LayoutPage::Form, static fn (): ?RecordView => $record),
        );
    }

    /**
     * The details page or, compact, the preview drawer, of a record as CrudRecords::details() gives it. Without a
     * layout: its values one under another. The sections other parts of the application add under the details can be
     * placed on the page, not in the drawer.
     *
     * @param RecordDetails $details
     */
    #[AsTwigFunction('crud_details_layout')]
    public function details(AsCrud $crud, array $details, bool $compact = false, ?string $tab = null): CrudLayoutView
    {
        $rows = [];
        foreach ($details['rows'] as $row) {
            $rows[$row['column']->field] = $row;
        }

        $sections = $compact ? [] : $this->registry->detailsSectionsOf($crud->name);
        $layout = $crud->detailsLayout !== []
            ? $crud->detailsLayout
            : array_map(static fn (string $field): Field => new Field($field), array_keys($rows));
        $record = new RecordView(
            $crud,
            $details['id'],
            $details['item'],
            $compact ? RecordScreen::Preview : RecordScreen::Details,
        );
        $nodes = self::withoutEmptyFieldsets(
            CrudLayout::resolve(
                $layout,
                $rows + $sections,
                $this->shows($crud, LayoutPage::Detail, static fn (): RecordView => $record),
            ),
            $rows,
        );

        $placed = array_intersect_key($sections, array_flip(CrudLayout::fields($nodes)));

        return new CrudLayoutView(
            $crud,
            LayoutPage::Detail,
            $nodes,
            $details['id'],
            rows: $rows,
            item: $details['item'],
            sections: $placed,
            compact: $compact,
            tab: $tab,
        );
    }

    /**
     * A fieldset of the details marked hideEmpty whose every value is null says nothing (the server of a mailbox of
     * another kind): it is left out, and a group left with nothing goes too. A section of another part of the
     * application keeps its group.
     *
     * @param list<LayoutNode|string> $nodes
     * @param array<string, array{column: Column, value: mixed}> $rows
     *
     * @return list<LayoutNode>
     */
    private static function withoutEmptyFieldsets(array $nodes, array $rows): array
    {
        $kept = [];
        foreach ($nodes as $node) {
            $node = is_string($node) ? new Field($node) : $node;
            if (!$node instanceof LayoutGroup) {
                $kept[] = $node;
                continue;
            }

            $fields = CrudLayout::fields($node->children());
            if (
                $node instanceof Fieldset
                && $node->hideEmpty
                && $fields !== []
                && array_all(
                    $fields,
                    static fn (string $field): bool => isset($rows[$field]) && $rows[$field]['value'] === null,
                )
            ) {
                continue;
            }
            $children = self::withoutEmptyFieldsets($node->children(), $rows);
            if ($children !== []) {
                $kept[] = $node->with($children);
            }
        }

        return $kept;
    }

    /**
     * Whether the parts a When wraps are on a page: what its LayoutCondition says of the record.
     *
     * @param Closure(): (RecordView|null) $record
     *
     * @return Closure(When): bool
     */
    private function shows(AsCrud $crud, LayoutPage $page, Closure $record): Closure
    {
        return function (When $when) use ($crud, $page, $record): bool {
            if (!$this->conditions->has($when->condition)) {
                throw new LogicException(sprintf(
                    'A part of the layout of "%s" is shown when %s says so, which is no service tagged "%s".',
                    $crud->name,
                    $when->condition,
                    LayoutCondition::TAG,
                ));
            }
            $condition = $this->conditions->get($when->condition);
            assert($condition instanceof LayoutCondition);

            // One that knows the page and the options of its When (PageLayoutCondition), else by the record alone.
            return $condition instanceof PageLayoutCondition
                ? $condition->showsOn($crud, $record(), $page, $when->options)
                : $condition->shows($crud, $record());
        };
    }
}
