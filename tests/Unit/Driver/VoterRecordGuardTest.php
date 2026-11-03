<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineManagers;
use Byfareska\Crud\Driver\Doctrine\DoctrineRecordEntities;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\VoterActionGuard;
use Byfareska\Crud\Driver\Doctrine\VoterRecordGuard;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * What the Voters of the application say of an entity is what the panel does with its record.
 */
final class VoterRecordGuardTest extends TestCase
{
    public function testTheVotersAreAskedAboutTheEntity(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($entities)->createSchema($entities->getMetadataFactory()->getAllMetadata());
        $anna = new Person('Anna');
        $adam = new Person('Adam');
        $entities->persist($anna);
        $entities->persist($adam);
        $entities->flush();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entities);
        // A Voter: Anna may be edited and refunded, nobody deleted.
        $actor = new readonly class implements Actor {
            public function id(): string
            {
                return '1';
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return $subject instanceof Person && $subject->name === 'Anna' && $attribute !== 'DELETE';
            }
        };
        $records = new DoctrineRecordEntities(new DoctrineManagers($doctrine));
        $guard = new class ($actor, $records) extends VoterRecordGuard {
            public function resource(): string
            {
                return 'person';
            }
        };
        $actions = new class ($actor, $records) extends VoterActionGuard {
            public function resource(): string
            {
                return 'person';
            }
        };
        $crud = new AsCrud('person', '/people', 'person', source: new DoctrineSource(Person::class));
        $record = static fn (Person $person): RecordView
            => new RecordView($crud, (string) $person->id, [], RecordScreen::List);

        self::assertTrue($guard->allows(CrudOperation::Update, $record($anna)));
        self::assertFalse($guard->allows(CrudOperation::Update, $record($adam)));
        self::assertFalse($guard->allows(CrudOperation::Delete, $record($anna)));
        self::assertTrue($actions->allowsAction('refund', $record($anna)));
        self::assertFalse($actions->allowsAction('refund', $record($adam)));
        self::assertFalse(
            $guard->allows(CrudOperation::Read, new RecordView($crud, '999', [], RecordScreen::List)),
            'No entity: refused.',
        );
    }
}
