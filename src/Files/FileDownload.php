<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The response with a file of a storage, for its signed address: streamed by PHP, or sent by the web server when the
 * storage has an "offload". Only an image or a PDF is opened in the browser; anything else is downloaded, never run
 * as a page of the panel.
 */
final readonly class FileDownload
{
    public function __construct(
        private FileStorages $storages,
        private FileLibraries $libraries,
    ) {
    }

    /**
     * @param int $maxAge for how many seconds the browser may keep it: as long as its address works
     * @return Response|null null = no such file
     */
    public function response(FileReference $reference, int $maxAge): ?Response
    {
        if ($reference->isExternal() || !$this->storages->has($reference->storage)) {
            return null;
        }
        $storage = $this->storages->storage($reference->storage);
        if (!$storage->exists($reference->path)) {
            return null;
        }

        $file = $this->libraries->describe($reference);
        $mimeType = $file->mimeType ?? $storage->entry($reference->path)->mimeType ?? 'application/octet-stream';
        $offload = $this->storages->settings($reference->storage)->offload;
        if ($offload instanceof FileOffload) {
            $response = new Response();
            $response->headers->set($offload->header->value, $offload->value($reference->path));
        } else {
            $response = new StreamedResponse(static function () use ($storage, $reference): void {
                $stream = $storage->readStream($reference->path);
                $out = fopen('php://output', 'wb');
                if ($out !== false) {
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                }
                if (is_resource($stream)) {
                    fclose($stream);
                }
            });
        }

        $inline = in_array($mimeType, UploadProfile::IMAGES, true) || $mimeType === 'application/pdf';
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $file->name,
            $this->asciiName($file->name, $reference),
        ));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox");
        $response->setPrivate();
        $response->setMaxAge(max(0, $maxAge));

        return $response;
    }

    /** The name for a browser that knows no UTF-8 in the header. */
    private function asciiName(string $name, FileReference $reference): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7e]|[%\/\\\\"]/', '_', $name);

        return $ascii === '' ? $reference->basename() : $ascii;
    }
}
