<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

use Byfareska\Crud\Routing\LocalUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Only an address of the application itself is one to send the browser back to: a header says anything.
 */
final class LocalUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function addresses(): iterable
    {
        yield 'a path' => ['/products/7?tab=prices', '/products/7?tab=prices'];
        yield 'the root' => ['/', '/'];
        yield 'of its own host' => ['https://shop.example/products/7?tab=prices', '/products/7?tab=prices'];
        yield 'none' => [null, null];
        yield 'empty' => ['', null];
        yield 'another site' => ['https://evil.example/products', null];
        yield 'a host that starts like its own' => ['https://shop.example.evil.example/products', null];
        yield 'its own host on another scheme' => ['http://shop.example/products', null];
        yield 'without a scheme' => ['//evil.example/products', null];
        yield 'a backslash a browser takes for a slash' => ['/\\evil.example', null];
        yield 'a tab a browser drops' => ["/\t/evil.example", null];
        yield 'a line break' => ["/products\r\nSet-Cookie: a=b", null];
        yield 'relative' => ['products', null];
        yield 'a script' => ['javascript:alert(1)', null];
    }

    #[DataProvider('addresses')]
    public function testOnlyAPathOfTheApplicationIsTaken(?string $url, ?string $path): void
    {
        self::assertSame($path, LocalUrl::of($url, Request::create('https://shop.example/_crud/Crud:Run/run')));
    }
}
