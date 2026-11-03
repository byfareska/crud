<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\RouteAction;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudToast;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingPriority;
use PHPUnit\Framework\TestCase;

final class RouteActionTest extends TestCase
{
    public function testParametersOfTheRouteAreTakenFromTheRecord(): void
    {
        $crud = new AsCrud('order', '/orders', 'cart', list: ListThingsDto::class);
        $action = new RouteAction(
            'invoices',
            'app_customer_invoices',
            'receipt',
            parameters: ['status' => 'open'],
            fieldParameters: ['customer' => 'customerId'],
        );

        self::assertSame(['customerId'], $action->fields(), 'Asked of the items of the list.');
        self::assertSame(
            ['id' => '12', 'status' => 'open', 'customer' => 7],
            $action->parameters($crud, '12', ['customerId' => 7, 'number' => 'FV/1']),
        );
    }

    public function testAToastIsToldTheValuesOfTheRecord(): void
    {
        $record = new class {
            public string $number = 'FV/2026/11';

            public ThingPriority $priority = ThingPriority::High;

            /** @var list<string> */
            public array $lines = ['not a value of a text'];
        };

        self::assertSame(
            ['id' => '5', 'number' => 'FV/2026/11', 'priority' => 'high'],
            CrudToast::parametersOf('5', $record),
        );
    }
}
