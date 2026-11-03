<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * A RecordGuard or a RecordActionGuard that says why it refused - "A paid invoice is not deleted." - so the button is
 * shown disabled with that as its hint instead of going away, and a page or a live action refused answers with it
 * instead of "Forbidden":
 *
 *     public function refusal(CrudOperation|string $what, RecordView $record): ?CrudFailure
 *     {
 *         return new CrudFailure(409, 'Paid.', translationKey: 'app.invoice.paid');
 *     }
 *
 * Asked only once the guard refused.
 */
interface ExplainsRefusal
{
    /**
     * @param CrudOperation|string $what the operation (RecordGuard) or the name of the action (RecordActionGuard)
     *
     * @return CrudFailure|null why; null = no reason to tell (a 403 then, and the button goes away)
     */
    public function refusal(CrudOperation|string $what, RecordView $record): ?CrudFailure;
}
