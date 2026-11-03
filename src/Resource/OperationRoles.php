<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Security\Actor;

/**
 * Takes away for the request the operations of AsCrud::$roles the one asking has not - so can() says no to them
 * wherever it is asked. The role is asked with the resource as the subject: a voter of the application may decide.
 */
final readonly class OperationRoles implements CrudDefinitionExtension
{
    public function __construct(
        private Actor $actor,
    ) {
    }

    public function resource(): string
    {
        return self::EVERY;
    }

    public function extend(AsCrud $crud): AsCrud
    {
        $refused = [];
        foreach (CrudOperation::cases() as $operation) {
            $role = $crud->roleOf($operation);
            if ($role !== null && $crud->offers($operation) && !$this->actor->isGranted($role, $crud)) {
                $refused[] = $operation;
            }
        }

        return $crud->refusing(...$refused);
    }
}
