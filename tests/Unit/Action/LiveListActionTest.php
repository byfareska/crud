<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\LiveListAction;
use Byfareska\Crud\Action\LiveListActionHandler;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use PHPUnit\Framework\TestCase;

final class LiveListActionTest extends TestCase
{
    public function testTheButtonRunsTheLiveActionOfTheListWithTheId(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, listComponent: 'App:ThingList');
        $action = new LiveListAction('toggle', 'toggleActive', 'toggle-on', inline: true);

        $button = new LiveListActionHandler()->button($action, new RecordView($crud, '7', [], RecordScreen::List));

        self::assertNotNull($button);
        self::assertSame('crud.thing.action.toggle', $button->label);
        self::assertTrue($button->inline);
        self::assertSame('live#action', $button->attributes['data-action']);
        self::assertSame('toggleActive', $button->attributes['data-live-action-param']);
        self::assertSame('7', $button->attributes['data-live-id-param']);
        $preview = new RecordView($crud, '7', [], RecordScreen::Preview);
        self::assertNotNull(new LiveListActionHandler()->button($action, $preview));
    }

    public function testThePagesOfTheRecordHaveNoList(): void
    {
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);
        $action = new LiveListAction('toggle', 'toggleActive', 'toggle-on');
        $handler = new LiveListActionHandler();

        self::assertNull($handler->button($action, new RecordView($crud, '7', [], RecordScreen::Details)));
        self::assertNull($handler->button($action, new RecordView($crud, '7', [], RecordScreen::Form)));
    }
}
