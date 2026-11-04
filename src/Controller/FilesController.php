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
use Byfareska\Crud\Files\UploadProfile;
use Byfareska\Crud\Files\UrlMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The files of a panel: an upload of a field (to the storage of the profile its address was signed with) and the
 * download of a file of a storage without addresses of its own (UrlMode::Signed). The routes come from
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
    public function __construct(
        private UriSigner $uriSigner,
        private FileUploader $uploader,
        private FileStorages $storages,
        private BrowserFiles $browser,
        private FileDownload $download,
        private TranslatorInterface $translator,
        private ?LoggerInterface $logger = null,
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

        return $this->download->response($reference, $maxAge) ?? throw new NotFoundHttpException('No such file.');
    }

    private function refused(FileRefused $refused, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(
            ['error' => $this->translator->trans($refused->key, $refused->parameters)],
            $status,
        );
    }
}
