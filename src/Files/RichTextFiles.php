<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Files\Form\CrudFileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * The files of fields of rich text (CrudRichTextType), with byfareska_crud.files enabled: the HTML keeps the addresses
 * of its images and links, not references - so only storages with lasting public addresses are uploaded to, and the
 * files a text uses are read back from its addresses (FileUrls::referenceOf()) for the index of usages.
 */
final readonly class RichTextFiles
{
    /** The attribute of the config of a field of rich text with this service (reindexing reads the fields with it). */
    public const string ATTRIBUTE = 'crud_rich_text_files';

    /** The attributes of HTML holding an address of a file. */
    private const string ADDRESSES = '/\s(src|href|poster|srcset)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

    public function __construct(
        private FileUrls $urls,
        private FileStorages $storages,
        private BrowserFiles $browser,
        private FileLibraries $libraries,
        private SubmittedFiles $submittedFiles,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The files of the storages an HTML shows or links to, each once, in the order they come in.
     *
     * @return list<FileReference>
     */
    public function references(string $html): array
    {
        if ($html === '' || preg_match_all(self::ADDRESSES, $html, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }
        $references = [];
        foreach ($matches as $match) {
            $value = html_entity_decode(
                ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''),
                ENT_QUOTES | ENT_HTML5,
            );
            $addresses = strtolower($match[1]) === 'srcset'
                ? array_map(
                    static fn (string $candidate): string => explode(' ', trim($candidate))[0],
                    explode(',', $value),
                )
                : [$value];
            foreach ($addresses as $address) {
                $reference = $this->referenceOf(trim($address));
                if ($reference instanceof FileReference) {
                    $references[(string) $reference] = $reference;
                }
            }
        }

        return array_values($references);
    }

    /**
     * What a submitted field holds, told to the index of usages once the record is saved.
     *
     * @param FormInterface<mixed> $form
     */
    public function record(FormInterface $form): void
    {
        $html = $form->getData();
        $this->submittedFiles->record(CrudFileType::pathOf($form), $this->references(is_string($html) ? $html : ''));
    }

    /**
     * Where a field uploads to and picks from: its "files" option (storage, accept, max_size, browse) - of storages
     * with lasting public addresses only.
     *
     * @param array<string, mixed> $files
     */
    public function profile(array $files): UploadProfile
    {
        $strings = static fn (mixed $list): array => is_array($list)
            ? array_values(array_filter($list, is_string(...)))
            : [];
        $profile = new UploadProfile(
            is_string($files['storage'] ?? null) ? $files['storage'] : $this->storages->default(),
            UploadProfile::expand($strings($files['accept'] ?? [])),
            is_int($files['max_size'] ?? null) ? $files['max_size'] : null,
            $strings($files['browse'] ?? []),
        );
        foreach ($profile->storages() as $storage) {
            if (!$this->storages->has($storage)) {
                throw new InvalidOptionsException(sprintf('A field of rich text names no storage "%s".', $storage));
            }
            if ($this->storages->settings($storage)->url !== UrlMode::Public) {
                throw new InvalidOptionsException(sprintf(
                    'A field of rich text keeps the addresses of its files in its HTML: the storage "%s" has none '
                    . 'that last (its "url" is not "public").',
                    $storage,
                ));
            }
        }

        return $profile;
    }

    /**
     * What the browser of a field is given: where it uploads, and the tokens of the window of the library - of every
     * file the field takes, and of its images only (what an image of the editor is picked from); null = none.
     *
     * @return array{upload_url: string|null, library_token: string|null, image_token: string|null}
     */
    public function view(UploadProfile $profile): array
    {
        $browse = array_values(array_filter(
            $profile->storages(),
            fn (string $storage): bool => $this->browser->mayUse($storage)
                && $this->libraries->can($storage, LibraryCapability::Browse),
        ));
        if ($browse === []) {
            return [
                'upload_url' => $this->browser->uploadUrl($profile),
                'library_token' => null,
                'image_token' => null,
            ];
        }
        $images = $profile->accept === []
            ? UploadProfile::IMAGES
            : array_values(array_filter(
                $profile->accept,
                static fn (string $kind): bool => in_array($kind, UploadProfile::IMAGES, true) || $kind === 'image/*',
            ));

        return [
            'upload_url' => $this->browser->uploadUrl($profile),
            'library_token' => $this->browser->libraryToken(
                new UploadProfile($profile->storage, $profile->accept, null, $browse),
                false,
            ),
            'image_token' => $images === [] ? null : $this->browser->libraryToken(
                new UploadProfile($profile->storage, $images, null, $browse),
                false,
            ),
        ];
    }

    /** The file at an address of an HTML: the address of a storage, also with the host of the page before it. */
    private function referenceOf(string $address): ?FileReference
    {
        if ($address === '' || str_starts_with($address, 'data:') || str_starts_with($address, '#')) {
            return null;
        }
        $reference = $this->urls->referenceOf($address);
        if ($reference instanceof FileReference) {
            return $reference;
        }
        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            return null;
        }
        foreach ([$request->getSchemeAndHttpHost(), '//' . $request->getHttpHost()] as $host) {
            if (str_starts_with($address, $host . '/')) {
                return $this->urls->referenceOf(substr($address, strlen($host)));
            }
        }

        return null;
    }
}
