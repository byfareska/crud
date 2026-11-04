<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Attribute;

use Byfareska\Crud\Action\BulkDeleteAction;
use Byfareska\Crud\Action\RouteAction;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\FieldSort;
use Byfareska\Crud\Driver\Dto\DtoSource;
use Byfareska\Crud\Resource\AfterSave;
use Byfareska\Crud\Resource\Column;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Tests\Unit\Fixtures\Gadget;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ReadThingDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use Byfareska\Crud\Value\ComponentFormat;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Yaml\Yaml;

final class AsCrudTest extends TestCase
{
    public function testTheDtoShorthandIsADtoSource(): void
    {
        $crud = new AsCrud(
            'thing',
            '/things',
            'box',
            list: ListThingsDto::class,
            read: ShowThingDto::class,
            search: 'query',
        );

        self::assertInstanceOf(DtoSource::class, $crud->source);
        self::assertSame('dto', $crud->source->driver());
        self::assertTrue($crud->can(CrudOperation::List));
        self::assertTrue($crud->can(CrudOperation::Read));
        self::assertFalse($crud->can(CrudOperation::Create));
        self::assertSame('query', $crud->search);
        self::assertTrue($crud->inPalette(CrudOperation::Read));
        self::assertFalse($crud->inPalette(CrudOperation::Create));
    }

    public function testWhereSaveLeadsIsTheConfigsUnlessTheResourceSays(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);
        self::assertNull($crud->save);
        self::assertNull($crud->afterSave);

