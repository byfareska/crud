<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Tests\Functional\App\Entity\AccountStatus;
use Byfareska\Crud\Tests\Functional\App\Entity\ContactChannel;
use Byfareska\Crud\Tests\Functional\App\Entity\Contract;
use Byfareska\Crud\Tests\Functional\App\Entity\Customer;
use Byfareska\Crud\Tests\Functional\App\Entity\Tariff;
use Byfareska\Crud\Tests\Functional\App\TestKernel;
use Dom\HTMLDocument;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Tests on the application of tests/Functional/App: a real kernel, FrameworkBundle and Doctrine, booted in a cache
 * directory of its own and removed afterwards.
 */
abstract class PanelTestCase extends TestCase
{
    /** @var array<string, TestKernel> by default locale, booted once a class */
    private static array $kernels = [];

    private static ?string $cacheRoot = null;

    private ?TestKernel $kernel = null;

    /** The exception handler before the test: FrameworkBundle sets its own on boot and on requests. */
    private mixed $exceptionHandler = null;

    public static function tearDownAfterClass(): void
    {
        foreach (self::$kernels as $kernel) {
            $kernel->shutdown();
        }
        self::$kernels = [];
        if (self::$cacheRoot !== null) {
            new Filesystem()->remove(self::$cacheRoot);
            self::$cacheRoot = null;
        }
        gc_collect_cycles();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->exceptionHandler = $this->currentExceptionHandler();
    }

    protected function tearDown(): void
    {
        if ($this->kernel instanceof TestKernel) {
            $resetter = $this->kernel->getContainer()->get('services_resetter');
            assert($resetter instanceof ResetInterface);
            $resetter->reset();
            $this->kernel = null;
        }
        // The handlers the kernel set during the test are taken away again, as KernelTestCase does: PHPUnit counts a
        // test that leaves one as risky.
        for ($left = 10; $left > 0 && $this->currentExceptionHandler() !== $this->exceptionHandler; --$left) {
            restore_exception_handler();
        }
        parent::tearDown();
    }

    private function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    protected function boot(string $defaultLocale = 'pl'): ContainerInterface
    {
        self::$cacheRoot ??= sys_get_temp_dir() . '/byfareska-crud-functional-' . bin2hex(random_bytes(6));
        if (!isset(self::$kernels[$defaultLocale])) {
            $kernel = new TestKernel($defaultLocale, self::$cacheRoot . '/' . $defaultLocale);
            $kernel->boot();
            self::$kernels[$defaultLocale] = $kernel;
        }
        $this->kernel = self::$kernels[$defaultLocale];

        return $this->container();
    }

    /** The schema, anew, and a customer with a contract. */
    protected function seed(): Contract
    {
        $entities = $this->entities();
        $schema = new SchemaTool($entities);
        $metadata = $entities->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $customer = new Customer(
            'Ewa Lisowska',
            '1187234',
            AccountStatus::Active,
            [ContactChannel::Phone, ContactChannel::Letter],
            Tariff::G12,
        );
        $contract = new Contract('1187234001', $customer);
        $entities->persist($customer);
        $entities->persist($contract);
        $entities->flush();
        $entities->clear();

        return $contract;
    }

    protected function entities(): EntityManagerInterface
    {
        $entities = $this->container()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entities);

        return $entities;
    }

    protected function request(string $uri, string $locale = 'pl'): string
    {
        $request = Request::create($uri);
        $request->setLocale($locale);
        $request->attributes->set('_locale', $locale);
        $response = $this->handle($request);
        $content = (string) $response->getContent();
        self::assertSame(200, $response->getStatusCode(), $content);

        return $content;
    }

    /** The response of the application, whatever its status. */
    protected function handle(Request $request): Response
    {
        assert($this->kernel instanceof TestKernel);
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        return $response;
    }

    /**
     * The texts of the elements of a page a CSS selector finds, their white space squeezed.
     *
     * @return list<string>
     */
    protected static function texts(string $html, string $selector): array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $texts = [];
        foreach ($document->querySelectorAll($selector) as $element) {
            $texts[] = trim((string) preg_replace('/\s+/u', ' ', (string) $element->textContent));
        }

        return $texts;
    }

    protected function container(): ContainerInterface
    {
        assert($this->kernel instanceof TestKernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        assert($container instanceof ContainerInterface);

        return $container;
    }
}
