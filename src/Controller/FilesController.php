<?php

declare(strict_types=1);

namespace Byfareska\Crud\Controller;

use Byfareska\Crud\Files\BrowserFiles;
use Byfareska\Crud\Files\FileDownload;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRefused;
use Byfareska\Crud\Files\FileStorageFailed;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\FileUploader;
use Byfareska\Crud\Files\FileUrls;
use Byfareska\Crud\Files\Image\ImageVariantFailed;
use Byfareska\Crud\Files\Image\ImageVariants;
use Byfareska\Crud\Files\Image\VariantStrategy;
use Byfareska\Crud\Files\UploadProfile;
use Byfareska\Crud\Files\UrlMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The files of a panel: an upload of a field (to the storage of the profile its address was signed with), the
 * download of a file of a storage without addresses of its own (UrlMode::Signed) - or of a size of its image - and a
 * size of an image made when it is first asked for (VariantStrategy::Lazy). The routes come from
 * {@see \Byfareska\Crud\Routing\CrudRouteLoader}, in every panel when byfareska_crud.files is enabled: in the
 * firewall of the panel, for those the panel - and the storage's "role" - is for.
 *
 * An upload is taken only from a script of the page (the header X-Requested-With, which a page of another site cannot
 * send without the browser asking first), and only as the signed address says: the browser cannot choose another
 * storage, kind or size.
 */
#[AsController]
final readonly class FilesController
{
    /** For how long the browser keeps where a size of an image is (a week). */
    public const int VARIANT_MAX_AGE = 604800;

    public function __construct(
        private UriSigner $uriSigner,
        private FileUploader $uploader,
        private FileStorages $storages,
        private BrowserFiles $browser,
        private FileDownload $download,
        private TranslatorInterface $translator,
        private FileUrls $urls,
        private ?LoggerInterface $logger = null,
        private ?ImageVariants $variants = null,
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
            throw new BadRequestHttpException('Only a field of the page may upload.');
        }
        if (!$this->uriSigner->checkRequest($request)) {
            throw new AccessDeniedHttpException('The address of the upload is not signed, or no longer works.');
        }
        $profile = UploadProfile::fromQuery($request->query->all());
        if (!$this->browser->mayUse($profile->storage)) {
            throw new AccessDeniedHttpException(sprintf('The storage "%s" is not for you.', $profile->storage));
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            // Nothing, or more than PHP's post_max_size: PHP leaves the body out.
            return $this->refused(FileRefused::tooBig((int) UploadedFile::getMaxFilesize()));
        }

        try {
            $stored = $this->uploader->upload($file, $profile);
        } catch (FileRefused $refused) {
            return $this->refused($refused);
        } catch (FileStorageFailed $failure) {
            $this->logger?->error('An upload to the storage "{storage}" failed: {message}', [
                'storage' => $profile->storage,
                'message' => $failure->getMessage(),
                'exception' => $failure,
            ]);

            return $this->refused(new FileRefused('file.refused.failed'), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse($this->browser->json($stored), Response::HTTP_CREATED);
    }

    public function download(Request $request): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            throw new AccessDeniedHttpException('The address of the file is not signed, or no longer works.');
        }
        $reference = FileReference::tryParse($request->query->getString('ref'));
        if (
            !$reference instanceof FileReference
            || $reference->isExternal()
            || !$this->storages->has($reference->storage)
            || $this->storages->settings($reference->storage)->url !== UrlMode::Signed
        ) {
            throw new NotFoundHttpException('No such file.');
        }
        if (!$this->browser->mayUse($reference->storage)) {
            throw new AccessDeniedHttpException(sprintf('The storage "%s" is not for you.', $reference->storage));
        }

        // Kept by the browser for as long as its address works.
        $expires = $request->query->getInt('_expiration');
        $maxAge = $expires > 0 ? $expires - time() : $this->storages->settings($reference->storage)->ttl;

        $variant = $request->query->has('variant') ? $request->query->getString('variant') : null;

        return $this->download->response($reference, $maxAge, $variant)
            ?? throw new NotFoundHttpException('No such file.');
    }

    /**
     * A size of an image of a storage that makes them when first asked for: made unless it is there, then the browser
     * is sent to its address - kept by the browser, the address of this route staying the same. An image that has no
     * such size, or one that cannot be made, sends it to the image.
     */
    public function variant(Request $request): RedirectResponse
    {
        if (!$this->uriSigner->checkRequest($request)) {
            throw new AccessDeniedHttpException('The address of the image is not signed.');
        }
        $reference = FileReference::tryParse($request->query->getString('ref'));
        $name = $request->query->getString('variant');
        if (
            !$reference instanceof FileReference
            || !$this->variants instanceof ImageVariants
            || !$this->variants->has($name)
            || $this->variants->strategy($reference->storage) !== VariantStrategy::Lazy
        ) {
            throw new NotFoundHttpException('No such image.');
        }
        if (!$this->browser->mayUse($reference->storage)) {
            throw new AccessDeniedHttpException(sprintf('The storage "%s" is not for you.', $reference->storage));
        }
        $storage = $this->storages->storage($reference->storage);
        if (!$storage->exists($reference->path)) {
            throw new NotFoundHttpException('No such image.');
        }

        $target = $reference;
        $maxAge = self::VARIANT_MAX_AGE;
        if ($this->variants->applies($reference, $name)) {
            try {
                $target = $this->variants->make($reference, $name);
            } catch (ImageVariantFailed | FileStorageFailed $failure) {
                $this->variants->failed($reference, $name, $failure);
                // Asked again a while later: the image may be replaced, the configuration changed.
                $maxAge = 300;
            }
        }
        $url = $this->urls->url($target) ?? throw new NotFoundHttpException('The image has no address.');
        $settings = $this->storages->settings($reference->storage);
        $response = new RedirectResponse($url);
        if ($settings->url === UrlMode::Public) {
            $response->setPublic();
            $response->setMaxAge($maxAge);
        } else {
            // The address of a temporary file works for "ttl": the browser keeps it for less.
            $response->setPrivate();
            $response->setMaxAge(min($maxAge, intdiv($settings->ttl, 2)));
        }

        return $response;
    }

    private function refused(FileRefused $refused, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(
            ['error' => $this->translator->trans($refused->key, $refused->parameters)],
            $status,
        );
    }
}
