<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Driver\Doctrine\DoctrineForm;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\WordsSearch;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DoctrineSourceTest extends TestCase
{
    public function testASearchOfItsOwnNeedsNoFields(): void
    {
        self::assertNull(new DoctrineSource(stdClass::class)->searchField());
        self::assertSame('query', new DoctrineSource(stdClass::class, search: ['name'])->searchField());
        // ElasticSearch: no field of the entity to name.
        self::assertSame('query', new DoctrineSource(stdClass::class, searchWith: WordsSearch::class)->searchField());
    }

    public function testOneFormForAddingAndEditing(): void
    {
        $guessed = new DoctrineSource(stdClass::class, fields: ['name']);
        self::assertEquals(new DoctrineForm(fields: ['name']), $guessed->formOf(true));
        self::assertEquals(new DoctrineForm(fields: ['name']), $guessed->formOf(false));

        $typed = new DoctrineSource(stdClass::class, form: ListThingsDto::class);
        self::assertEquals(new DoctrineForm(ListThingsDto::class), $typed->formOf(true));
        self::assertEquals(new DoctrineForm(ListThingsDto::class), $typed->formOf(false));
    }

    public function testTheAddAndTheEditFormInPlaceOfIt(): void
    {
        $edit = new DoctrineForm(ShowThingDto::class, ['validation_groups' => ['edit']]);
        $source = new DoctrineSource(
            stdClass::class,
            form: ListThingsDto::class,
            createForm: new DoctrineForm(fields: ['name', 'password']),
            updateForm: $edit,
        );

        self::assertEquals(new DoctrineForm(fields: ['name', 'password']), $source->formOf(true));
        self::assertSame($edit, $source->formOf(false));
        self::assertEquals(
            new DoctrineForm(ListThingsDto::class),
            new DoctrineSource(stdClass::class, form: ListThingsDto::class, createForm: $edit)->formOf(false),
        );
    }

    public function testAFormTypeHasItsFieldsItself(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DoctrineForm(ListThingsDto::class, fields: ['name']);
    }

    public function testTheFieldsOfADoctrineFormGoIntoIt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DoctrineSource(stdClass::class, form: new DoctrineForm(), fields: ['name']);
    }
}
