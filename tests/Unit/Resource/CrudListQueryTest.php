<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDriver;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudListQuery;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\DefaultIdMapper;
use Byfareska\Crud\Resource\DefaultPageSizes;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordRows;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\UseCase\ListPage;
use Byfareska\Crud\UseCase\Page;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

/**
 * Every match of a bulk action is what the list shows: the records its guards let be read.
 */
final class CrudListQueryTest extends TestCase
{
    public function testEveryMatchIsWhatItsGuardsLetBeRead(): void
    {
        $crud = new AsCrud('note', '/notes', 'sticky', source: new DoctrineSource(stdClass::class));
        $secret = new readonly class implements RecordGuard {
            public function resource(): string
            {
                return 'note';
            }

            public function fields(): array
            {
                return ['secret'];
            }

            public function allows(CrudOperation $operation, RecordView $record): bool
            {
                return $operation !== CrudOperation::Read || ($record->item['secret'] ?? false) !== true;
            }
        };
        $notes = [];
        foreach (range(1, 7) as $id) {
            $notes[] = ['id' => $id, 'secret' => $id % 3 === 0];
        }

        $guarded = $this->query($notes, [$secret]);
        self::assertSame(['1', '2', '4', '5', '7'], iterator_to_array($guarded->readableIds($crud), false));
        self::assertFalse($guarded->streams($crud), 'Read page by page: gathered before anything is done.');

        $open = $this->query($notes);
        self::assertSame(['1', '2', '3', '4', '5', '6', '7'], iterator_to_array($open->readableIds($crud), false));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param list<RecordGuard> $guards
     */
    private function query(array $records, array $guards = []): CrudListQuery
    {
        $driver = new readonly class ($records) implements CrudDriver {
            /** @param list<array<string, mixed>> $records */
            public function __construct(private array $records)
            {
            }

            public function name(): string
            {
                return 'doctrine';
            }

            public function listForm(AsCrud $crud): FormInterface
            {
                return Forms::createFormFactory()
                    ->createNamedBuilder('filters', FormType::class)
                    ->add('page', IntegerType::class)
                    ->add('perPage', IntegerType::class)
                    ->getForm();
            }

            public function list(AsCrud $crud, FormInterface $form): Page
            {
                $page = $form->get('page')->getData();
                $perPage = $form->get('perPage')->getData();
                assert(is_int($page) && is_int($perPage));

                return new ListPage(
                    array_slice($this->records, ($page - 1) * $perPage, $perPage),
                    count($this->records),
                    $page,
                    $perPage,
                );
            }

            public function read(AsCrud $crud, string $id): array
            {
                return [];
            }

            public function delete(AsCrud $crud, string $id): void
            {
            }

            public function form(AsCrud $crud, ?string $id, array $with = []): FormBuilderInterface
            {
                return Forms::createFormFactory()->createBuilder();
            }

            public function save(AsCrud $crud, FormInterface $form, ?string $id): ?string
            {
                return null;
            }
        };
        $actor = self::createStub(Actor::class);
        $recordGuards = new RecordGuards($guards);

        return new CrudListQuery(
            new CrudDrivers([$driver]),
            self::createStub(CrudAccess::class),
            // Pages of two: the guards go through every one of them.
            new DefaultPageSizes([2]),
            new RecordActions(new CrudRegistry(), $actor, new ServiceLocator([]), $recordGuards),
            $recordGuards,
            new RecordRows(),
            new DefaultIdMapper(),
        );
    }
}
