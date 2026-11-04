<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileDownload;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file of its signed address: streamed by PHP, or sent by the web server.
 */
final class FileDownloadTest extends TestCase
{
    private Filesystem $filesystem;

    private FileDownload $download;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new FlysystemFileStorage($this->filesystem);
        $this->download = new FileDownload(
            new FileStorages(
                [
                    'private' => ['url' => 'signed'],
                    'nginx' => [
                        'url' => 'signed',
                        'offload' => ['header' => 'X-Accel-Redirect', 'prefix' => '/_protected'],
                    ],
                    'apache' => [
                        'url' => 'signed',
                        'offload' => ['header' => 'X-Sendfile', 'prefix' => '/var/app/private/'],
                    ],
                ],
                new ServiceLocator(array_fill_keys(
                    ['private', 'nginx', 'apache'],
                    static fn (): FlysystemFileStorage => $storage,
                )),
            ),
            new FileLibraries(new ServiceLocator([])),
        );
        $this->filesystem->write('ab/cd/raport roczny.pdf', "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n");
        $this->filesystem->write('ab/cd/page.html', '<script>alert(1)</script>');
    }

    public function testPhpStreamsTheFile(): void
    {
        $response = $this->download->response(FileReference::parse('private://ab/cd/raport roczny.pdf'), 900);

        self::assertInstanceOf(StreamedResponse::class, $response);
        ob_start();
        $response->sendContent();
        self::assertSame("%PDF-1.4\n%\xe2\xe3\xcf\xd3\n", ob_get_clean());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('900', $response->headers->getCacheControlDirective('max-age'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testNginxSendsTheFile(): void
    {
        $response = $this->download->response(FileReference::parse('nginx://ab/cd/raport roczny.pdf'), 900);

        self::assertNotNull($response);
        self::assertNotInstanceOf(StreamedResponse::class, $response);
        self::assertSame('', $response->getContent());
        self::assertSame('/_protected/ab/cd/raport%20roczny.pdf', $response->headers->get('X-Accel-Redirect'));
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testApacheSendsTheFileFromTheDisk(): void
    {
        $response = $this->download->response(FileReference::parse('apache://ab/cd/raport roczny.pdf'), 900);

        self::assertNotNull($response);
        self::assertSame('/var/app/private/ab/cd/raport roczny.pdf', $response->headers->get('X-Sendfile'));
    }

    public function testAPageIsDownloadedNeverOpened(): void
    {
        $response = $this->download->response(FileReference::parse('private://ab/cd/page.html'), 900);

        self::assertNotNull($response);
        self::assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testNoSuchFile(): void
    {
        self::assertNull($this->download->response(FileReference::parse('private://no/such.pdf'), 900));
        self::assertNull($this->download->response(FileReference::parse('avatars://a.pdf'), 900));
    }
}
