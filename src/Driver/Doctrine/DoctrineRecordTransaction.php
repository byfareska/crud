<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Doctrine\DBAL\Connection;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordTransaction;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Throwable;

/**
 * A transaction of the connection of the entity manager of the resource ({@see DoctrineSource::$manager}, else the
 * manager of its entity); for a resource of another driver the default manager's, which its use cases most likely
 * write through. A transaction of the connection, not EntityManager::wrapInTransaction(): that one closes the manager
 * when something fails, and the form shown again with its error still needs it.
 */
final readonly class DoctrineRecordTransaction implements RecordTransaction
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    public function run(AsCrud $crud, Closure $work): mixed
    {
        $connection = $this->manager($crud)?->getConnection();
        if (!$connection instanceof Connection) {
            return $work();
        }

        $connection->beginTransaction();
        try {
            $result = $work();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            // A flush that failed may have rolled its own part back already.
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private function manager(AsCrud $crud): ?EntityManagerInterface
    {
        // What the resource says of itself: no transaction, or the one of another entity manager.
        $option = $crud->option(RecordTransaction::OPTION);
        if ($option === false) {
            return null;
        }
        $source = $crud->source;
        $manager = match (true) {
            is_string($option) => $this->doctrine->getManager($option),
            !$source instanceof DoctrineSource => $this->doctrine->getManager(),
            $source->manager !== null => $this->doctrine->getManager($source->manager),
            default => $this->doctrine->getManagerForClass($source->entity),
        };

        return $manager instanceof EntityManagerInterface ? $manager : null;
    }
}
