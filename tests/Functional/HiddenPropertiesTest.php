<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\CommandPalette\PaletteRecord;
use Byfareska\Crud\Component\Crud\CrudForm;
use Byfareska\Crud\Driver\Doctrine\DoctrineDriver;
use Byfareska\Crud\Driver\Doctrine\DoctrineForm;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\FieldFilter;
use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Resource\CrudListQuery;
use Byfareska\Crud\Resource\CrudPaletteRecords;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Tests\Functional\App\Entity\AccountStatus;
use Byfareska\Crud\Tests\Functional\App\Entity\Customer;
use Byfareska\Crud\Tests\Functional\App\Entity\Memo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\ComponentFactory;

/**
 * A property the resource hides (AsCrud::$hidden) - declared, or for one request by a CrudDefinitionExtension (the
 * application's HiddenByAddress, ?hidden=number) - is seen nowhere and reached by nothing: not in the guessed form nor
 * prefilled or copied into one, not searched, ordered nor filtered by, whatever the source names.
 */
final class HiddenPropertiesTest extends PanelTestCase
{
    public function testAHiddenPropertyIsNoFieldOfTheGuessedEditForm(): void
    {
        $container = $this->boot();
        $this->seed();
        $driver = $container->get(DoctrineDriver::class);
        self::assertInstanceOf(DoctrineDriver::class, $driver);
        $crud = $this->customer($container);
        self::assertTrue($driver->form($crud, '1')->getForm()->has('number'), 'Not hidden, it is edited.');

        $form = $driver->form($crud->with(hidden: ['number']), '1')->getForm();
        self::assertFalse($form->has('number'));
        $form->submit(['name' => 'Ewa Lisowska', 'number' => '666', 'status' => 'active'], false);
        $customer = $form->getData();
        self::assertInstanceOf(Customer::class, $customer);
        self::assertSame('1187234', $customer->number, 'Not to be written by a form that has no field of it.');
    }

    public function testAFormTheApplicationNamesTheFieldsOfIsItsOwn(): void
    {
        $container = $this->boot();
        $this->seed();
        $this->addCustomer('Adam Nowak', '9000000', AccountStatus::Closed);
        $driver = $container->get(DoctrineDriver::class);
        self::assertInstanceOf(DoctrineDriver::class, $driver);
        $crud = $this->customer($container, new DoctrineSource(
            Customer::class,
            form: new DoctrineForm(fields: ['name', 'number', 'status']),
        ))->with(hidden: ['number']);

        $builder = $driver->form($crud, '1');
        self::assertTrue($builder->getForm()->has('number'));

        // But nothing of the address nor of another record comes into it.
        $driver->prefill($crud, $builder, ['number' => '999', 'name' => 'Jan']);
        $driver->copy($crud, '2', $builder, ['number', 'status']);
        $customer = $builder->getData();
        self::assertInstanceOf(Customer::class, $customer);
        self::assertSame('Jan', $customer->name, 'Prefilled.');
        self::assertSame(AccountStatus::Closed, $customer->status, 'Copied.');
        self::assertSame('1187234', $customer->number, 'Neither prefilled nor copied.');
    }

    public function testTheEditFormOfARequestThatHidesAPropertyHasNoFieldOfIt(): void
    {
        $this->boot();
        $this->seed();

        $view = $this->formView('customer', ['id' => '1']);
        self::assertSame('1187234', $view->children['number']->vars['value'] ?? null, 'Not hidden, it is edited.');

        $view = $this->formView('customer', ['id' => '1'], '?hidden=number');
        self::assertArrayNotHasKey('number', $view->children);
        self::assertArrayHasKey('name', $view->children);
    }

    public function testNoHiddenPropertyIsCopiedNorPrefilledIntoTheAddForm(): void
    {
        $this->boot();
        $this->seed();
        $this->addMemo('Meter replaced', 'complained twice');

        // Its form names the remark: the application's own, kept - but nothing comes into it.
        self::assertSame('complained twice', $this->formView('memo', ['id' => '1'])->children['remark']->vars['value']);
        $view = $this->formView('memo', ['from' => '1', 'with' => ['remark' => 'by the address']]);
        self::assertSame('', $view->children['remark']->vars['value']);
        self::assertSame('Meter replaced', $view->children['title']->vars['value'], 'What is not hidden is copied.');
        $view = $this->formView('memo', ['with' => ['remark' => 'by the address', 'title' => 'By the address']]);
        self::assertSame('', $view->children['remark']->vars['value']);
        self::assertSame('By the address', $view->children['title']->vars['value'], 'What is not hidden is prefilled.');
    }

