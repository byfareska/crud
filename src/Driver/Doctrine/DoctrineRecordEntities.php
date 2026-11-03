<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Resource\RecordView;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The entity of a record of a resource of entities, by its id - for what goes by the entity rather than by the
 * values of the record: a Voter of the application (VoterRecordGuard). Read once a request for each record.
 */
final class DoctrineRecordEntities implements ResetInterface
{
    /** @var array<string, object|null> */
    private array $read = [];

    public function __construct(
        private readonly DoctrineManagers $managers,
        private readonly DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
    ) {
    }

    /** The entity; null for a record of another driver, or one not there. */
    public function of(RecordView $record): ?object
    {
        $source = $record->crud->source;
        if (!$source instanceof DoctrineSource) {
            return null;
        }
        $key = $source->entity . '#' . $record->id;
        if (array_key_exists($key, $this->read)) {
            return $this->read[$key];
        }
        $manager = $this->managers->of($record->crud);
        $criteria = $this->ids->decode($manager->getClassMetadata($source->entity), $record->id);

        return $this->read[$key] = $criteria === null ? null : $manager->find($source->entity, $criteria);
    }

    public function reset(): void
    {
        $this->read = [];
    }
}
