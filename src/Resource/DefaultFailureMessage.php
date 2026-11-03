<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What to tell the person an operation was refused for: the translation named by the failure, in the language of
 * the request. A failure without one is a programming detail and keeps its own, English, text.
 */
final readonly class DefaultFailureMessage implements FailureMessage
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function of(CrudFailure $failure): string
    {
        return $failure->translationKey === null
            ? $failure->getMessage()
            : $this->translator->trans($failure->translationKey, $failure->parameters);
    }
}
