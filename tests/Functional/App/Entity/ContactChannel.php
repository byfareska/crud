<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ContactChannel: string implements TranslatableInterface
{
    case Phone = 'phone';
    case Letter = 'letter';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('app.channel.' . $this->value, locale: $locale);
    }
}
