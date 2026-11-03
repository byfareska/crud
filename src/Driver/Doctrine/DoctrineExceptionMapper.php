<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Throwable;

/**
 * What an error of the database means to the one saving or deleting a record: a {@see CrudFailure} shown at the form
 * or in a toast, or null for an error of the application (a 500). The bundle's is
 * {@see DefaultDoctrineExceptionMapper}; decorate it to name what a constraint of the application means
 * (`uniq_product_sku` → "this SKU is taken", at the field "sku").
 */
interface DoctrineExceptionMapper
{
    /**
     * @param CrudOperation $operation Create, Update or Delete
     */
    public function map(AsCrud $crud, CrudOperation $operation, Throwable $exception): ?CrudFailure;
}
