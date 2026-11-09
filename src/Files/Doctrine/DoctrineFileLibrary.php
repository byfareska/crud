<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine;

use Byfareska\Crud\Driver\Doctrine\DoctrineOrder;
use Byfareska\Crud\Driver\Doctrine\LikePattern;
use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\FilePage;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileRecordFactory;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\LibraryCapability;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\UploadProfile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

/**
 * The library of a storage in a table of Doctrine: the bundle's entity (CrudFileRecord, "crud_file") or the
 * application's (byfareska_crud.files.entity), found by the properties of files.entity_fields - the names files were
 * uploaded under are searched, the kinds filtered, the newest first.
 */
final readonly class DoctrineFileLibrary implements FileLibrary
{
    /**
     * @param class-string $class the entity of the files
     * @param string $storage the storage whose files it keeps: one table may keep those of every storage
     */
    public function __construct(
        private ManagerRegistry $doctrine,
        private string $class,
        private string $storage,
        private FileRecordFactory $factory,
        private FileRecordMappers $mappers,
        private RecordFields $fields = new RecordFields(),
    ) {
    }

    public function register(StoredFile $file): StoredFile
    {
        $record = $this->factory->create($this->class, $file);
        $manager = $this->manager();
        $manager->persist($record);
        $manager->flush();

        return $this->mappers->toStoredFile($record) ?? $file;
    }

    public function find(FileReference $reference): ?StoredFile
    {
        $record = $this->record($reference);

        return $record === null ? null : $this->mappers->toStoredFile($record);
    }

    public function browse(FileQuery $query): FilePage
    {
        $builder = $this->ofStorage($query->storage);
        $words = array_filter(
            explode(' ', mb_strtolower(trim($query->search))),
            static fn (string $word): bool => $word !== '',
        );
        foreach (array_values($words) as $i => $word) {
            $builder
                ->andWhere(sprintf(
                    "LOWER(f.%s) LIKE :word%d ESCAPE '%s'",
                    $this->fields->name,
                    $i,
                    LikePattern::ESCAPE,
                ))
                ->setParameter('word' . $i, '%' . LikePattern::escape($word) . '%');
        }
        $this->filterKinds($builder, $query->accept);
        if ($this->fields->created !== null) {
            $builder->orderBy('f.' . $this->fields->created, DoctrineOrder::of(true));
        }
        $builder->setFirstResult($query->offset())->setMaxResults($query->perPage + 1);

        $files = [];
        foreach ($builder->getQuery()->toIterable() as $record) {
            if (is_object($record)) {
                $file = $this->mappers->toStoredFile($record);
                if ($file instanceof StoredFile) {
                    $files[] = $file;
                }
            }
        }

        $more = count($files) > $query->perPage;

        return new FilePage(array_slice($files, 0, $query->perPage), $query->page, null, $more);
    }

    public function remove(FileReference $reference): void
    {
        $record = $this->record($reference);
        if ($record !== null) {
            $manager = $this->manager();
            $manager->remove($record);
            $manager->flush();
        }
    }

    public function capabilities(): array
    {
        return [LibraryCapability::Browse, LibraryCapability::Search, LibraryCapability::Remove];
    }

    /**
     * The entity of a file of this storage; null = none.
     */
    public function record(FileReference $reference): ?object
    {
        if ($reference->storage !== $this->storage) {
            return null;
        }
        $builder = $this->manager()->createQueryBuilder()->select('f')->from($this->class, 'f')->setMaxResults(1);
        if ($this->fields->reference !== null) {
            $builder->where(sprintf('f.%s = :reference', $this->fields->reference))
                ->setParameter('reference', (string) $reference);
        } else {
            $builder->where(sprintf('f.%s = :storage AND f.%s = :path', $this->fields->storage, $this->fields->path))
                ->setParameter('storage', $reference->storage)
                ->setParameter('path', $reference->path);
        }
        $record = $builder->getQuery()->getOneOrNullResult();

        return is_object($record) ? $record : null;
    }

    private function ofStorage(string $storage): QueryBuilder
    {
        $builder = $this->manager()->createQueryBuilder()->select('f')->from($this->class, 'f');
        if ($this->fields->reference !== null) {
            return $builder
                ->where(sprintf("f.%s LIKE :prefix ESCAPE '%s'", $this->fields->reference, LikePattern::ESCAPE))
                ->setParameter('prefix', LikePattern::escape($storage . '://') . '%');
        }

        return $builder->where(sprintf('f.%s = :storage', $this->fields->storage))->setParameter('storage', $storage);
    }

    /**
     * Only the kinds wanted: "image/*" by the beginning of the MIME type, ".pdf" by the end of the path.
     *
     * @param list<string> $accept
     */
    private function filterKinds(QueryBuilder $builder, array $accept): void
    {
        $accept = UploadProfile::expand($accept);
        if ($accept === []) {
            return;
        }
        $path = 'f.' . ($this->fields->reference ?? $this->fields->path);
        $or = $builder->expr()->orX();
        foreach ($accept as $i => $kind) {
            $parameter = 'kind' . $i;
            if (str_starts_with($kind, '.')) {
                $or->add(sprintf("LOWER(%s) LIKE :%s ESCAPE '%s'", $path, $parameter, LikePattern::ESCAPE));
                $builder->setParameter($parameter, '%' . LikePattern::escape($kind));
            } elseif ($this->fields->mimeType !== null && str_ends_with($kind, '/*')) {
                $or->add(sprintf(
                    "f.%s LIKE :%s ESCAPE '%s'",
                    $this->fields->mimeType,
                    $parameter,
                    LikePattern::ESCAPE,
                ));
                $builder->setParameter($parameter, LikePattern::escape(substr($kind, 0, -1)) . '%');
            } elseif ($this->fields->mimeType !== null) {
                $or->add(sprintf('f.%s = :%s', $this->fields->mimeType, $parameter));
                $builder->setParameter($parameter, $kind);
            }
        }
        if ($or->count() > 0) {
            $builder->andWhere($or);
        }
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass($this->class);

        return $manager instanceof EntityManagerInterface
            ? $manager
            : throw new LogicException(sprintf(
                'The entity of the files "%s" is not mapped by Doctrine ORM (byfareska_crud.files.entity).',
                $this->class,
            ));
    }
}
