<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Layout;

use Byfareska\Crud\Layout\Breakpoint;
use Byfareska\Crud\Layout\Column;
use Byfareska\Crud\Layout\CrudLayout;
use Byfareska\Crud\Layout\Field;
use Byfareska\Crud\Layout\Fieldset;
use Byfareska\Crud\Layout\Row;
use Byfareska\Crud\Layout\Span;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class SpanTest extends TestCase
{
    public function testWidthsByBreakpointTheSmallestFirst(): void
    {
        self::assertSame(['' => 12, 'md' => 6, 'xxl' => 3], (new Span(xs: 12, md: 6, xxl: 3))->widths);
        self::assertSame(['lg' => 4], Span::from(Breakpoint::Lg, 4)->widths);
        self::assertSame(['' => 4], Span::from(Breakpoint::Xs, 4)->widths);
    }

    public function testNeedsAWidth(): void
    {
        $this->expectException(LogicException::class);
        new Span();
    }

    public function testAWidthIsOneToTwelve(): void
    {
        $this->expectExceptionMessage('not 13 (at "lg")');
        new Span(lg: 13);
    }

    public function testANumberIsFromTheBreakpointOfTheColumnOrTheRow(): void
    {
        self::assertNull(new Column()->span());
        self::assertSame(['md' => 8], new Column(8)->span()?->widths);
        self::assertSame(['lg' => 4], new Row([], Breakpoint::Lg)->spanOf(new Field('sku', 4))?->widths);
        self::assertSame(['sm' => 6], new Row([])->spanOf(new Field('sku', new Span(sm: 6)))?->widths);
        self::assertNull(new Row([])->spanOf(new Field('sku')));
    }

    public function testClassesOfAFieldOnlyInARow(): void
    {
        CrudLayout::validate([new Row([new Field('sku', class: 'x')])]);

        $this->expectExceptionMessage('The field "sku" has a width or classes of its own');
        CrudLayout::validate([new Fieldset('basics', [new Field('sku', class: 'x')])]);
    }

    /** @return iterable<string, array{string, Span|null, Breakpoint, int, string}> */
    public static function classes(): iterable
    {
        yield 'bootstrap5, an equal share' => ['bootstrap5', null, Breakpoint::Md, 3, 'col-md'];
        yield 'bootstrap5, an equal share always' => ['bootstrap5', null, Breakpoint::Xs, 3, 'col'];
        yield 'bootstrap5, a span' => [
            'bootstrap5',
            new Span(xs: 12, sm: 6, xxl: 4),
            Breakpoint::Md,
            2,
            'col-12 col-sm-6 col-xxl-4',
        ];
        yield 'tailwind, an equal share' => ['tailwind', null, Breakpoint::Md, 3, 'col-span-12 md:col-span-4'];
        yield 'tailwind, an equal share always' => ['tailwind', null, Breakpoint::Xs, 2, 'col-span-6'];
        yield 'tailwind, a span' => [
            'tailwind',
            new Span(sm: 6, xxl: 4),
            Breakpoint::Md,
            2,
            'col-span-12 sm:col-span-6 2xl:col-span-4',
        ];
        yield 'tailwind, a span from xs' => [
            'tailwind',
            new Span(xs: 6, lg: 3),
            Breakpoint::Md,
            2,
            'col-span-6 lg:col-span-3',
        ];
    }

    #[DataProvider('classes')]
    public function testEveryThemeDrawsItWithItsOwnGrid(
        string $theme,
        ?Span $span,
        Breakpoint $breakpoint,
        int $count,
        string $classes,
    ): void {
        $twig = new Environment(
            new FilesystemLoader(dirname(__DIR__, 3) . '/templates/' . $theme),
            ['strict_variables' => true],
        );
        $twig->addFilter(new TwigFilter('trans', static fn (mixed $value): mixed => $value));
        foreach (['crud_layout', 'crud_layout_part', 'crud_value', 'form_row', 'component', 'crud_icon'] as $function) {
            $twig->addFunction(new TwigFunction($function, static fn (): string => ''));
        }

        $html = $twig->createTemplate(
            "{% import 'crud/layout.html.twig' as layout %}{{ layout.col(span, breakpoint, count) }}",
        )
            ->render(['span' => $span, 'breakpoint' => $breakpoint, 'count' => $count]);

        self::assertSame($classes, $html);
    }
}
