<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What the whole dashboard is looked at for - a period, a branch - that every widget on it goes by: the parameters
 * "context" of the address of its page (/admin?context[period]=q3&context[branch]=7), set by a form of the application
 * in the block "dashboard_filters" of its head. A widget asks for them itself; one loaded after the page (lazy) is
 * told them by the address of the page its request comes from (X-Live-Url):
 *
 *     public function __construct(private DashboardContext $context) {}
 *
 *     public function getRevenue(): int
 *     {
 *         return $this->sales->revenue(period: $this->context->get('period', 'month'));
 *     }
 *
 * They are what the address says: whether the one looking may see that branch is for the widget to check.
 */
final readonly class DashboardContext
{
    public const string PARAMETER = 'context';

    private const string LIVE_URL = 'X-Live-Url';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function get(string $name, ?string $default = null): ?string
    {
        return $this->all()[$name] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $request = $this->requests->getMainRequest();
        if (!$request instanceof Request) {
            return [];
        }
        // A live request: the address of the page it comes from.
        $page = $request->headers->get(self::LIVE_URL);
        if (is_string($page) && $page !== '') {
            parse_str((string) parse_url($page, PHP_URL_QUERY), $query);
            $given = $query[self::PARAMETER] ?? [];
        } else {
            $given = $request->query->all()[self::PARAMETER] ?? [];
        }

        $context = [];
        foreach (is_array($given) ? $given : [] as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $context[$name] = (string) $value;
            }
        }

        return $context;
    }
}
