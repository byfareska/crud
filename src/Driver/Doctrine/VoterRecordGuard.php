<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;

/**
 * A RecordGuard asking the Voters of the application about the entity of the record - an OrderVoter of "EDIT" and
 * "DELETE" on an Order - so what Symfony Security says of an entity is what the panel does with it:
 *
 *     final class OrderGuard extends VoterRecordGuard
 *     {
 *         public function resource(): string { return 'order'; }
 *     }
 *
 * The attribute of an operation is attributeOf(): VIEW, EDIT and DELETE by default (override it for others; null =
 * not asked). The entity is read by its id once a request (DoctrineRecordEntities); a record of no entity is
 * refused.
 */
abstract class VoterRecordGuard implements RecordGuard
{
    public function __construct(
        private readonly Actor $actor,
        private readonly DoctrineRecordEntities $entities,
    ) {
    }

    public function fields(): array
    {
        return [];
    }

    public function allows(CrudOperation $operation, RecordView $record): bool
    {
        $attribute = $this->attributeOf($operation);
        if ($attribute === null) {
            return true;
        }
        $entity = $this->entities->of($record);

        return $entity !== null && $this->actor->isGranted($attribute, $entity);
    }

    /** The attribute a Voter is asked for an operation; null = the operation is not asked of the Voters. */
    protected function attributeOf(CrudOperation $operation): ?string
    {
        return match ($operation) {
            CrudOperation::Read => 'VIEW',
            CrudOperation::Update => 'EDIT',
            CrudOperation::Delete => 'DELETE',
            default => null,
        };
    }
}
