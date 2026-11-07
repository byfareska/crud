<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Intervention;

use Byfareska\Crud\Files\Image\ImageResizer;
use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Intervention\InterventionImageResizer;
use Byfareska\Crud\Files\Testing\ImageResizerContractTestCase;
use Imagick;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * The sizes of images made by intervention/image with Imagick: the colour profile of a photo is kept.
 */
#[RequiresPhpExtension('imagick')]
final class InterventionImagickResizerTest extends ImageResizerContractTestCase
{
    protected function resizer(): ImageResizer
    {
        return new InterventionImageResizer('imagick');
    }

    public function testAutoTakesImagick(): void
    {
        self::assertSame('imagick', new InterventionImageResizer('auto')->driverName());
        self::assertSame('gd', new InterventionImageResizer('gd')->driverName());
    }

    public function testTheColourProfileIsKept(): void
    {
        $photo = $this->withProfile($this->jpeg(800, 600));

        $variant = new ImageVariant('p', 400);
        $kept = $this->profileOf($this->resizer()->resize($photo, 'image/jpeg', $variant, 80)->contents);
        $dropped = $this->profileOf(
            new InterventionImageResizer('gd')->resize($photo, 'image/jpeg', $variant, 80)->contents,
        );

        self::assertSame($this->profileOf($photo), $kept);
        self::assertNull($dropped, 'GD writes no profile.');
    }

    /** A JPEG with an ICC profile, as a phone writes it (Display P3); the profile is a copy of sRGB of Imagick's. */
    private function withProfile(string $jpeg): string
    {
        $image = new Imagick();
        $image->readImageBlob($jpeg);
        $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        $image->setImageProfile('icc', $this->iccProfile());

        return $image->getImageBlob();
    }

    private function profileOf(string $contents): ?string
    {
        $image = new Imagick();
        $image->readImageBlob($contents);

        $profile = $image->getImageProfiles('icc')['icc'] ?? null;

        return is_string($profile) ? $profile : null;
    }

    /**
     * The smallest ICC profile of RGB: its header (version 2.1, a monitor, RGB in XYZ, the white of D50) and no tags.
     */
    private function iccProfile(): string
    {
        $header = "\0\0\0\0" . pack('N', 0x02100000) . 'mntr' . 'RGB ' . 'XYZ ' . str_repeat("\0", 12)
            . 'acsp' . str_repeat("\0", 28)
            . pack('NNN', 0x0000F6D6, 0x00010000, 0x0000D32D) . str_repeat("\0", 48);

        return pack('N', 4 + strlen($header) + 4) . $header . pack('N', 0);
    }
}
