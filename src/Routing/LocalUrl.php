<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * An address a request names (the header X-Live-Url, the Referer) that the browser may be sent to: a path of this
 * application ("/products/7?tab=prices") - or an address of its own host, made a path - never another site. Anybody
 * can send any header: "//evil.example", "/\evil.example", "https://evil.example/" or a path with a tab or a line
 * break in it (which a browser drops, making "//") are not taken.
 */
final class LocalUrl
{
    /**
     * The path; null = the address is not one of this application.
     */
    public static function of(?string $url, Request $request): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $origin = $request->getSchemeAndHttpHost();
        if (str_starts_with($url, $origin . '/')) {
            $url = substr($url, strlen($origin));
        }

        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && preg_match('/[\x00-\x1F\x7F\\\\]/', $url) !== 1
            ? $url
            : null;
    }
}
