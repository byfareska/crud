<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Another button of the add and edit forms of a resource, next to "save": it saves the same way, then leads where it
 * says - "save and go to the lines of the order", "save and send". A service, autoconfigured. RecordSaving and
 * RecordSaved know which button saved ($then), so a listener does what it promises (the invoice sent, in the
 * transaction of the save).
 */
#[AutoconfigureTag(self::TAG)]
interface CrudSaveButton
{
    public const string TAG = 'byfareska_crud.save_button';

    /** A button of the forms of every resource. */
    public const string EVERY = '*';

    /** The name of the resource, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    /** What the button is told by (data-live-then-param): lower-case letters, digits, "_" and "-". */
    public function name(): string;

    /** Translation key of the text of the button. */
    public function label(): string;

    /** Whether the form shows it: on the add form ($id null), on the edit form, for some records. */
    public function isFor(AsCrud $crud, ?string $id): bool;

    /**
     * Where the save leads.
     *
     * @param string|null $id the record saved (of a new one: if the driver could tell)
     */
    public function afterSave(AsCrud $crud, ?string $id, bool $created): string;
}
