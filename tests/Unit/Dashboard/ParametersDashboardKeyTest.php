<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\Dashboard\ParametersDashboardKey;
use Byfareska\Crud\Routing\PageParameters;
use PHPUnit\Framework\TestCase;

final class ParametersDashboardKeyTest extends TestCase
{
    public function testTheLayoutsOfAPanelPerShopAreKeptApart(): void
    {
        $parameters = new PageParameters();
        $keys = new ParametersDashboardKey($parameters);
        self::assertSame('admin', $keys->of('admin'));

        $parameters->set(['shop' => '5', 'region' => 'eu']);
        self::assertSame('admin?region=eu&shop=5', $keys->of('admin'));

        $parameters->set(['shop' => str_repeat('x', 100)]);
        $long = $keys->of('admin');
        self::assertSame(64, strlen($long));
        self::assertNotSame($long, (static function () use ($parameters, $keys): string {
            $parameters->set(['shop' => str_repeat('x', 99) . 'y']);

            return $keys->of('admin');
        })(), 'Cut, and still apart.');
    }
}
