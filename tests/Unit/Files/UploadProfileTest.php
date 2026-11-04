<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\UploadProfile;
use PHPUnit\Framework\TestCase;

final class UploadProfileTest extends TestCase
{
    public function testKindsGroupsAndExtensions(): void
    {
        $profile = new UploadProfile('public', ['image/*', 'application/pdf', '.csv']);

        self::assertTrue($profile->accepts('image/png', 'png'));
        self::assertTrue($profile->accepts('application/pdf', 'pdf'));
        self::assertTrue($profile->accepts('text/plain', 'csv'));
        self::assertFalse($profile->accepts('application/zip', 'zip'));
    }

    public function testWhatABrowserWouldRunOnlyWhenNamed(): void
    {
        self::assertTrue(new UploadProfile('public')->accepts('application/zip', 'zip'), 'Nothing named: any kind.');
        self::assertFalse(new UploadProfile('public')->accepts('text/html', 'html'));
        self::assertFalse(new UploadProfile('public', ['image/*'])->accepts('image/svg+xml', 'svg'));
        self::assertTrue(new UploadProfile('public', ['image/svg+xml'])->accepts('image/svg+xml', 'svg'));
    }

    public function testAGalleryTakesOnlyImages(): void
    {
        self::assertTrue(UploadProfile::images('public')->imagesOnly());
        self::assertSame(UploadProfile::IMAGES, UploadProfile::expand(['images']));
        self::assertFalse(new UploadProfile('public', ['image/*', '.pdf'])->imagesOnly());
        self::assertFalse(new UploadProfile('public')->imagesOnly());
    }

    public function testItGoesThroughTheAddressOfAnUpload(): void
    {
        $profile = new UploadProfile('avatars', ['image/png', '.webp'], 2048, ['public']);

        self::assertEquals($profile, UploadProfile::fromQuery($profile->toQuery()));
        self::assertSame(['avatars', 'public'], $profile->storages());
    }

    public function testAFileOfALibraryWithoutItsKindIsJudgedByItsExtension(): void
    {
        $photo = StoredFile::of(FileReference::parse('legacy://2019/photo.jpg'));

        self::assertTrue(UploadProfile::images('legacy')->acceptsFile($photo));
        self::assertFalse(new UploadProfile('legacy', ['.pdf'])->acceptsFile($photo));
    }
}
