<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Dto\DtoSource;
use Byfareska\Crud\Resource\CrudDefinitionExtension;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Resource\OperationRoles;
use Byfareska\Crud\Routing\CrudRoutes;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Who may do an operation of a resource: AsCrud::$roles, and what an extension refuses for a request.
 */
final class OperationRolesTest extends TestCase
{
    public function testTheRolesAreByOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('roles are by operation');

        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, roles: ['remove' => 'ROLE_ADMIN']);
    }

    public function testARoleIsAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (a role that is no name is the point)
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, roles: ['delete' => true]);
    }

    public function testWhatIsRefusedTheResourceCannotDoInTheRequest(): void
    {
        $crud = $this->thing();
        $refused = $crud->refusing(CrudOperation::Delete, CrudOperation::Create);

        self::assertFalse($refused->can(CrudOperation::Delete));
        self::assertTrue($refused->offers(CrudOperation::Delete));
        self::assertTrue($refused->isRefused(CrudOperation::Create));
        self::assertTrue($refused->can(CrudOperation::Read));
        self::assertFalse($refused->inPalette(CrudOperation::Create));
        self::assertTrue($crud->can(CrudOperation::Delete), 'The declaration is what it was.');
        // Kept by with(), and refusing it again changes nothing.
        self::assertTrue($refused->with(icon: 'star')->isRefused(CrudOperation::Delete));
        self::assertSame($refused, $refused->refusing(CrudOperation::Delete));
        self::assertTrue($crud->isSameResourceAs($refused));
        self::assertFalse($refused->isSameResourceAs($crud), 'What is refused is not given back.');
    }

    public function testWithoutTheListTheRecordsAreNotFoundInThePalette(): void
    {
        $crud = $this->thing()->refusing(CrudOperation::List);

        self::assertFalse($crud->inPalette(CrudOperation::List));
        self::assertFalse($crud->inPalette(CrudOperation::Read));
        self::assertTrue($crud->can(CrudOperation::Read));
    }

    public function testTheRolesAreAskedAboutTheResource(): void
    {
        $actor = $this->actor(['ROLE_MANAGER']);
        $crud = $this->thing();

        $extended = new OperationRoles($actor)->extend($crud);

        self::assertSame([CrudOperation::Delete], $extended->refused);
        self::assertSame([['ROLE_MANAGER', $crud], ['ROLE_ADMIN', $crud]], $actor->asked);
        self::assertSame($crud, new OperationRoles($this->actor(['ROLE_MANAGER', 'ROLE_ADMIN']))->extend($crud));
    }

    public function testTheRegistryRefusesForTheRequestAndRoutesStay(): void
    {
        $registry = new CrudRegistry(
            providers: [$this->provider($this->thing())],
            extensions: [new OperationRoles($this->actor([]))],
        );

        self::assertFalse($registry->get('thing')->can(CrudOperation::Create));
        self::assertFalse($registry->get('thing')->can(CrudOperation::Delete));
        self::assertTrue($registry->get('thing')->can(CrudOperation::Update));
        // The page to add one is there: it answers 403 to those it is not for.
        self::assertNotNull(new CrudRoutes($registry)->of('panel')->get('crud_thing_new'));
    }

    public function testAnExtensionDoesNotGiveBackWhatIsRefused(): void
    {
        $givesBack = new readonly class implements CrudDefinitionExtension {
            public function resource(): string
            {
                return 'thing';
            }

            public function extend(AsCrud $crud): AsCrud
            {
                return new AsCrud(...[
                    'name' => $crud->name,
                    'path' => $crud->path,
                    'icon' => $crud->icon,
                    'source' => $crud->source,
                    'roles' => $crud->roles,
                ]);
            }
        };
        $registry = new CrudRegistry(
            providers: [$this->provider($this->thing())],
            extensions: [new OperationRoles($this->actor([])), $givesBack],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not given back');

        $registry->get('thing');
    }

    private function thing(): AsCrud
    {
        return new AsCrud(
            'thing',
            '/things',
            'box',
            source: new DtoSource(
                ListThingsDto::class,
                ShowThingDto::class,
                ListThingsDto::class,
                ListThingsDto::class,
                ShowThingDto::class,
            ),
            roles: ['create' => 'ROLE_MANAGER', 'delete' => 'ROLE_ADMIN'],
        );
    }

    private function provider(AsCrud $crud): CrudResourceProvider
    {
        return new readonly class ($crud) implements CrudResourceProvider {
            public function __construct(private AsCrud $crud)
            {
            }

            public function resources(): iterable
            {
                yield $this->crud;
            }
        };
    }

    /**
     * @param list<string> $roles
     */
    private function actor(array $roles): RecordingActor
    {
        return new RecordingActor($roles);
    }
}
