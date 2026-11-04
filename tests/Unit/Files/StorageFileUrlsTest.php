<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRoutes;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\StorageFileUrls;
use Byfareska\Crud\Panel\Scope;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The addresses of files by the "url" of their storage.
 */
final class StorageFileUrlsTest extends TestCase
{
    private UriSigner $signer;

    private StorageFileUrls $urls;

    protected function setUp(): void
    {
        $storage = new FlysystemFileStorage(new Filesystem(new InMemoryFilesystemAdapter()));
        $routes = new RouteCollection();
        $routes->add(FileRoutes::download('admin'), new Route('/admin/_files/download'));
        $requests = new RequestStack();
        $requests->push(new Request(attributes: [Scope::ATTRIBUTE => 'admin']));
        $this->signer = new UriSigner('secret');
        $this->urls = new StorageFileUrls(
            new FileStorages(
                [
                    'public' => ['public_url' => 'https://cdn.example.com/media/'],
                    'private' => ['url' => 'signed', 'ttl' => 3600],
                    'vault' => ['url' => 'none'],
                ],
                new ServiceLocator(array_fill_keys(
                    ['public', 'private', 'vault'],
                    static fn (): FlysystemFileStorage => $storage,
                )),
            ),
            new ServiceLocator([]),
            new UrlGenerator($routes, new RequestContext(host: 'panel.example.com')),
            $this->signer,
            $requests,
        );
    }

    public function testAPublicFileIsUnderTheAddressOfItsStorage(): void
    {
        $reference = FileReference::parse('public://ab/cd/kot na płocie.jpg');

        $url = $this->urls->url($reference);

        self::assertSame('https://cdn.example.com/media/ab/cd/kot%20na%20p%C5%82ocie.jpg', $url);
        self::assertEquals($reference, $this->urls->referenceOf($url));
    }

    public function testAPrivateFileHasASignedAddressThatWorksForItsTtl(): void
    {
        $url = $this->urls->url(FileReference::parse('private://ab/cd/invoice.pdf'));

        self::assertNotNull($url);
        self::assertStringStartsWith('http://panel.example.com/admin/_files/download?', $url);
        self::assertTrue($this->signer->check($url));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('private://ab/cd/invoice.pdf', $query['ref'] ?? null);
        self::assertIsString($query['_expiration'] ?? null);
        self::assertGreaterThanOrEqual(time() + 3600, (int) $query['_expiration']);
        self::assertSame($url, $this->urls->url(FileReference::parse('private://ab/cd/invoice.pdf')), 'Kept a while.');
    }

    public function testASignedAddressStaysTheSameForAQuarterOfItsTtl(): void
    {
        $start = 1_000_000_800; // A quarter of an hour begins: 1 111 112 x 900.
        self::assertSame(0, $start % 900);

        $expires = StorageFileUrls::expiresAt($start + 1, 3600);

        self::assertSame($start + 900 + 3600, $expires);
        self::assertSame($expires, StorageFileUrls::expiresAt($start + 900, 3600));
        self::assertGreaterThan($expires, StorageFileUrls::expiresAt($start + 901, 3600));
        foreach ([1, 7, 60, 3600, 86400] as $ttl) {
            foreach ([0, 1, 59, 1_234_567] as $now) {
                $at = StorageFileUrls::expiresAt($now, $ttl);
                self::assertGreaterThanOrEqual($now + $ttl, $at, 'Never shorter than its ttl.');
                self::assertLessThan($now + $ttl + max(1, intdiv($ttl, 4)), $at, 'At most a quarter longer.');
            }
        }
    }

    public function testNoAddressesOfSomeFiles(): void
    {
        self::assertNull($this->urls->url(FileReference::parse('vault://a.pdf')));
        self::assertNull($this->urls->url(FileReference::parse('avatars://a.png')), 'No such storage.');
        self::assertSame(
            'https://example.com/a.png',
            $this->urls->url(FileReference::parse('https://example.com/a.png')),
        );
    }
}
