<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordView;

/**
 * A LayoutCondition that also knows the page it is asked on and the options of its When - one class for many
 * conditions of one kind, a part only on the details page or only in the form:
 *
 *     new When(StatusIs::class, [new Tab('refund', ...)], options: ['status' => 'paid'])
 *
 *     public function showsOn(AsCrud $crud, ?RecordView $record, LayoutPage $page, array $options): bool
 *     {
 *         return ($record?->item['status'] ?? null) === $options['status'];
 *     }
 *
 * Asked in place of shows(), which it still has (a When without options on any page may ask it).
 */
interface PageLayoutCondition extends LayoutCondition
{
    /**
     * @param LayoutPage $page the forms (Form) or the details page and the preview drawer (Detail)
     * @param array<string, mixed> $options of the When (When::$options)
     */
    public function showsOn(AsCrud $crud, ?RecordView $record, LayoutPage $page, array $options): bool;
}
