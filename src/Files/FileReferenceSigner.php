<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Signs the references the server gives the browser (an upload, a file picked from a library), so a field takes back
 * only those: one typed into the hidden input of a field by hand - another's private file - has no signature and is
 * refused, unless the record had it already.
 */
final readonly class FileReferenceSigner
{
    public function __construct(
        #[\SensitiveParameter]
        private string $secret,
    ) {
    }

    public function sign(FileReference $reference): string
    {
        return rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            'byfareska_crud.file|' . $reference,
            $this->secret,
            true,
        )), '+/', '-_'), '=');
    }

    public function verify(FileReference $reference, string $signature): bool
    {
        return $signature !== '' && hash_equals($this->sign($reference), $signature);
    }
}
