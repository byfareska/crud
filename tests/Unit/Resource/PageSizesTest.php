<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\DefaultPageSizes;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use PHPUnit\Framework\TestCase;

final class PageSizesTest extends TestCase
{
    public function testTheConfigurationDecidesForAResourceThatDoesNotSay(): void
    {
        $sizes = new DefaultPageSizes([5, 15, 30], 15);
        $crud = $this->crud();

        self::assertSame([5, 15, 30], $sizes->of($crud));
        self::assertSame(15, $sizes->defaultOf($crud));
    }

    public function testTheResourceSaysForItself(): void
    {
        $sizes = new DefaultPageSizes([5, 15, 30], 15);
        $crud = $this->crud([10, 20], 20);

        self::assertSame([10, 20], $sizes->of($crud));
        self::assertSame(20, $sizes->defaultOf($crud));
    }

    public function testTheConfiguredSizeCountsWhenTheResourceOffersIt(): void
    {
        $sizes = new DefaultPageSizes([5, 15, 30], 15);

        self::assertSame(15, $sizes->defaultOf($this->crud([10, 15])));
        self::assertSame(10, $sizes->defaultOf($this->crud([10, 20])));
    }

    public function testWithoutADefaultTheFirstIsShown(): void
    {
        self::assertSame(5, new DefaultPageSizes([5, 15])->defaultOf($this->crud()));
    }

    /**
     * @param non-empty-list<int>|null $perPage
     */
    private function crud(?array $perPage = null, ?int $default = null): AsCrud
    {
        return new AsCrud(
            'thing',
            '/things',
            'box',
            list: ListThingsDto::class,
            perPage: $perPage,
            defaultPerPage: $default,
        );
    }
}
