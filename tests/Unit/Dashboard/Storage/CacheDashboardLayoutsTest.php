<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard\Storage;

use Byfareska\Crud\Dashboard\Storage\CacheDashboardLayouts;
use Byfareska\Crud\Dashboard\WidgetPlacement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The layouts in a cache pool: one per owner and dashboard, and the one for everybody - which no user is, also a user
 * whose id is "*".
 */
final class CacheDashboardLayoutsTest extends TestCase
{
    public function testAUserCalledAsEverybodyHasALayoutOfTheirOwn(): void
    {
        $layouts = new CacheDashboardLayouts(new ArrayAdapter());
        $layouts->save(null, 'panel', [new WidgetPlacement('a1', 'panel.clock', 0, 0, 3, 2)]);

        self::assertNull($layouts->find('*', 'panel'), 'The layout for everybody is no layout of the user "*".');

        $layouts->save('*', 'panel', [new WidgetPlacement('b2', 'panel.note', 0, 0, 3, 2)]);
        $layouts->save('ewa', 'panel', [new WidgetPlacement('c3', 'panel.note', 3, 0, 3, 2)]);
        $layouts->delete('*', 'panel');

        self::assertSame('a1', $layouts->find(null, 'panel')[0]->id ?? null, 'Saved and deleted by its owner.');
        self::assertSame('c3', $layouts->find('ewa', 'panel')[0]->id ?? null);
        self::assertNull($layouts->find('*', 'panel'));
    }
}
