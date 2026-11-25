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
        // What browsers do to an address before they follow it (WHATWG URL): drop tabs and line breaks anywhere, trim
        // control characters and spaces at the ends, take "\" for "/" - and nothing else.
        yield 'its own host, then without a scheme' => ['https://shop.example//evil.example', null];
        yield 'its own host, then a backslash' => ['https://shop.example/\\evil.example', null];
        yield 'its own host as the user of another' => ['https://shop.example@evil.example/', null];
        yield 'its own host on another port' => ['https://shop.example:8443/products', null];
        yield 'a backslash first' => ['\\\\evil.example', null];
        yield 'a backslash, then a slash' => ['/\\/evil.example', null];
        yield 'a line break a browser drops' => ["/\n/evil.example", null];
        yield 'a carriage return a browser drops' => ["/\r/evil.example", null];
        yield 'a space a browser trims' => [' //evil.example', null];
        yield 'a tab a browser trims' => ["\t//evil.example", null];
        yield 'a vertical tab' => ["/\v/evil.example", null];
        yield 'a null byte' => ["/\0/evil.example", null];
        yield 'a scheme with one slash' => ['https:/evil.example', null];
        yield 'a scheme without slashes' => ['https:evil.example', null];
        yield 'a scheme of data' => ['data:text/html,<script>alert(1)</script>', null];
        // Encoded, a tab or a backslash stays a part of the path: the browser does not decode it.
        yield 'an encoded tab' => ['/%09/evil.example', '/%09/evil.example'];
        yield 'an encoded backslash' => ['/%5Cevil.example', '/%5Cevil.example'];
        yield 'an encoded slash' => ['/%2F/evil.example', '/%2F/evil.example'];
        yield 'a dot segment' => ['/./products', '/./products'];
        yield 'a dot segment before two slashes' => ['/.//evil.example', '/.//evil.example'];
        yield 'a slash of another script' => ["/\u{FF0F}evil.example", "/\u{FF0F}evil.example"];
    }

    #[DataProvider('addresses')]
    public function testOnlyAPathOfTheApplicationIsTaken(?string $url, ?string $path): void
    {
        self::assertSame($path, LocalUrl::of($url, Request::create('https://shop.example/_crud/Crud:Run/run')));
    }
}
