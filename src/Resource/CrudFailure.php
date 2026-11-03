<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use RuntimeException;
use Throwable;

/**
 * A driver refused an operation for a reason the person can act on: a record that is gone, a value that is taken.
 * Show it with {@see FailureMessage}: the exception's own message is for logs, the translation key is for people.
 */
class CrudFailure extends RuntimeException
{
    /**
     * @param int $status the HTTP status it amounts to (404 gone, 409 taken, 422 refused)
     * @param string|null $field the form field the message is shown at; null = the form as a whole
     * @param string|null $translationKey what to tell the person; null = the (English) message
     * @param array<string, string> $parameters of the translation
     * @param array<string, list<string>> $violations more messages, ready to show, by the field they are about (a
     *     property path: "email", "address.city"; "" = the form) - what a validation of the domain refused, each at
     *     its field
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly ?string $field = null,
        ?Throwable $previous = null,
        public readonly ?string $translationKey = null,
        public readonly array $parameters = [],
        public readonly array $violations = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notFound(?Throwable $previous = null): self
    {
        return new self(404, 'Not found.', null, $previous, 'crud.error.not_found');
    }

    public static function forbidden(?Throwable $previous = null): self
    {
        return new self(403, 'Forbidden.', null, $previous, 'crud.error.forbidden');
    }
}
