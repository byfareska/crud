<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Throwable;

/**
 * The bundle's {@see DoctrineExceptionMapper}:
 * - a value another record has (a unique key): 409, crud.error.taken;
 * - a record others use (a foreign key) - deleted, or one it names that is gone: 409, crud.error.in_use;
 * - a record changed by somebody else since the form was opened (optimistic locking): 409, crud.error.changed;
 * - a deadlock or a lock that waited too long: 503, crud.error.retry - nothing was kept, the same again may succeed;
 * - a value the database requires left empty, another constraint: 422, crud.error.refused.
 */
final readonly class DefaultDoctrineExceptionMapper implements DoctrineExceptionMapper
{
    public function map(AsCrud $crud, CrudOperation $operation, Throwable $exception): ?CrudFailure
    {
        if ($exception instanceof CrudFailure) {
            return $exception;
        }
        [$status, $key] = match (true) {
            $exception instanceof UniqueConstraintViolationException => [409, 'crud.error.taken'],
            $exception instanceof ForeignKeyConstraintViolationException => [409, 'crud.error.in_use'],
            $exception instanceof OptimisticLockException => [409, 'crud.error.changed'],
            $exception instanceof RetryableException => [503, 'crud.error.retry'],
            $exception instanceof ConstraintViolationException => [422, 'crud.error.refused'],
            default => [null, null],
        };

        return $status === null ? null : new CrudFailure($status, $exception->getMessage(), null, $exception, $key);
    }
}