        $crud = new AsCrud(
            'thing',
            '/things',
            'box',
            list: ListThingsDto::class,
            save: AfterSave::Show,
            afterSave: [AfterSave::Return],
        );
        self::assertSame(AfterSave::Show, $crud->save);
        self::assertSame([AfterSave::Return], $crud->afterSave);
        self::assertSame('send-now', $crud->with(save: 'send-now')->save);
    }

    public function testSaveNamesACaseOrAButton(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, save: 'Send Now');
    }

    public function testTheButtonsBesidesSaveAreCases(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, afterSave: ['return']);
    }

    public function testASourceAndDtosNextToItAreAMistake(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', '/things', 'box', source: new DtoSource(ListThingsDto::class), read: ShowThingDto::class);
    }

    public function testAResourceNeedsAList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', '/things', 'box', read: ShowThingDto::class);
    }

    public function testAnOwnResourceHasNoListCreateNorDelete(): void
    {
        $crud = new AsCrud(
            'me',
            '/me',
            'person',
            source: new DoctrineSource(stdClass::class, operations: [CrudOperation::Read, CrudOperation::Update]),
            own: true,
        );

        self::assertFalse($crud->can(CrudOperation::List));
        self::assertTrue($crud->can(CrudOperation::Update));
        self::assertSame('crud_me_show', $crud->ownPage());
        self::assertSame([], $crud->recordParameters('7'));
    }

    public function testTheDoctrineSourceSearchesByItsQueryFilter(): void
    {
        $crud = new AsCrud(
            'product',
            '/products',
            'box',
            source: new DoctrineSource(stdClass::class, search: ['name']),
            columns: ['name', 'price' => 'money'],
        );

        self::assertSame(DoctrineSource::SEARCH_FILTER, $crud->search);
        self::assertSame('crud.product.field.price.name', $crud->columns[1]->label);
        self::assertSame('money', $crud->columns[1]->type);
        self::assertSame('Kubek', $crud->titleOf(['name' => ' Kubek ', 'price' => 10]));
    }

    public function testDoctrineSourceKnowsOnlyTheOperations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (the wrong operation is the point)
        new DoctrineSource(stdClass::class, operations: [CrudOperation::List, 'archive']);
    }

    public function testDoctrineFilterTypes(): void
    {
        $source = new DoctrineSource(stdClass::class, filters: ['status', 'category' => 'App\\CategoryType']);

        self::assertSame(['status' => null, 'category' => 'App\\CategoryType'], $source->filterTypes());
        self::assertNull($source->searchField());
    }

    public function testDoctrineSorts(): void
    {
        $orders = new FieldSort('orders.total');
        $source = new DoctrineSource(
            stdClass::class,
            sortable: ['name', 'category' => 'category.name', 'orders' => $orders],
        );

        self::assertSame(['name' => 'name', 'category' => 'category.name', 'orders' => $orders], $source->sorts());
    }

    public function testADoctrineSortIsNamedByItsColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // The sort without a name is the point.
        new DoctrineSource(stdClass::class, sortable: [new FieldSort('name')]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function wrongNamesAndPaths(): iterable
    {
        yield 'upper-case name' => ['Thing', '/things'];
        yield 'name with a dash' => ['my-thing', '/things'];
        yield 'path without a slash' => ['thing', 'things'];
        // A parameter of the path would let the address choose what the route gives the page.
        yield 'path with {id}' => ['thing', '/things/{id}'];
        yield 'path with {crud}' => ['thing', '/{crud}/things'];
        yield 'path with {_scope}' => ['thing', '/{_scope}'];
    }

    #[DataProvider('wrongNamesAndPaths')]
    public function testTheNameAndThePathAreChecked(string $name, string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud($name, $path, 'box', list: ListThingsDto::class);
    }

    public function testANameOfTheBundlesTextsIsNoName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('crud.action.*');

        new AsCrud('action', '/actions', 'box', list: ListThingsDto::class);
    }

    public function testTheReservedNamesAreThoseOfTheBundlesTexts(): void
    {
        $texts = Yaml::parseFile(dirname(__DIR__, 3) . '/translations/messages+intl-icu.en.yaml');
        self::assertIsArray($texts);
        $crud = $texts['crud'] ?? [];
        self::assertIsArray($crud);
        $names = array_keys($crud);
        sort($names);

        self::assertSame($names, AsCrud::RESERVED_NAMES);
    }

    public function testANameWithAnUnderscoreIsFine(): void
    {
        self::assertSame(
            'crud_user_group_list',
            new AsCrud('user_group', '/user-groups', 'people', list: ListThingsDto::class)->route('list'),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function wrongPageNames(): iterable
    {
        yield 'a page of the bundle' => ['edit'];
        yield 'the list' => ['list'];
        yield 'an underscore' => ['change_log'];
        yield 'upper-case' => ['History'];
        yield 'a slash' => ['a/b'];
    }

    #[DataProvider('wrongPageNames')]
    public function testTheNameOfAPageIsChecked(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrudPage('thing', $name, 'Thing:Page');
    }

    public function testAnActionIsARecordAction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (the wrong action is the point)
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, actions: [new stdClass()]);
    }

    public function testTwoActionsOfAResourceDoNotShareAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, actions: [
            new RouteAction('login', 'app_login', 'key'),
            new RouteAction('login', 'app_login_as', 'person'),
        ]);
    }

    public function testAColumnTakesATypeOrAFormat(): void
    {
        $format = new ComponentFormat('App:StockLevel', ['low' => 5]);
        $crud = new AsCrud(
            'thing',
            '/things',
            'box',
            list: ListThingsDto::class,
            columns: ['name', 'email' => 'email', 'stock' => $format],
        );

        [$name, $email, $stock] = $crud->columns;
        self::assertSame([null, null], [$name->type, $name->format]);
        self::assertSame(['email', null], [$email->type, $email->format]);
        self::assertSame([null, $format], [$stock->type, $stock->format]);
        self::assertSame('crud.thing.field.stock.name', $stock->label);
    }

    public function testAColumnIsNothingElse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (the wrong column is the point)
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, columns: ['stock' => new stdClass()]);
    }

    public function testTwoBulkActionsOfAResourceDoNotShareAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, bulkActions: [
            new BulkDeleteAction(),
            new BulkDeleteAction(icon: 'x'),
        ]);
    }

    public function testABulkActionIsNothingElse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (the wrong action is the point)
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, bulkActions: [new stdClass()]);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function wrongPageSizes(): iterable
    {
        yield 'none' => [[]];
        yield 'zero' => [[15, 0]];
        yield 'text' => [['15']];
        yield 'not a list' => [['a' => 15]];
    }

    /**
     * @param array<mixed> $sizes
     */
    #[DataProvider('wrongPageSizes')]
    public function testThePageSizesArePositiveNumbers(array $sizes): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (the wrong sizes are the point)
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, perPage: $sizes);
    }

    public function testTheFirstPageSizeIsOneOfThem(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, perPage: [10, 25], defaultPerPage: 15);
    }

    public function testTheFiltersInViewAreNamesOfFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type (a filter given in place of its name is the point)
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, filtersInView: [new stdClass()]);
    }

    public function testWithChangesWhatItShowsAndKeepsTheRest(): void
    {
        $crud = new AsCrud(
            'thing',
            '/things',
            'box',
            list: ListThingsDto::class,
            read: ShowThingDto::class,
            columns: ['name', 'price' => 'money'],
            section: 'app.nav',
            role: 'ROLE_ADMIN',
            perPage: [10, 25],
        );

        $changed = $crud->with(
            columns: [...$crud->columns, $crud->column('margin', 'money'), 'notes'],
            perPage: [50],
            section: null,
        );

        self::assertSame(['name', 'price', 'margin', 'notes'], array_column($changed->columns, 'field'));
        self::assertSame($crud->columns[1], $changed->columns[1]);
        self::assertInstanceOf(Column::class, $changed->columns[3]);
        self::assertSame('crud.thing.field.notes.name', $changed->columns[3]->label);
        self::assertSame([50], $changed->perPage);
        self::assertNull($changed->section);
        self::assertSame($crud->source, $changed->source);
        self::assertSame('ROLE_ADMIN', $changed->role);
        self::assertTrue($crud->isSameResourceAs($changed));
        // The declaration itself is what it was.
        self::assertSame(['name', 'price'], array_column($crud->columns, 'field'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unchangeable(): iterable
    {
        foreach (['name', 'path', 'scope', 'own', 'source', 'listedIn', 'role', 'list'] as $argument) {
            yield $argument => [$argument];
        }
    }

    #[DataProvider('unchangeable')]
    public function testWithKeepsWhatTheRoutesAreMadeOfAndTheRole(string $argument): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);

        $this->expectException(InvalidArgumentException::class);

        $crud->with(...[$argument => null]);
    }

    public function testWithChangesTheSourceForOneOfTheSameRecords(): void
    {
        $crud = new AsCrud('gadget', '/gadgets', 'box', source: new DoctrineSource(Gadget::class, filters: ['name']));
        $source = $crud->source;
        self::assertInstanceOf(DoctrineSource::class, $source);

        // A filter only for a role, the entity manager of a tenant.
        $changed = $crud->with(source: $source->with(filters: ['name', 'margin'], manager: 'tenant_a'));

        self::assertInstanceOf(DoctrineSource::class, $changed->source);
        self::assertSame(['name', 'margin'], $changed->source->filters);
        self::assertSame('tenant_a', $changed->source->manager);
        self::assertSame(Gadget::class, $changed->source->entity);
    }

    /**
     * @return iterable<string, array{DoctrineSource|DtoSource}>
     */
    public static function otherRecords(): iterable
    {
        yield 'another entity' => [new DoctrineSource(stdClass::class)];
        yield 'other operations' => [new DoctrineSource(Gadget::class, operations: [CrudOperation::List])];
        yield 'another driver' => [new DtoSource(ListThingsDto::class)];
    }

    #[DataProvider('otherRecords')]
    public function testWithKeepsTheRecordsOfTheSource(DoctrineSource|DtoSource $source): void
    {
        $crud = new AsCrud('gadget', '/gadgets', 'box', source: new DoctrineSource(Gadget::class));

        $this->expectException(InvalidArgumentException::class);

        $crud->with(source: $source);
    }

    public function testWithIsCheckedLikeADeclaration(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, perPage: [10, 25]);

        $this->expectException(InvalidArgumentException::class);

        $crud->with(defaultPerPage: 15);
    }

    public function testOptionsOfTheApplication(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, options: ['export' => true]);

        self::assertTrue($crud->option('export'));
        self::assertSame('none', $crud->option('audit', 'none'));
        $changed = $crud->with(options: [...$crud->options, 'audit' => 'full']);
        self::assertSame('full', $changed->option('audit'));
        self::assertTrue($crud->isSameResourceAs($changed), 'What an extension may change.');

        $this->expectException(InvalidArgumentException::class);
        $crud->with(options: ['export']);
    }

    public function testAnotherDeclarationOfTheNameIsAnotherResource(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);

        self::assertFalse($crud->isSameResourceAs(new AsCrud('thing', '/stuff', 'box', list: ListThingsDto::class)));
        self::assertFalse($crud->isSameResourceAs(
            new AsCrud('thing', '/things', 'box', source: new DtoSource(
                ListThingsDto::class,
                read: ReadThingDto::class,
            )),
        ));
        self::assertFalse($crud->isSameResourceAs(
            new AsCrud('thing', '/things', 'box', source: new DtoSource(ShowThingDto::class)),
        ), 'Another use case of the list.');
        // Of the same records, declared again: what a CrudDefinitionExtension gives back is not the same object.
        self::assertTrue($crud->isSameResourceAs(
            new AsCrud('thing', '/things', 'box', source: new DtoSource(ListThingsDto::class)),
        ));
    }

    public function testTheListAndTheFormMayBeComponentsOfTheApplication(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);
        self::assertSame(['Crud:List', 'Crud:Form'], [$crud->listComponent, $crud->formComponent]);

        $own = $crud->with(listComponent: 'App:ThingList');
        self::assertSame('App:ThingList', $own->listComponent);
        self::assertTrue($crud->isSameResourceAs($own));

        $this->expectException(InvalidArgumentException::class);
        new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, formComponent: '');
    }

    public function testAPathHasParametersOfTheApplication(): void
    {
        $crud = new AsCrud('category_product', '/categories/{category}/products', 'box', list: ListThingsDto::class);

        self::assertSame(['category'], $crud->pathParameters());
        self::assertFalse($crud->inPalette(CrudOperation::List), 'Its pages are reached from a page that knows them.');
        self::assertSame([], new AsCrud('thing', '/things', 'box', list: ListThingsDto::class)->pathParameters());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsOfTheBundlesParameters(): iterable
    {
        yield 'the id' => ['/things/{id}'];
        yield 'the page' => ['/things/{page}'];
        yield 'the resource' => ['/{crud}/things'];
        yield 'one starting with _' => ['/{_scope}/things'];
        yield 'an open brace' => ['/things/{category'];
        yield 'no slash' => ['things'];
    }

    #[DataProvider('pathsOfTheBundlesParameters')]
    public function testAPathHasNoParameterOfTheBundles(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AsCrud('thing', $path, 'box', list: ListThingsDto::class);
    }

    public function testAResourceWithParametersIsNoPageOfTheMenu(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no page of the menu');

        new AsCrud('thing', '/categories/{category}/things', 'box', list: ListThingsDto::class, section: 'app.nav');
    }
}
