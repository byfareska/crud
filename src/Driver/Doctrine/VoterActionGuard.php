<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Resource\RecordActionGuard;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;

/**
 * A RecordActionGuard asking the Voters of the application about the entity of the record, by the name of the action:
 * "refund" of an order is the attribute REFUND of an OrderVoter (attributeOf() to say it otherwise; null = not asked).
 *
 *     final class OrderActions extends VoterActionGuard
 *     {
 *         public function resource(): string { return 'order'; }
 *     }
 */
abstract class VoterActionGuard implements RecordActionGuard
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

    public function allowsAction(string $action, RecordView $record): bool
    {
        $attribute = $this->attributeOf($action);
        if ($attribute === null) {
            return true;
        }
        $entity = $this->entities->of($record);

        return $entity !== null && $this->actor->isGranted($attribute, $entity);
    }

    /** The attribute a Voter is asked for an action: its name in capitals, "-" as "_" ("send-again": SEND_AGAIN). */
    protected function attributeOf(string $action): ?string
    {
        return strtoupper(str_replace('-', '_', $action));
    }
}
