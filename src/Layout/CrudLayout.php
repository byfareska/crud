<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

use LogicException;

/**
 * How the pages of a #[AsCrud] resource are laid out: a tree of tabs, columns, fieldsets and rows holding fields by
 * their names - the counterpart of EasyAdmin's FormField::addTab() / addColumn() / addFieldset() / addRow(), except
 * that a group holds its fields rather than standing before them.
 *
 *   top: Tab... or Column, Fieldset, Row, fields (tabs are all or nothing)
 *   Tab: Column, Fieldset, Row, fields
 *   Column: Fieldset, Row, fields
 *   Fieldset: Row, fields
 *   Row: fields
 *
 * A field is a name - of the form, of the record, or of a section another part of the application adds - or a Field.
 * A part of the application's own says where it may stand (PlacedLayoutNode); one wrapped in When is on a page only
 * when its LayoutCondition says so, and stands for its children.
 * One layout serves every page: each takes what it has (resolve()), and a group left with nothing disappears.
 */
final class CrudLayout
{
    private const array ALLOWED = [
        'top' => [Tab::class, Column::class, Fieldset::class, Row::class, Field::class],
        Tab::class => [Column::class, Fieldset::class, Row::class, Field::class],
        Column::class => [Fieldset::class, Row::class, Field::class],
        Fieldset::class => [Row::class, Field::class],
        Row::class => [Field::class],
    ];

    /**
     * @param list<LayoutNode|string> $nodes
     *
     * @throws LogicException when the tree breaks the rules above or names something twice
     */
    public static function validate(array $nodes): void
    {
        $top = self::unwrapped($nodes);
        $tabs = array_filter($top, static fn (LayoutNode|string $node): bool => $node instanceof Tab);
        if ($tabs !== [] && count($tabs) !== count($top)) {
            throw new LogicException('A layout with tabs has nothing but tabs at the top.');
        }

        $fields = [];
        $groups = [];
        self::check($nodes, 'top', $fields, $groups);
    }

    /**
     * What a page shows of a layout: the fields it has, in groups that still hold something.
     *
     * @param list<LayoutNode|string> $nodes
     * @param array<array-key, mixed> $available what the page has, by name
     * @param (\Closure(When): bool)|null $shows whether the parts a When wraps are on the page; null = they are
     *
     * @return list<LayoutNode> fields as Field; a When replaced by its children, or by nothing
     */
    public static function resolve(array $nodes, array $available, ?\Closure $shows = null): array
    {
        $resolved = [];
        foreach ($nodes as $node) {
            $node = is_string($node) ? new Field($node) : $node;
            if ($node instanceof When) {
                if (!$shows instanceof \Closure || $shows($node)) {
                    array_push($resolved, ...self::resolve($node->children, $available, $shows));
                }
            } elseif ($node instanceof Field) {
                if (array_key_exists($node->name, $available)) {
                    $resolved[] = $node;
                }
            } elseif ($node instanceof LayoutGroup) {
                $children = self::resolve($node->children(), $available, $shows);
                if ($children !== []) {
                    $resolved[] = $node->with($children);
                }
            } else {
                // A part of the application's own that holds nothing (a notice): on every page.
                $resolved[] = $node;
            }
        }

        return $resolved;
    }

    /**
     * The names of the fields a When keeps off the page (its condition says no), however deep in it.
     *
     * @param list<LayoutNode|string> $nodes
     * @param \Closure(When): bool $shows whether the parts a When wraps are on the page
     *
     * @return list<string>
     */
    public static function hidden(array $nodes, \Closure $shows): array
    {
        $hidden = [];
        foreach ($nodes as $node) {
            if ($node instanceof When) {
                $inside = $shows($node) ? self::hidden($node->children, $shows) : self::fields($node->children);
                array_push($hidden, ...$inside);
            } elseif ($node instanceof LayoutGroup) {
                array_push($hidden, ...self::hidden($node->children(), $shows));
            }
        }

        return $hidden;
    }

    /**
     * The nodes with every When replaced by its children: where they stand.
     *
     * @param list<LayoutNode|string> $nodes
     *
     * @return list<LayoutNode|string>
     */
    private static function unwrapped(array $nodes): array
    {
        $unwrapped = [];
        foreach ($nodes as $node) {
            if ($node instanceof When) {
                array_push($unwrapped, ...self::unwrapped($node->children));
            } else {
                $unwrapped[] = $node;
            }
        }

        return $unwrapped;
    }

    /**
     * The names of the fields of a layout, in order.
     *
     * @param list<LayoutNode|string> $nodes
     *
     * @return list<string>
     */
    public static function fields(array $nodes): array
    {
        $names = [];
        foreach ($nodes as $node) {
            if (is_string($node)) {
                $names[] = $node;
            } elseif ($node instanceof When) {
                array_push($names, ...self::fields($node->children));
            } elseif ($node instanceof Field) {
                $names[] = $node->name;
            } elseif ($node instanceof LayoutGroup) {
                array_push($names, ...self::fields($node->children()));
            }
        }

        return $names;
    }

    /**
     * @param list<LayoutNode|string> $nodes
     * @param array<string, true> $fields
     * @param array<string, true> $groups
     */
    private static function check(array $nodes, string $parent, array &$fields, array &$groups): void
    {
        $where = $parent === 'top' ? 'at the top' : 'in a ' . self::short($parent);
        foreach ($nodes as $node) {
            // It stands for its children: they stand where it does.
            if ($node instanceof When) {
                self::check($node->children, $parent, $fields, $groups);

                continue;
            }
            $class = is_string($node) ? Field::class : $node::class;
            if (!self::mayStand($class, $parent)) {
                throw new LogicException(sprintf('A %s cannot stand %s.', self::short($class), $where));
            }

            if ($node instanceof Field && ($node->cols !== null || $node->class !== null) && $parent !== Row::class) {
                throw new LogicException(sprintf(
                    'The field "%s" has a width or classes of its own, which only a cell of a Row gives it.',
                    $node->name,
                ));
            }

            $field = is_string($node) ? $node : ($node instanceof Field ? $node->name : null);
            if ($field !== null) {
                if (isset($fields[$field])) {
                    throw new LogicException(sprintf('The field "%s" is in the layout twice.', $field));
                }
                $fields[$field] = true;
            }

            if ($node instanceof LayoutGroup) {
                $name = $node->name();
                if ($name !== null) {
                    if (isset($groups[$name])) {
                        throw new LogicException(sprintf('Two parts of the layout are named "%s".', $name));
                    }
                    $groups[$name] = true;
                }
                self::check($node->children(), $node::class, $fields, $groups);
            }
        }
    }

    /**
     * Whether a node of the class may stand in the parent: the bundle's rules, or what a PlacedLayoutNode says - of
     * itself, or as the parent, of what it holds.
     */
    private static function mayStand(string $class, string $parent): bool
    {
        $holds = self::ALLOWED[$parent]
            ?? (is_a($parent, PlacedLayoutNode::class, true) ? $parent::holds() : []);

        return in_array($class, $holds, true)
            || (is_a($class, PlacedLayoutNode::class, true) && in_array($parent, $class::placedIn(), true));
    }

    private static function short(string $class): string
    {
        return $class === Field::class ? 'field' : strtolower(substr($class, (int) strrpos($class, '\\') + 1));
    }
}
