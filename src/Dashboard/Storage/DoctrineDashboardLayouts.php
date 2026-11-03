<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard\Storage;

use Byfareska\Crud\Dashboard\DashboardLayouts;
use Byfareska\Crud\Dashboard\WidgetPlacement;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

/**
 * The layouts in a table of the database (DashboardLayoutRecord). The one for everybody (owner null) is the row of the
 * owner "*": a unique key of (owner, board) does not keep a second NULL out on PostgreSQL or MySQL, so two shares at
 * once would leave two. A row of an owner NULL, as an older version wrote it, is still read, and goes when the layout
 * for everybody is stored again.
 *
 * Read and written by the connection of the entity manager its mapping is in, row by row (DBAL): storing a layout
 * flushes nothing else the application has pending in its entity manager, and what was stored is what is read next,
 * in the same request too.
 */
final readonly class DoctrineDashboardLayouts implements DashboardLayouts
{
    /** The owner of the layout for everybody, in the table. */
    public const string EVERYBODY = '*';

    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    public function find(?string $owner, string $board): ?array
    {
        $placements = $this->placementsOf($owner ?? self::EVERYBODY, $board)
            ?? ($owner === null ? $this->placementsOf(null, $board) : null);

        return $placements === null ? null : WidgetPlacement::listFromArray($placements);
    }

    public function save(?string $owner, string $board, array $placements): void
    {
        $data = array_map(static fn (WidgetPlacement $placement): array => $placement->toArray(), $placements);
        [$connection, $table, $columns] = $this->table();
        $values = [$columns['placements'] => $data, $columns['updatedAt'] => new DateTimeImmutable()];
        $types = [$columns['placements'] => Types::JSON, $columns['updatedAt'] => Types::DATETIME_IMMUTABLE];
        $key = [$columns['owner'] => $owner ?? self::EVERYBODY, $columns['board'] => $board];

        $store = function (Connection $connection) use ($owner, $board, $table, $columns, $values, $types, $key): void {
            if ($this->placementsOf($key[$columns['owner']], $board) === null) {
                try {
                    $connection->insert($table, $key + $values, $types);
                } catch (UniqueConstraintViolationException) {
                    // Stored at the same moment by another request: this one comes after it.
                    $connection->update($table, $values, $key, $types);
                }
            } else {
                $connection->update($table, $values, $key, $types);
            }
            if ($owner === null) {
                $this->forgetLegacy($connection, $table, $columns, $board);
            }
        };
        $connection->transactional($store);
    }

    public function delete(?string $owner, string $board): void
    {
        [$connection, $table, $columns] = $this->table();
        $connection->delete($table, [$columns['owner'] => $owner ?? self::EVERYBODY, $columns['board'] => $board]);
        if ($owner === null) {
            $this->forgetLegacy($connection, $table, $columns, $board);
        }
    }

    /**
     * The placements of a row, as stored (WidgetPlacement::listFromArray() leaves out what is none); null = no row.
     * An owner null is the row an older version wrote.
     *
     * @return array<array-key, mixed>|null
     */
    private function placementsOf(?string $owner, string $board): ?array
    {
        [$connection, $table, $columns] = $this->table();
        $builder = $connection->createQueryBuilder()
            ->select($columns['placements'])
            ->from($table)
            ->where($columns['board'] . ' = :board')
            ->setParameter('board', $board)
            ->setMaxResults(1);
        if ($owner === null) {
            $builder->andWhere($columns['owner'] . ' IS NULL');
        } else {
            $builder->andWhere($columns['owner'] . ' = :owner')->setParameter('owner', $owner);
        }
        $value = $builder->executeQuery()->fetchOne();
        if ($value === false) {
            return null;
        }

        $placements = Type::getType(Types::JSON)->convertToPHPValue($value, $connection->getDatabasePlatform());

        return is_array($placements) ? $placements : [];
    }

    /**
     * @param array<string, string> $columns
     */
    private function forgetLegacy(Connection $connection, string $table, array $columns, string $board): void
    {
        $connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s IS NULL AND %s = ?', $table, $columns['owner'], $columns['board']),
            [$board],
        );
    }

    /**
     * The connection, the table and its columns by field, as the mapping of DashboardLayoutRecord says.
     *
     * @return array{Connection, string, array<string, string>}
     */
    private function table(): array
    {
        $manager = $this->doctrine->getManagerForClass(DashboardLayoutRecord::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf(
                'No entity manager maps %s: the "doctrine" dashboard storage needs the mapping the bundle adds.',
                DashboardLayoutRecord::class,
            ));
        }
        $metadata = $manager->getClassMetadata(DashboardLayoutRecord::class);
        $platform = $manager->getConnection()->getDatabasePlatform();
        $strategy = $manager->getConfiguration()->getQuoteStrategy();
        $columns = [];
        foreach (['owner', 'board', 'placements', 'updatedAt'] as $field) {
            $columns[$field] = $strategy->getColumnName($field, $metadata, $platform);
        }

        return [$manager->getConnection(), $strategy->getTableName($metadata, $platform), $columns];
    }
}
