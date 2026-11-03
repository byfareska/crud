<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;

/**
 * A link to a page of the application, which gets the record's id - e.g. "sign in as this user". A full page load:
 * it may change who is signed in. See {@see RecordAction}.
 */
final readonly class RouteAction implements PrioritizedAction
{
    /**
     * @param string $name lower-case; part of the translation key
     * @param string $route name of a route with the parameter "id"
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action; the page it leads to still has to
     *     refuse everybody else
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param bool $page the way to a page of {@see AsCrudPage}: its address has the id like the other pages of the
     *     record - none for an "own" resource
     * @param string $idParameter the parameter of the route the id goes to: "category" for the list of a resource at
     *     /categories/{category}/products, the products of the record
     * @param array<string, scalar> $parameters more parameters of the route (and of its query): ['status' => 'open']
     * @param string|null $label translation key of its text; null = crud.<resource>.action.<name>
     * @param string|null $target where it opens: "_blank" for a new tab (a report, a PDF)
     * @param bool $turbo the page is visited by Turbo; false (by default) = a full page load, as for a page that may
     *     change who is signed in
     * @param int $priority where it stands among the actions of a record, lower first (PrioritizedAction)
     * @param array<string, string> $fieldParameters parameters of the route taken from the record, by the field:
     *     ['customer' => 'customerId'] leads from an order to /customers/{customer}/invoices
     * @param ActionTone|null $tone the colour of its button; null = Secondary
     */
    public function __construct(
        public string $name,
        public string $route,
        public string $icon,
        public ?string $role = null,
        public bool $inline = false,
        public bool $page = false,
        public string $idParameter = 'id',
        public array $parameters = [],
        public ?string $label = null,
        public ?string $target = null,
        public bool $turbo = false,
        public int $priority = 0,
        public array $fieldParameters = [],
        public ?ActionTone $tone = null,
    ) {
    }

    /**
     * The parameters of its route for a record.
     *
     * @param array<string, mixed> $item the record, for $fieldParameters
     *
     * @return array<string, mixed>
     */
    public function parameters(AsCrud $crud, string $id, array $item = []): array
    {
        $parameters = $this->parameters;
        foreach ($this->fieldParameters as $parameter => $field) {
            $value = $item[$field] ?? null;
            $parameters[$parameter] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $this->page
            ? $crud->recordParameters($id, $parameters)
            : [$this->idParameter => $id] + $parameters;
    }

    public function fields(): array
    {
        return array_values($this->fieldParameters);
    }

    public function handler(): string
    {
        return RouteActionHandler::class;
    }
}
