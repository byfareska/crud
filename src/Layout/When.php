<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * Parts of a layout on a page only when a {@see LayoutCondition} says so - asked when the page is drawn, with the
 * record and whoever is looking:
 *
 *     layout: [new Tab('basics', children: [...]), new When(PaidOrder::class, [new Tab('refund', children: [...])])]
 *
 * It stands for its children: they stand where it stands (a tab at the top, a field in a row) and are checked as if it
 * were not there. Not shown, its fields are not on the page, and the form component takes them out of the form: they
 * cannot be sent either.
 *
 * It arranges the forms and the details only: the list, its search, order and filters and the command palette still
 * have the property. To keep a property from somebody, hide it - AsCrud::$hidden, for a request by a
 * CrudDefinitionExtension: `$crud->with(hidden: [...$crud->hidden, 'margin'])`.
 */
final readonly class When implements LayoutNode
{
    /**
     * @param class-string<LayoutCondition> $condition the service that decides
     * @param list<LayoutNode|string> $children
     * @param array<string, mixed> $options what a PageLayoutCondition is given with the record: the status a part is
     *     shown for, so one class serves every status
     */
    public function __construct(
        public string $condition,
        public array $children,
        public array $options = [],
    ) {
    }
}
