<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum AccountStatus: string implements TranslatableInterface
{
    case Active = 'active';
    case Closed = 'closed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('app.status.' . $this->value, locale: $locale);
    }
}
