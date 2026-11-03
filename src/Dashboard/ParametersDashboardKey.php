<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Byfareska\Crud\Routing\PageParameters;

/**
 * The bundle's {@see DashboardKey}: the panel ("admin"), and the parameters of the application in its address after a
 * "?" ("admin?shop=5"); a key longer than the column is cut and given a hash of the whole.
 */
final readonly class ParametersDashboardKey implements DashboardKey
{
    private const int LENGTH = 64;

    public function __construct(
        private PageParameters $parameters,
    ) {
    }

    public function of(string $board): string
    {
        $parameters = $this->parameters->all();
        if ($parameters === []) {
            return $board;
        }
        ksort($parameters);
        $key = $board . '?' . http_build_query($parameters);

        if (strlen($key) <= self::LENGTH) {
            return $key;
        }

        return substr($key, 0, self::LENGTH - 17) . '#' . substr(sha1($key), 0, 16);
    }
}
