<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\BrowserFiles;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileReferenceSigner;
use Byfareska\Crud\Files\FileRoutes;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Files\Form\FileTypeGuesser;
use Byfareska\Crud\Files\NoFileLibrary;
use Byfareska\Crud\Files\RichTextFiles;
use Byfareska\Crud\Files\StorageFileLibrary;
use Byfareska\Crud\Files\StorageFileUrls;
use Byfareska\Crud\Files\SubmittedFiles;
use Byfareska\Crud\Form\CrudRichTextType;
use Byfareska\Crud\Form\RichTextTypeGuesser;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;

/**
 * The files of a panel "admin" for tests: the storage "public" in memory with a library listing it, "private" (signed
 * addresses, for ROLE_ADMIN), "archive" (no library) - and what the fields of files are made of.
 */
final readonly class FilesKit
{
    public Filesystem $filesystem;

    public FileStorages $storages;

    public FileLibraries $libraries;

    public StorageFileLibrary $library;

    public FileReferenceSigner $signer;

    public UriSigner $uriSigner;

    public BrowserFiles $browser;

    public StorageFileUrls $urls;

    public SubmittedFiles $submitted;

    public FileRecordMappers $mappers;

    public RichTextFiles $richText;

    /**
     * @param list<string> $roles of the one acting
     */
    public function __construct(array $roles = [], ?FileLibrary $library = null)
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new FlysystemFileStorage($this->filesystem);
        $this->storages = new FileStorages(
            [
                'public' => ['public_url' => '/uploads'],
                'private' => ['url' => 'signed', 'role' => 'ROLE_ADMIN'],
                'archive' => ['upload' => false],
            ],
            new ServiceLocator(array_fill_keys(
                ['public', 'private', 'archive'],
                static fn (): FlysystemFileStorage => $storage,
            )),
        );
        $this->library = new StorageFileLibrary($storage, 'public');
        $own = $library ?? $this->library;
        $this->libraries = new FileLibraries(new ServiceLocator([
            'public' => static fn (): FileLibrary => $own,
            'private' => static fn (): StorageFileLibrary => new StorageFileLibrary($storage, 'private'),
            'archive' => static fn (): NoFileLibrary => new NoFileLibrary(),
        ]));

        $routes = new RouteCollection();
        $routes->add(FileRoutes::upload('admin'), new Route('/admin/_files', methods: ['POST']));
        $routes->add(FileRoutes::download('admin'), new Route('/admin/_files/download'));
        $router = new UrlGenerator($routes, new RequestContext());
        $requests = new RequestStack();
        $request = Request::create('http://localhost/admin/products/new');
        $request->attributes->set(Scope::ATTRIBUTE, 'admin');
        $requests->push($request);
        $scopes = new Scopes(
            ['admin' => [
                'label' => null,
                'icon' => 'shield',
                'layout' => null,
                'theme' => null,
                'entries' => [],
                'role' => null,
                'dashboard' => false,
                'home' => null,
            ]],
            'layout.html.twig',
            'daisyui',
            ['app'],
            $requests,
            $this->actor($roles),
            new CurrentCrud($requests),
        );

        $this->signer = new FileReferenceSigner('secret');
        $this->uriSigner = new UriSigner('secret');
        $this->urls = new StorageFileUrls(
            $this->storages,
            new ServiceLocator([]),
            $router,
            $this->uriSigner,
            $requests,
        );
        $this->browser = new BrowserFiles(
            $this->urls,
            $this->signer,
            $this->uriSigner,
            $router,
            $this->storages,
            $scopes,
        );
        $this->submitted = new SubmittedFiles();
        $this->mappers = new FileRecordMappers();
        $this->richText = new RichTextFiles(
            $this->urls,
            $this->storages,
            $this->browser,
            $this->libraries,
            $this->submitted,
            $requests,
        );
    }

    /**
     * @param array{editor?: string, options?: array<string, mixed>, files?: bool} $richText byfareska_crud.rich_text
     */
    public function forms(array $richText = []): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            // As in the application: the message of a refused file is the field's own.
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new CrudFileType(
                $this->storages,
                $this->libraries,
                $this->browser,
                $this->signer,
                $this->mappers,
                $this->submitted,
                new IdentityTranslator(),
            ))
            ->addType(new CrudRichTextType(
                $richText + ['editor' => 'tinymce', 'options' => [], 'files' => false],
                $this->richText,
            ))
            ->addTypeGuesser(new FileTypeGuesser())
            ->addTypeGuesser(new RichTextTypeGuesser())
            ->getFormFactory();
    }

    /** A file written to the storage, as an upload leaves it; an image of PNG by default (told by its bytes). */
    public function put(string $path, ?string $contents = null): FileReference
    {
        $this->filesystem->write($path, $contents ?? "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0");

        return FileReference::parse('public://' . $path);
    }

    /**
     * What the browser sends for files: the JSON of the hidden input.
     *
     * @param list<array<string, mixed>> $files
     */
    public static function json(array $files): string
    {
        return json_encode($files, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $roles
     */
    private function actor(array $roles): Actor
    {
        return new readonly class ($roles) implements Actor {
            /** @param list<string> $roles */
            public function __construct(private array $roles)
            {
            }

            public function id(): string
            {
                return '1';
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return in_array($attribute, $this->roles, true);
            }
        };
    }
}
