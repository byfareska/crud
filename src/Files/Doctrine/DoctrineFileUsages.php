<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine;

use Byfareska\Crud\Files\Doctrine\Usage\CrudFileUsage;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsage;
use Byfareska\Crud\Files\FileUsages;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

/**
 * The index of where files are used in the table "crud_file_usage" (byfareska_crud.files.usages: doctrine, the default
 * with Doctrine ORM). Written in the transaction of the save of the record, when the record is in the same database.
 */
final readonly class DoctrineFileUsages implements FileUsages
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    public function tracks(): bool
    {
        return true;
    }

    public function replace(string $resource, string $id, string $field, array $references): void
    {
        $manager = $this->manager();
        $manager->createQueryBuilder()
            ->delete(CrudFileUsage::class, 'u')
            ->where('u.resource = :resource AND u.recordId = :id AND u.field = :field')
            ->setParameter('resource', $resource)
            ->setParameter('id', $id)
            ->setParameter('field', $field)
            ->getQuery()
            ->execute();
        $kept = [];
        foreach ($references as $reference) {
            $text = (string) $reference;
            if (!isset($kept[$text]) && !$reference->isExternal()) {
                $kept[$text] = true;
                $manager->persist(new CrudFileUsage($text, $resource, $id, $field));
            }
        }
        $manager->flush();
    }

    public function forget(string $resource, string $id): void
    {
        $this->manager()->createQueryBuilder()
            ->delete(CrudFileUsage::class, 'u')
            ->where('u.resource = :resource AND u.recordId = :id')
            ->setParameter('resource', $resource)
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }

    public function of(FileReference $reference): array
    {
        $usages = [];
        $found = $this->manager()->getRepository(CrudFileUsage::class)->findBy(
            ['reference' => (string) $reference],
            ['resource' => 'ASC', 'recordId' => 'ASC', 'field' => 'ASC'],
        );
        foreach ($found as $usage) {
            $usages[] = new FileUsage($usage->getResource(), $usage->getRecordId(), $usage->getField());
        }

        return $usages;
    }

    public function used(array $references): array
    {
        if ($references === []) {
            return [];
        }
        $rows = $this->manager()->createQueryBuilder()
            ->select('DISTINCT u.reference')
            ->from(CrudFileUsage::class, 'u')
            ->where('u.reference IN (:references)')
            ->setParameter('references', array_map(strval(...), $references))
            ->getQuery()
            ->getSingleColumnResult();
        $used = array_flip(array_filter($rows, is_string(...)));

        return array_values(array_filter(
            $references,
            static fn (FileReference $reference): bool => isset($used[(string) $reference]),
        ));
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass(CrudFileUsage::class);

        return $manager instanceof EntityManagerInterface
            ? $manager
            : throw new LogicException('The index of files (crud_file_usage) is not mapped by Doctrine ORM.');
    }
}
