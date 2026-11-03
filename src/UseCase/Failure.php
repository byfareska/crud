<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

final readonly class Failure
{
    /**
     * @param int $status the HTTP status the API answers with
     * @param string|null $field the DTO property a form shows the message at; null = the form as a whole
     * @param string|null $message the translation key of what to tell the user; null = the text of the exception
     */
    public function __construct(
        public int $status,
        public ?string $field = null,
        public ?string $message = null,
    ) {
    }
}
