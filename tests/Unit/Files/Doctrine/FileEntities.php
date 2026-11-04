<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * An entity manager of SQLite in memory with the entities of files - the bundle's and the fixture of the
 * application's - and a registry that gives it for each of them.
 */
final class FileEntities
{
    public static function manager(): EntityManagerInterface
    {
        $src = __DIR__ . '/../../../../src/Files/Doctrine';
        $config = ORMSetup::createAttributeMetadataConfig(
            [$src . '/Mapped', $src . '/Record', $src . '/Usage', __DIR__ . '/../../Fixtures/Files'],
            true,
        );
        $config->enableNativeLazyObjects(true);
        $manager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return $manager;
    }

    public static function registry(TestCase $test, EntityManagerInterface $manager): ManagerRegistry
    {
        $registry = (fn (): ManagerRegistry => $this->createStub(ManagerRegistry::class))->call($test);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }
}
