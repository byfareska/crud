<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Component\Crud\CrudForm;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Tests\Functional\App\Entity\Memo;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\UX\TwigComponent\ComponentFactory;

/**
 * The add form started as a copy of a record (?from=<id>): only by the way its DuplicateAction offers, whatever the
 * address names.
 */
final class CopiedRecordsTest extends PanelTestCase
{
    public function testARecordIsCopiedByTheDuplicateActionOfItsResource(): void
    {
        $this->boot();
        $this->seed();
        $memo = new Memo();
        $memo->title = 'Meter replaced';
        $entities = $this->entities();
        $entities->persist($memo);
        $entities->flush();
        $entities->clear();

        self::assertSame('Meter replaced', $this->addForm('memo', '1')->children['title']->vars['value']);
    }

    public function testAResourceWithoutADuplicateActionCopiesNothingByTheAddress(): void
    {
        $this->boot();
        $this->seed();

        // A customer that may be read, and a form that may add one: the address is no way to a copy all the same.
        try {
            $this->addForm('customer', '1');
            self::fail('A customer was copied by the address alone.');
        } catch (HttpException $refused) {
            self::assertSame(403, $refused->getStatusCode());
        }
    }

    /**
     * The add form of a resource (Crud:Form) starting as a copy of a record.
     */
    private function addForm(string $resource, string $from): FormView
    {
        $requests = $this->container()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requests);
        $registry = $this->container()->get(CrudRegistry::class);
        self::assertInstanceOf(CrudRegistry::class, $registry);
        $factory = $this->container()->get('ux.twig_component.component_factory');
        self::assertInstanceOf(ComponentFactory::class, $factory);

        $requests->push(Request::create('/'));
        try {
            $form = $factory->create('Crud:Form', ['resource' => $resource, 'from' => $from])->getComponent();
            self::assertInstanceOf(CrudForm::class, $form);

            return $form->getFormView();
        } finally {
            $requests->pop();
            $registry->reset();
        }
    }
}
