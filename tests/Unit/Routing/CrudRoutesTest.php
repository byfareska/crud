<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Controller\CrudPageController;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Routing\CrudRoutes;
use Byfareska\Crud\Tests\Unit\Fixtures\AuditPage;
use Byfareska\Crud\Tests\Unit\Fixtures\Gadget;
use Byfareska\Crud\Tests\Unit\Fixtures\OtherHistoryPage;
use Byfareska\Crud\Tests\Unit\Fixtures\NumberedThingCrud;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCrud;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingHistoryPage;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingReportPage;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Routing\Route;

final class CrudRoutesTest extends TestCase
{
    public function testEveryPageHasAnActionOfItsOwnAndNoTemplateInTheRoute(): void
    {
        $routes = new CrudRoutes(new CrudRegistry([ThingCrud::class]))->of('panel');

        self::assertSame(
            CrudPageController::class . '::list',
            $routes->get('crud_thing_list')?->getDefault('_controller'),
        );
        self::assertSame(
            CrudPageController::class . '::show',
            $routes->get('crud_thing_show')?->getDefault('_controller'),
        );
        foreach ($routes as $route) {
            self::assertFalse($route->hasDefault('view'));
        }
    }

    public function testTheWordsOfTheAddressesAndWhatAnIdIsMadeOf(): void
    {
        $routes = new CrudRoutes(
            new CrudRegistry([NumberedThingCrud::class]),
            ['new' => 'nowy', 'edit' => 'edycja', 'page' => 'strona'],
        )->of('panel');

        [$new, $edit, $list, $show] = array_map(
            static fn (string $page): Route => $routes->get('crud_numbered_' . $page) ?? self::fail('No ' . $page),
            ['new', 'edit', 'list', 'show'],
        );
        self::assertSame('/numbered/nowy', $new->getPath());
        self::assertSame('/numbered/{id}/edycja', $edit->getPath());
        self::assertSame('strona', $list->getDefault('_pages'));
        self::assertSame('\d+', $show->getRequirement('id'));
        self::assertSame('\d+', $edit->getRequirement('id'));
    }

    public function testThePagesOfTheRecords(): void
    {
        $registry = new CrudRegistry(
            [Gadget::class, ThingCrud::class],
            pages: [AuditPage::class, ThingHistoryPage::class],
        );
        $routes = new CrudRoutes($registry)->of('panel');

        $history = $routes->get('crud_thing_history');
        self::assertNotNull($history);
        self::assertSame('/things/{id}/history', $history->getPath());
        self::assertSame(CrudPageController::class . '::page', $history->getDefault('_controller'));
        self::assertSame(['thing', 'history'], [$history->getDefault('crud'), $history->getDefault('name')]);
        // A page of every resource, for every resource of the scope - and only of the scope.
        self::assertSame('/things/{id}/audit', $routes->get('crud_thing_audit')?->getPath());
        self::assertSame('/gadgets/{id}/audit', $routes->get('crud_gadget_audit')?->getPath());
        self::assertNull($routes->get('crud_gizmo_audit'));
        self::assertNotNull(new CrudRoutes($registry)->of('admin')->get('crud_gizmo_audit'));
    }

    public function testAPageNamedLikeAnAddressOfTheBundlesIsAMistake(): void
    {
        // The history of a record at /things/{id}/history: the edit form when "edit" is called "history".
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('byfareska_crud.paths.edit');

        new CrudRoutes(
            new CrudRegistry([ThingCrud::class], pages: [ThingHistoryPage::class]),
            ['edit' => 'history'],
        )->of('panel');
    }

    public function testACollectionPageNamedLikeTheListIsAMistake(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('byfareska_crud.paths.page');

        new CrudRoutes(
            new CrudRegistry([ThingCrud::class], pages: [ThingReportPage::class]),
            ['page' => 'report'],
        )->of('panel');
    }

    public function testThePanelsWordsAndWhatTheParametersOfThePathAreMadeOf(): void
    {
        $products = new AsCrud(
            'category_product',
            '/categories/{category}/products',
            'box',
            source: new DoctrineSource(stdClass::class),
            scope: 'pl',
            requirements: ['category' => '\d+'],
        );
        $registry = new CrudRegistry(providers: [new readonly class ($products) implements CrudResourceProvider {
            public function __construct(private AsCrud $crud)
            {
            }

            public function resources(): iterable
            {
                yield $this->crud;
            }
        }]);

        $polish = ['pl' => ['paths' => ['new' => 'nowy', 'edit' => 'edycja']]];
        $routes = new CrudRoutes($registry, scopes: $polish)->of('pl');
        $path = static fn (string $page): ?string => $routes->get('crud_category_product_' . $page)?->getPath();

        self::assertSame('/categories/{category}/products/nowy', $path('new'));
        self::assertSame('/categories/{category}/products/{id}/edycja', $path('edit'));
        $list = $routes->get('crud_category_product_list');
        self::assertSame('page', $list?->getDefault('_pages'), 'Not named: of "paths".');
        self::assertSame('\d+', $routes->get('crud_category_product_show')?->getRequirement('category'));
    }

    public function testTwoPagesOfOneNameAreAMistake(): void
    {
        $this->expectException(LogicException::class);

        new CrudRoutes(new CrudRegistry(
            [ThingCrud::class],
            pages: [ThingHistoryPage::class, OtherHistoryPage::class],
        ))->of('panel');
    }
}
