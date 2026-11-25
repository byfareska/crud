<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Signs the references the server gives the browser (an upload, a file picked from a library), so a field takes back
 * only those: one typed into the hidden input of a field by hand - another's private file - has no signature and is
 * refused, unless the record had it already.
 *
 * Signed with the secret of the application (kernel.secret), which must not be empty: with none, anybody could sign
 * any reference - as with Symfony's UriSigner, which refuses one too.
 */
final readonly class FileReferenceSigner
{
    /**
     * @throws \InvalidArgumentException an empty secret
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $secret,
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException(
                'The references of files are signed with the secret of the application (kernel.secret): set one.',
            );
        }
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
