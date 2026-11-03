<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Tests\Unit\Fixtures\HiddenActionHandler;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\UpperFormat;
use Byfareska\Crud\Tests\Unit\Fixtures\UpperFormatter;
use Byfareska\Crud\Twig\CrudValueRenderer;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Byfareska\Crud\Value\TypedValue;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * A column with a format is printed by its formatter; the rest by the blocks of the theme.
 */
final class CrudValueRendererTest extends TestCase
{
    public function testAFormatPrintsTheValueBeforeTheBlocks(): void
    {
        $crud = $this->thing(['sku' => new UpperFormat()]);
        $item = ['name' => 'Mug', 'sku' => 'kub-001'];

        self::assertSame('KUB-001 (list, Mug)', $this->render($crud, 0, 'kub-001', $item));
    }

    public function testAnEmptyValueIsStillTheBlockOfEmptiness(): void
    {
        self::assertSame('—', $this->render($this->thing(['sku' => new UpperFormat()]), 0, null, []));
    }

    public function testAColumnWithoutAFormatIsPrintedByTheBlocks(): void
    {
        self::assertSame('<b>kub-001</b>', $this->render($this->thing(['sku' => 'code']), 0, 'kub-001', []));
    }

    public function testAValueThatSaysWhatItIsIsPrintedByTheBlocksOfItsKind(): void
    {
        $crud = $this->thing(['sku']);

        self::assertSame('<b>KUB-1</b>', $this->render($crud, 0, new TypedValue('code', 'KUB-1'), []));
        self::assertSame('—', $this->render($crud, 0, new TypedValue('code', ''), []));
        self::assertSame('KUB-1 (list, Mug)', $this->render(
            $crud,
            0,
            new TypedValue('code', 'kub-1', new UpperFormat()),
            ['name' => 'Mug'],
        ));
    }

    public function testAValueIsMadeReadyByTheNormalizersFirst(): void
    {
        $date = $this->render($this->thing(['sku']), 0, new DateTimeImmutable('2026-10-31 12:00'), []);

        self::assertSame('2026-10-31', $date);
    }

    public function testAFormatWhoseFormatterIsNoServiceIsAMistake(): void
    {
        // @phpstan-ignore argument.type (no formatter is the point)
        $crud = $this->thing(['sku' => new UpperFormat(HiddenActionHandler::class)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(HiddenActionHandler::class);

        $this->render($crud, 0, 'kub-001', []);
    }

    /**
     * @param array<int|string, mixed> $columns
     */
    private function thing(array $columns): AsCrud
    {
        // @phpstan-ignore argument.type (the columns of the tests are right)
        return new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, columns: $columns);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function render(AsCrud $crud, int $column, mixed $value, array $item): string
    {
        $twig = new Environment(new ArrayLoader([
            CrudValueRenderer::DEFAULT_THEME => '{% block type_empty %}—{% endblock %}'
                . '{% block type_code %}<b>{{ value }}</b>{% endblock %}'
                . '{% block type_date %}{{ value|date("Y-m-d") }}{% endblock %}'
                . '{% block value %}{{ value }}{% endblock %}',
        ]));
        $renderer = new CrudValueRenderer(
            new ServiceLocator([UpperFormatter::class => static fn (): UpperFormatter => new UpperFormatter()]),
            new CrudValues([new PlainValueNormalizer()]),
        );

        return $renderer->render($twig, $crud, $crud->columns[$column], $value, $item, 'list');
    }
}
