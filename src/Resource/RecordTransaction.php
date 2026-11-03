<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Closure;

/**
 * What a save and a delete of a record are done in, all or nothing: for a save RecordSaving, the driver, the sections
 * of the form and RecordSaved; for a delete RecordDeleting, the driver and RecordDeleted. What fails in any of them - a
 * section refused, a listener of RecordSaved writing an audit log - undoes the rest.
 *
 * With Doctrine ORM the bundle's is {@see \Byfareska\Crud\Driver\Doctrine\DoctrineRecordTransaction}: a transaction of
 * the connection of the resource's entity manager (of the default one for a resource of another driver); without it
 * {@see NoRecordTransaction}. Alias this interface for another store. A resource says it of itself by its option
 * "transaction" (AsCrud::$options): false - none (a use case writing through a message bus, another database), or the
 * name of the entity manager whose connection it is (a resource of DTOs writing to another database).
 *
 * What must happen only once it is kept (an e-mail, a message to another system) does not belong in RecordSaved
 * itself: send a message through a transport that is written in the same transaction (Messenger's doctrine transport),
 * or do it on kernel.terminate.
 */
interface RecordTransaction
{
    /** The option of AsCrud saying which transaction a resource's records are saved in - see above. */
    public const string OPTION = 'transaction';

    /**
     * Runs $work in a transaction: committed when it returns, rolled back when it throws (and the exception goes on).
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function run(AsCrud $crud, Closure $work): mixed;
}
