<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordView;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Whether a part of a layout wrapped in {@see When} is on a page: by the record ("Refund" only for a paid order), by
 * the one looking (an "Audit" fieldset for an administrator). A service, autoconfigured; When names it by its class.
 */
#[AutoconfigureTag(self::TAG)]
interface LayoutCondition
{
    public const string TAG = 'byfareska_crud.layout_condition';

    /**
     * @param RecordView|null $record the record of the page (every property, when the resource can read one); null on
     *     the add form
     */
    public function shows(AsCrud $crud, ?RecordView $record): bool;
}