    public function testADeclaredHiddenPropertyIsNoColumn(): void
    {
        $this->boot();
        $this->seed();
        $this->addMemo('Meter replaced', 'complained twice');

        $html = $this->request('/memos');
        self::assertContains('Meter replaced', self::texts($html, 'tbody td'));
        self::assertStringNotContainsString('complained twice', $html);
        self::assertStringNotContainsString('complained twice', $this->request('/memos/1'));
    }

    public function testAHiddenPropertyIsNotSearched(): void
    {
        $container = $this->boot();
        $this->seed();
        $query = $this->listQuery($container);
        $crud = $this->customer($container, new DoctrineSource(Customer::class, search: ['name', 'number']));
        self::assertCount(1, iterator_to_array($query->items($crud, ['query' => '1187234'])), 'Not hidden, searched.');

        $hiding = $crud->with(hidden: ['number']);
        self::assertSame([], iterator_to_array($query->items($hiding, ['query' => '1187234'])));
        self::assertCount(1, iterator_to_array($query->items($hiding, ['query' => 'Ewa'])));

        // With nothing left to look in, a typed text finds nothing - not everything.
        $onlyHidden = $this->customer($container, new DoctrineSource(Customer::class, search: ['number']))
            ->with(hidden: ['number']);
        self::assertSame([], iterator_to_array($query->items($onlyHidden, ['query' => 'Ewa'])));
    }

    public function testTheCommandPaletteFindsNothingByAHiddenProperty(): void
    {
        $this->boot();
        $this->seed();
        $palette = $this->container()->get(CrudPaletteRecords::class);
        self::assertInstanceOf(CrudPaletteRecords::class, $palette);
        $registry = $this->container()->get(CrudRegistry::class);
        self::assertInstanceOf(CrudRegistry::class, $registry);
        $requests = $this->container()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        $found = static fn (): array => array_map(
            static fn (PaletteRecord $record): string => $record->name,
            $palette->searchIn('1187234', 5, null),
        );

        // Searched by its number (HiddenByAddress names it); the status hidden does not name it.
        $requests->push(Request::create('/?hidden=status'));
        self::assertSame(['Ewa Lisowska'], $found());
        $requests->pop();
        $registry->reset();

        $requests->push(Request::create('/?hidden=number'));
        self::assertSame([], $found());
        $requests->pop();
    }

    public function testAHiddenPropertyIsNotOrderedByOnRequest(): void
    {
        $container = $this->boot();
        $this->seed();
        $this->addCustomer('Adam Nowak', '9000000');
        $query = $this->listQuery($container);
        $crud = $this->customer($container, new DoctrineSource(Customer::class, sortable: ['name', 'number']));

        $form = $query->form($crud, [], '-number');
        self::assertContains('-number', $query->sortable($form), 'Not hidden, the list is ordered by it.');
        self::assertSame(['-number'], $form->get('sort')->getData());

        $hiding = $crud->with(hidden: ['number']);
        $form = $query->form($hiding, [], '-number');
        self::assertNotContains('number', $query->sortable($form));
        self::assertNotContains('-number', $query->sortable($form));
        self::assertSame(['id'], $form->get('sort')->getData(), 'The order nobody chose.');

        // A list form submitted by hand (an address of the list) does not take it either.
        $driver = $container->get(DoctrineDriver::class);
        self::assertInstanceOf(DoctrineDriver::class, $driver);
        $handmade = $driver->listForm($hiding)->submit(['sort' => ['-number', 'name']]);
        self::assertTrue($handmade->isValid(), (string) $handmade->getErrors(true));
        self::assertSame(['name'], $handmade->get('sort')->getData());
    }

    public function testTheOrderOfAListNobodySortedMayBeByAHiddenPropertyButNobodyChoosesIt(): void
    {
        $container = $this->boot();
        $this->seed();
        $this->addCustomer('Adam Nowak', '9000000');
        $driver = $container->get(DoctrineDriver::class);
        self::assertInstanceOf(DoctrineDriver::class, $driver);
        $query = $this->listQuery($container);
        $crud = $this->customer($container, new DoctrineSource(Customer::class, sort: ['-number']))
            ->with(hidden: ['number']);
        $names = static fn (iterable $items): array => array_map(
            static fn (mixed $item): mixed => is_array($item) ? $item['name'] ?? null : null,
            iterator_to_array($items, false),
        );

        // Ordered by it, the developer's choice.
        self::assertSame(['Adam Nowak', 'Ewa Lisowska'], $names($query->items($crud)));
        // Asked the other way, by the address or a form submitted by hand: the order nobody chose all the same.
        self::assertSame(['Adam Nowak', 'Ewa Lisowska'], $names($query->items($crud, [], 'number')));
        $form = $driver->listForm($crud)->submit(['sort' => ['number'], 'fields' => ['name']]);
        self::assertTrue($form->isValid());
        self::assertSame(['-number'], $form->get('sort')->getData());
        $form = $driver->listForm($crud)->submit(['sort' => ['name', 'number'], 'fields' => ['name']]);
        self::assertSame(['name'], $form->get('sort')->getData());
        self::assertSame(
            ['Adam Nowak', 'Ewa Lisowska'],
            $names($driver->list($crud, $form)->items),
        );
    }

