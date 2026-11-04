<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Files;

use Byfareska\Crud\Attribute\CrudRichText;

/**
 * A record with HTML: a body with images uploaded into it, a lead without.
 */
final class Article
{
    #[CrudRichText(editorOptions: ['toolbar' => 'bold italic'], files: ['accept' => ['images']])]
    public ?string $body = null;

    #[CrudRichText]
    public ?string $lead = null;

    public string $title = '';
}
