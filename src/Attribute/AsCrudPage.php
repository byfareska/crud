<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\Page\CrudContext;
use InvalidArgumentException;

/**
 * Adds a page to every record of a resource declared with {@see AsCrud} - its history, its invoices - named, not
 * imported, so a part of the application can add a page to another one's record.
 *
 * Put it on an (empty) class of its own. The page is a Twig component, given the "id" of the record as it is in the
 * URL, inside the frame of every page of a record (@CrudTheme/crud/record.html.twig): the layout of the panel, the
 * name of the record, the way back to it. The bundle makes its route, crud_<resource>_<name> at <path>/{id}/<name>
 * (<path>/<name> for an "own" resource), and leads to it from the record like a
 * {@see \Byfareska\Crud\Action\RouteAction}: from the details page, the preview drawer, the edit form and the menu of
 * a row of the list.
 *
 * The page is only for somebody the resource (and its panel) is for, and has "role" if given; a record that does not
 * exist is a 404 page; the component can ask crud_context() for the {@see CrudContext}. Its text, the name of the page
 * and of the way to it, is the translation crud.<resource>.action.<name>.
 *
 * A page needing more than a component - a form of its own, a POST - is a controller of the application's taking
 * #[MapCrud] instead.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsCrudPage
{
    /** Added to every class carrying the attribute (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.page';

    /** The resource of a page of the records of every resource: its component gets `resource` besides `id`. */
    public const string EVERY = '*';

    /** What a name of a page is made of: a part of its address and of its route. */
    public const string NAME = '/^[a-z][a-z0-9-]*$/';

    /** Names of the pages of the bundle, and of the address of the edit form. */
    public const array RESERVED = ['list', 'new', 'show', 'edit'];

    /**
     * @param string $resource name of the resource whose records get the page, as in #[AsCrud]; EVERY for all
     * @param string $name lower-case letters, digits and "-": the end of the address and of the route name
     * @param string $component name of the Twig component, e.g. "Audit:History"
     * @param string $icon a Bootstrap Icons name, of the way to it
     * @param string|null $role only somebody with this role is led to the page and may open it
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param int $priority where the way to it stands among the pages of a record, lower first
     * @param bool $collection a page of the whole resource, not of a record - an import, a kanban, a report: at
     *     <path>/<name>, led to by a button next to "new" on the list, for those who may see the list
     */
    public function __construct(
        public string $resource,
        public string $name,
        public string $component,
        public string $icon = 'file-earmark-text',
        public ?string $role = null,
        public bool $inline = false,
        public int $priority = 0,
        public bool $collection = false,
    ) {
        if (preg_match(self::NAME, $name) !== 1 || in_array($name, self::RESERVED, true)) {
            throw new InvalidArgumentException(sprintf(
                'The page "%s" of "%s": the name is lower-case letters, digits and "-", starting with a letter, and '
                . 'none of "%s".',
                $name,
                $resource,
                implode('", "', self::RESERVED),
            ));
        }
    }
}
