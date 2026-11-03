<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Layout;

use Byfareska\Crud\Layout\CrudLayout;
use Byfareska\Crud\Layout\Fieldset;
use Byfareska\Crud\Layout\LayoutCondition;
use Byfareska\Crud\Layout\LayoutNode;
use Byfareska\Crud\Layout\Row;
use Byfareska\Crud\Layout\Tab;
use Byfareska\Crud\Layout\When;
use Byfareska\Crud\Tests\Unit\Fixtures\Notice;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CrudLayoutTest extends TestCase
{
    public function testWhenStandsForItsChildrenAndIsShownByItsCondition(): void
    {
        $refund = new When(LayoutCondition::class, [new Tab('refund', children: ['amount'])]);
        $layout = [new Tab('basics', children: ['name']), $refund];
        CrudLayout::validate($layout);

        $available = ['name' => true, 'amount' => true];
        $shown = CrudLayout::resolve($layout, $available, static fn (When $when): bool => true);
        $hidden = CrudLayout::resolve($layout, $available, static fn (When $when): bool => false);
        self::assertSame(['basics', 'refund'], $this->tabs($shown));
        self::assertSame(['basics'], $this->tabs($hidden));
        self::assertSame(['name', 'amount'], CrudLayout::fields($layout));
        // What it keeps off the page is not to be in the form either.
        self::assertSame([], CrudLayout::hidden($layout, static fn (When $when): bool => true));
        self::assertSame(['amount'], CrudLayout::hidden($layout, static fn (When $when): bool => false));
    }

    public function testWhatAWhenHoldsIsCheckedWhereItStands(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A tab cannot stand in a row');

        CrudLayout::validate([new Row([new When(LayoutCondition::class, [new Tab('refund')])])]);
    }

    public function testAPartOfTheApplicationsOwnSaysWhereItStands(): void
    {
        $layout = [new Fieldset('basics', ['name', new Notice('Prices are net.')])];
        CrudLayout::validate($layout);

        // Holding nothing, it is on every page.
        $resolved = CrudLayout::resolve($layout, []);
        self::assertCount(1, $resolved);
        self::assertInstanceOf(Fieldset::class, $resolved[0]);
        self::assertInstanceOf(Notice::class, $resolved[0]->children[0]);

        $this->expectException(LogicException::class);
        CrudLayout::validate([new Row([new Notice('Not here.')])]);
    }

    public function testAShowIfIsKeptWhenAPageTakesWhatItHas(): void
    {
        $fieldset = new Fieldset('schedule', ['when', 'every'], showIf: 'trigger=schedule');

        $resolved = CrudLayout::resolve([$fieldset], ['when' => true]);

        self::assertInstanceOf(Fieldset::class, $resolved[0]);
        self::assertSame('trigger=schedule', $resolved[0]->showIf);
        self::assertSame('kind=company', new Tab('company', showIf: 'kind=company')->with([])->showIf);
    }

    /**
     * @param list<LayoutNode> $nodes
     *
     * @return list<string>
     */
    private function tabs(array $nodes): array
    {
        return array_map(static fn (LayoutNode $node): string => $node instanceof Tab ? $node->name : '?', $nodes);
    }
}