    public function testAHiddenPropertyIsNoFilter(): void
    {
        $container = $this->boot();
        $this->seed();
        $driver = $container->get(DoctrineDriver::class);
        self::assertInstanceOf(DoctrineDriver::class, $driver);
        $crud = $this->customer($container, new DoctrineSource(Customer::class, filters: [
            'name',
            'number',
            'byNumber' => new FieldFilter('number', [Comparison::Equals]),
        ]));
        $form = $driver->listForm($crud);
        self::assertTrue($form->has('number') && $form->has('byNumber'), 'Not hidden, filtered by.');

        $form = $driver->listForm($crud->with(hidden: ['number']));
        self::assertTrue($form->has('name'));
        self::assertFalse($form->has('number'));
        self::assertFalse($form->has('byNumber'), 'By the field it compares, whatever its name.');

        // Nor matched by one sent all the same.
        $query = $this->listQuery($container);
        $hiding = $crud->with(hidden: ['number']);
        $filter = ['number' => ['comparison' => 'eq', 'value' => 'nothing like it']];
        self::assertCount(1, iterator_to_array($query->items($hiding, $filter)));
    }

    public function testADetailsPageAndTheListOfARequestThatHidesAPropertyDoNotShowIt(): void
    {
        $this->boot();
        $this->seed();

        self::assertContains('aktywna', self::texts($this->request('/customers'), 'tbody td'));
        $html = $this->request('/customers?hidden=status');
        self::assertNotContains('aktywna', self::texts($html, 'tbody td'));

        $html = $this->request('/customers/1?hidden=status');
        self::assertNotContains('aktywna', self::texts($html, 'main dd'));
        // Not what the record is named by either: its title is its name alone.
        self::assertSame(['Ewa Lisowska Klienci'], self::texts($html, 'h1'));

        $records = $this->container()->get(CrudRecords::class);
        self::assertInstanceOf(CrudRecords::class, $records);
        $crud = $this->customer($this->container())->with(hidden: ['status']);
        $rows = $records->details($crud, '1')['rows'];
        self::assertNotContains('status', array_map(static fn (array $row): string => $row['column']->field, $rows));
    }

    private function customer(ContainerInterface $container, ?DoctrineSource $source = null): AsCrud
    {
        $registry = $container->get(CrudRegistry::class);
        self::assertInstanceOf(CrudRegistry::class, $registry);
        $crud = $registry->get('customer');

        return $source instanceof DoctrineSource ? $crud->with(source: $source) : $crud;
    }

    /**
     * The view of the form of a resource (Crud:Form) in a request of the address given.
     *
     * @param array<string, mixed> $props
     */
    private function formView(string $resource, array $props, string $query = ''): FormView
    {
        $requests = $this->container()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        $registry = $this->container()->get(CrudRegistry::class);
        self::assertInstanceOf(CrudRegistry::class, $registry);
        $factory = $this->container()->get('ux.twig_component.component_factory');
        self::assertInstanceOf(ComponentFactory::class, $factory);

        $requests->push(Request::create('/' . $query));
        try {
            $form = $factory->create('Crud:Form', ['resource' => $resource, ...$props])->getComponent();
            self::assertInstanceOf(CrudForm::class, $form);

            return $form->getFormView();
        } finally {
            $requests->pop();
            $registry->reset();
        }
    }

    private function listQuery(ContainerInterface $container): CrudListQuery
    {
        $query = $container->get(CrudListQuery::class);
        self::assertInstanceOf(CrudListQuery::class, $query);

        return $query;
    }

    private function addCustomer(string $name, string $number, AccountStatus $status = AccountStatus::Active): void
    {
        $entities = $this->entities();
        $entities->persist(new Customer($name, $number, $status));
        $entities->flush();
        $entities->clear();
    }

    private function addMemo(string $title, string $remark): void
    {
        $memo = new Memo();
        $memo->title = $title;
        $memo->remark = $remark;
        $entities = $this->entities();
        $entities->persist($memo);
        $entities->flush();
        $entities->clear();
    }
}
