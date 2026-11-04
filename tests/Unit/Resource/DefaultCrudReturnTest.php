<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\AfterSave;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\DefaultCrudReturn;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class DefaultCrudReturnTest extends TestCase
{
    public function testTheWayBackIsTheList(): void
    {
        self::assertSame('/gadgets', $this->return()->afterSave($this->gadget(), '7', false));
        self::assertSame('/gadgets', $this->return()->afterSaveTo($this->gadget(), '7', true, AfterSave::Return));
    }

    public function testEditLeadsToTheEditFormOfTheRecordSavedAddedOrNot(): void
    {
        self::assertSame('/gadgets/7/edit', $this->return()->afterSaveTo($this->gadget(), '7', false, AfterSave::Edit));
        self::assertSame('/gadgets/8/edit', $this->return()->afterSaveTo($this->gadget(), '8', true, AfterSave::Edit));
    }

    public function testANewRecordWhoseIdIsNotKnownGoesBack(): void
    {
        self::assertSame('/gadgets', $this->return()->afterSaveTo($this->gadget(), null, true, AfterSave::Edit));
        self::assertSame('/gadgets', $this->return()->afterSaveTo($this->gadget(), null, true, AfterSave::Show));
    }

    public function testShowLeadsToTheDetailsOfTheRecord(): void
    {
        self::assertSame('/gadgets/7', $this->return()->afterSaveTo($this->gadget(), '7', true, AfterSave::Show));
    }

    public function testWhatTheResourceDoesNotDoGoesBack(): void
    {
        $return = $this->return();
        $gadget = $this->gadget()->refusing(CrudOperation::Read, CrudOperation::Update, CrudOperation::Create);

        self::assertSame('/gadgets', $return->afterSaveTo($gadget, '7', false, AfterSave::Show));
        self::assertSame('/gadgets', $return->afterSaveTo($gadget, '7', false, AfterSave::Edit));
        self::assertSame('/gadgets', $return->afterSaveTo($gadget, '7', false, AfterSave::New));
    }

    public function testNewLeadsToTheAddForm(): void
    {
        self::assertSame('/gadgets/new', $this->return()->afterSaveTo($this->gadget(), '7', true, AfterSave::New));
    }

    public function testOnesOwnRecordHasAnAddressWithoutAnId(): void
    {
        $profile = new AsCrud('profile', '/profile', 'person', read: ShowThingDto::class, own: true);

        self::assertSame('/profile', $this->return()->afterSaveTo($profile, null, false, AfterSave::Show));
    }

    private function gadget(): AsCrud
    {
        return new AsCrud('gadget', '/gadgets', 'box', source: new DoctrineSource(stdClass::class));
    }

    private function return(): DefaultCrudReturn
    {
        $routes = new RouteCollection();
        $routes->add('crud_gadget_list', new Route('/gadgets'));
        $routes->add('crud_gadget_new', new Route('/gadgets/new'));
        $routes->add('crud_gadget_show', new Route('/gadgets/{id}'));
        $routes->add('crud_gadget_edit', new Route('/gadgets/{id}/edit'));
        $routes->add('crud_profile_show', new Route('/profile'));
        $routes->add('crud_profile_edit', new Route('/profile/edit'));

        return new DefaultCrudReturn(new CrudRegistry(), new UrlGenerator($routes, new RequestContext()));
    }
}
