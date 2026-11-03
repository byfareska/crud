<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Twig\PanelExtension;
use PHPUnit\Framework\TestCase;

final class PanelDirectionTest extends TestCase
{
    public function testALanguageWrittenFromTheRight(): void
    {
        self::assertSame('rtl', PanelExtension::directionOf('ar'));
        self::assertSame('rtl', PanelExtension::directionOf('fa_IR'));
        self::assertSame('rtl', PanelExtension::directionOf('he-IL'));
        self::assertSame('ltr', PanelExtension::directionOf('pl'));
        self::assertSame('ltr', PanelExtension::directionOf('zh_CN'));
    }
}
