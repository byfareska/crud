<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * The routes of files of a panel (CrudRouteLoader adds them to every panel when byfareska_crud.files is enabled).
 */
final class FileRoutes
{
    /** POST: an upload, to the storage of a signed profile. */
    public static function upload(string $scope): string
    {
        return 'crud_files_' . $scope . '_upload';
    }

    /** GET: a file of a storage without addresses of its own, by a signed address. */
    public static function download(string $scope): string
    {
        return 'crud_files_' . $scope . '_download';
    }

    /** GET: a size of an image made when first asked for (VariantStrategy::Lazy), by a signed address. */
    public static function variant(string $scope): string
    {
        return 'crud_files_' . $scope . '_variant';
    }
}
