<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * What was logged, each message with its placeholders filled in.
 */
final class MemoryLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = is_scalar($value) ? (string) $value : '';
        }
        $this->messages[] = strtr((string) $message, $replacements);
    }
}
