<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\UseCase\MapsToRequest;

/**
 * Adds fields to the edit form of a resource declared elsewhere with {@see AsCrud} - and, with "create", to its add
 * form - named, not imported, so neither part knows the other. The fields are a DTO of their own, saved through its
 * own use case right after the record, in the same transaction (RecordTransaction): "what a user is notified about"
 * in the form of a user.
 *
 * Put it on an (empty) class of its own.
 *
 * - update: a form type (#[AsFormType]) with a public "id" - the id of the record being edited.
 * - read: a DTO with a public "id" whose use case returns the current values; the same-named properties of the
 *   result (scalars and lists of scalars) are what the fields start with.
 * - create: a form type with a public "id" too, for the add form: its own defaults to start with, the id of the new
 *   record put onto it once the record is saved - which needs a driver that tells the id of a new record (the
 *   doctrine one does; the dto one when the use case returns an object with an "id"). It may be the class of
 *   "update". Null: the section is not in the add form.
 *
 * A refusal of the section's use case is shown at its field, like one of the record's own.
 *
 * Repeatable: one section can extend the forms of several resources (the users of the administration and one's own
 * profile), an attribute for each.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsCrudFormSection
{
    /** Added to every class carrying the attribute (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.form_section';

    /**
     * @param string $resource name of the resource whose forms are extended, as in #[AsCrud]
     * @param string $name lower-case; the name of the section inside the form
     * @param string $label translation key of its heading
     * @param class-string<MapsToRequest> $update
     * @param class-string<MapsToRequest>|null $read
     * @param class-string<MapsToRequest>|null $create the DTO of the section in the add form; null = not there
     * @param string|null $role only somebody with it has the section - in the form and in what the form saves (asked
     *     with the resource as the subject, like AsCrud::$roles); null = everybody the form is for
     * @param int $priority where it stands among the sections of the form, lower first; equal ones by their class
     */
    public function __construct(
        public string $resource,
        public string $name,
        public string $label,
        public string $update,
        public ?string $read = null,
        public ?string $create = null,
        public ?string $role = null,
        public int $priority = 0,
    ) {
    }
}
