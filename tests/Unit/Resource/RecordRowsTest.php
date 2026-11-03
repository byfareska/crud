<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordRow;
use Byfareska\Crud\Resource\RecordRows;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use PHPUnit\Framework\TestCase;

/**
 * The attributes of a row of the list, of every RecordRow of its resource.
 */
final class RecordRowsTest extends TestCase
{
    public function testTheRowsOfTheResourceAddToTheRowTheirClassesPutTogether(): void
    {
        $rows = new RecordRows([
            $this->row('thing', ['class' => 'late', 'data-state' => 'late', 'id' => 'stolen'], ['due']),
            $this->row(RecordRow::EVERY, ['class' => 'mine', 'data-state' => 'mine'], ['owner']),
            $this->row('other', ['class' => 'nothing'], ['nothing']),
        ]);
        $thing = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);

        self::assertSame(
            ['data-state' => 'mine', 'class' => 'late mine'],
            $rows->attributes(new RecordView($thing, '7', [], RecordScreen::List)),
        );
        self::assertSame(['due', 'owner'], $rows->fields($thing));
    }

    /**
     * @param array<string, string> $attributes
     * @param list<string> $fields
     */
    private function row(string $resource, array $attributes, array $fields): RecordRow
    {
        return new readonly class ($resource, $attributes, $fields) implements RecordRow {
            /**
             * @param array<string, string> $attributes
             * @param list<string> $fields
             */
            public function __construct(
                private string $resource,
                private array $attributes,
                private array $fields,
            ) {
            }

            public function resource(): string
            {
                return $this->resource;
            }

            public function fields(): array
            {
                return $this->fields;
            }

            public function attributes(RecordView $record): array
            {
                return $this->attributes;
            }
        };
    }
}
