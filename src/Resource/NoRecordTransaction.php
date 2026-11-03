<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Closure;

/**
 * No transaction: what is done stays done - the {@see RecordTransaction} of an application without Doctrine ORM.
 */
final readonly class NoRecordTransaction implements RecordTransaction
{
    public function run(AsCrud $crud, Closure $work): mixed
    {
        return $work();
    }
}
