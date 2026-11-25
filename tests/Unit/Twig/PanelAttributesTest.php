<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Theme\ScopeThemeSelector;
use Byfareska\Crud\Theme\Themes;
use Byfareska\Crud\Twig\PanelExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * crud_attrs(): the attributes of a row, a button, an icon - their values escaped, and names that are no names of an
 * attribute left out, whatever they are made of.
 */
final class PanelAttributesTest extends TestCase
{
    public function testTheValuesAreEscaped(): void
    {
        self::assertSame(
            ' data-title="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;" hidden title="a &amp; b"',
            $this->extension()->attributes([
                'data-title' => '"><img src=x onerror=alert(1)>',
                'hidden' => true,
                'open' => false,
                'class' => null,
                'title' => 'a & b',
            ]),
        );
    }

    public function testTheNamesOfDataAriaAndOfTheFrameworksOfTheBrowser(): void
    {
        self::assertSame(
            ' data-row-id="7" aria-label="Row" x-on:click="open = true" @click="go" :class="on" data-a&amp;b="1"',
            $this->extension()->attributes([
                'data-row-id' => 7,
                'aria-label' => 'Row',
                'x-on:click' => 'open = true',
                '@click' => 'go',
                ':class' => 'on',
                'data-a&b' => '1',
            ]),
        );
    }

    /**
     * A name made of data of a record ("data-" and a value) must not end itself and start an attribute of its own.
     */
    #[DataProvider('namesThatAreNoNames')]
    public function testANameThatIsNoNameIsLeftOut(string $name): void
    {
        self::assertSame(' data-kept="1"', $this->extension()->attributes([$name => 'alert(1)', 'data-kept' => '1']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesThatAreNoNames(): iterable
    {
        yield 'a space' => ['data-x onmouseover'];
        yield 'a tab' => ["data-x\tonmouseover"];
        yield 'a new line' => ["data-x\nonfocus"];
        yield 'an equals sign' => ['data-x=1 onclick'];
        yield 'a quote' => ['data-x"onclick'];
        yield 'an apostrophe' => ["data-x'onclick"];
        yield 'the end of the tag' => ['data-x><script>alert(1)</script'];
        yield 'a slash' => ['data-x/onclick'];
        yield 'a control character' => ["data-x\0onclick"];
        yield 'nothing' => [''];
        yield 'not UTF-8' => ["data-\xC3\x28"];
    }

    private function extension(): PanelExtension
    {
        $requests = new RequestStack();
        $current = new CurrentCrud($requests);
        $actor = new readonly class implements Actor {
            public function id(): ?string
            {
                return null;
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return true;
            }
        };
        $scopes = new Scopes([], 'layout.html.twig', 'daisyui', [], $requests, $actor, $current);
        $themes = new Themes(['daisyui' => ['path' => '/daisyui', 'parent' => null]], new ScopeThemeSelector($scopes));

        return new PanelExtension('Panel', $scopes, $themes, $current);
    }
}
