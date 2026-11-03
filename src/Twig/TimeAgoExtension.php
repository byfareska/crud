<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

/**
 * How long ago something happened, as people say it: "przed chwilą", "5 min temu", "3 godz. temu", "wczoraj";
 * a week and older is the date itself. `{{ login.createdAt|time_ago }}` (a date or its ISO 8601 text).
 */
final readonly class TimeAgoExtension
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFilter('time_ago')]
    public function ago(DateTimeInterface|string|null $at, ?DateTimeInterface $now = null): string
    {
        if ($at === null || $at === '') {
            return '';
        }
        $at = is_string($at) ? new DateTimeImmutable($at) : $at;
        $seconds = max(0, ($now ?? new DateTimeImmutable())->getTimestamp() - $at->getTimestamp());

        return match (true) {
            $seconds < 60 => $this->translator->trans('ui.time_ago.now'),
            $seconds < 3600 => $this->translator->trans('ui.time_ago.minutes', ['count' => intdiv($seconds, 60)]),
            $seconds < 86400 => $this->translator->trans('ui.time_ago.hours', ['count' => intdiv($seconds, 3600)]),
            $seconds < 7 * 86400 => $this->translator->trans('ui.time_ago.days', ['count' => intdiv($seconds, 86400)]),
            default => $this->translator->trans('ui.time_ago.date', ['at' => $at]),
        };
    }
}
