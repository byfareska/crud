<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Layout;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Layout\LayoutPage;
use Byfareska\Crud\Layout\PageLayoutCondition;
use Byfareska\Crud\Layout\When;
use Byfareska\Crud\Resource\CrudLayouts;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * One condition of a kind for many of its parts, by the options of each When and the page it is on.
 */
final class PageLayoutConditionTest extends TestCase
{
    public function testTheOptionsOfTheWhenAndThePage(): void
    {
        $status = new class implements PageLayoutCondition {
            /** @var list<LayoutPage> */
            public array $pages = [];

            public function shows(AsCrud $crud, ?RecordView $record): bool
            {
                return false;
            }

            public function showsOn(AsCrud $crud, ?RecordView $record, LayoutPage $page, array $options): bool
            {
                $this->pages[] = $page;

                return ($record?->item['status'] ?? null) === $options['status'];
            }
        };
        $crud = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(stdClass::class), layout: [
            'number',
            new When($status::class, ['paidAt'], options: ['status' => 'paid']),
            new When($status::class, ['refundedAt'], options: ['status' => 'refunded']),
        ]);
        $layouts = new CrudLayouts(
            new CrudRegistry(),
            self::createStub(CrudRecords::class),
            new ServiceLocator([$status::class => static fn (): PageLayoutCondition => $status]),
        );
        $paid = new RecordView($crud, '7', ['status' => 'paid'], RecordScreen::Form);

        self::assertSame(['refundedAt'], $layouts->hiddenInForm($crud, $paid));
        self::assertSame([LayoutPage::Form, LayoutPage::Form], $status->pages);
    }
}
