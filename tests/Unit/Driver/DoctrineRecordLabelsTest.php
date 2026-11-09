<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Autocomplete\RelationChoiceLabel;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineRecordLabels;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Byfareska\Crud\Value\RecordRef;
use Byfareska\Crud\Value\TypedValue;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * What names an entity shown as a relation: a label of the application, else (no title, no __toString()) its id.
 */
final class DoctrineRecordLabelsTest extends TestCase
{
    private ManagerRegistry $doctrine;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entities);
        $this->doctrine = $doctrine;
    }

    public function testAnEntityWithoutTitleOrTextIsNamedByItsId(): void
    {
        $order = new Order();
        $order->id = 12;

        self::assertSame('12', $this->labels()->of($order));
    }

    public function testALabelOfTheApplicationComesFirst(): void
    {
        $order = new Order();
        $order->id = 12;
        $labels = $this->labels([
            new readonly class implements RelationChoiceLabel {
                public function supports(object $entity): bool
                {
                    return $entity instanceof Order;
                }

                public function label(object $entity): string
                {
                    return $entity instanceof Order ? 'Zamówienie ' . $entity->id : '';
                }
            },
        ]);

        self::assertSame('Zamówienie 12', $labels->of($order));
    }

    public function testARelationIsItsIdInJsonAndItsNameAsText(): void
    {
        $order = new Order();
        $order->id = 12;
        $normalizer = new DoctrineEntityNormalizer($this->doctrine, labels: $this->labels());

        $value = $normalizer->normalize($order, $this->values());

        self::assertEquals(new TypedValue('relation', new RecordRef('12', '12')), $value);
    }

    /**
     * @param list<RelationChoiceLabel> $labels
     */
    private function labels(array $labels = []): DoctrineRecordLabels
    {
        return new DoctrineRecordLabels(
            $this->doctrine,
            new CrudRegistry(providers: []),
            PropertyAccess::createPropertyAccessor(),
            $this->values(),
            $labels,
        );
    }

    private function values(): CrudValues
    {
        return new CrudValues([new DoctrineEntityNormalizer($this->doctrine), new PlainValueNormalizer()]);
    }
}
