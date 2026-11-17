<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** An enum that translates itself. */
enum ThingStatus: string implements TranslatableInterface
{
    case Active = 'active';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('app.status.' . $this->value, locale: $locale);
    }
}
