<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Fills the add form of a resource in from something pasted (a curl command, ...): the form shows a field for it above
 * its own, and what the filler makes of it goes into the visible fields - nothing is saved, the form is then checked
 * and saved as typed. Texts: crud.<resource>.filler.<name>.{title,help,placeholder,button,unreadable}.
 */
#[AutoconfigureTag(self::TAG)]
interface CrudFormFiller
{
    public const string TAG = 'byfareska_crud.form_filler';

    /** The resource whose add form it fills in, as in #[AsCrud]. */
    public function resource(): string;

    /** What it is known by among the fillers of the resource: [a-z0-9_]+. */
    public function name(): string;

    public function fill(string $input): CrudFill;
}
