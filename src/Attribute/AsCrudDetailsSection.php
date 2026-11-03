<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;

/**
 * Adds a part to the details page of a resource declared with {@see AsCrud} - named, not imported, so a part of the
 * application can extend the page of another one's record: "who signed in to this account" under the details of a user.
 *
 * Put it on an (empty) class of its own.
 *
 * The part is a Twig component, rendered under the details and given the "id" of the record as it is in the URL.
 * It brings its own panel and heading, and decides for itself whether the one looking may see it: a component
 * with nothing to show renders nothing. Repeatable: an attribute for every resource it is shown under.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsCrudDetailsSection
{
    /** Added to every class carrying the attribute (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.details_section';

    /** The resource of a section under the details of every resource: its component gets `resource` besides `id`. */
    public const string EVERY = '*';

    /**
     * @param string $resource name of the resource whose details page is extended, as in #[AsCrud]; EVERY for all
     * @param string $name lower-case; tells the sections of one page apart
     * @param string $component name of the Twig component, e.g. "Identity:UserLogins"
     * @param string|null $role only somebody with it is shown the section (asked with the resource as the subject);
     *     null = everybody the details are for - the component may still decide for itself
     * @param int $priority where it stands among the sections of the details, lower first; equal ones by their class
     */
    public function __construct(
        public string $resource,
        public string $name,
        public string $component,
        public ?string $role = null,
        public int $priority = 0,
    ) {
    }
}
