<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Panel\Scope;
use LogicException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Refuses the routes of the application when the import of a panel ("type: byfareska_crud") puts a parameter into
 * its routes that the address would choose instead of the route: the pages of a panel know what they are of by the
 * defaults of their routes alone - the resource ("crud"), the page of #[AsCrudPage] ("name"), the dashboard
 * ("board"), the panel ("_scope") - and by the parameters of their own paths ("id", "page"). Symfony gives a parameter
 * of the address precedence over a default, so with `prefix: /{name}/admin` the address "/approve/admin/orders/5/
 * history" would open the page "approve" of the order, while the firewall and access_control saw ".../history"; with
 * `host: '{crud}.example.com'` any resource of any panel would open under this panel's prefix; with a `{id}` there, the
 * add form would be the edit form of that record.
 *
 * A parameter of the prefix or the host of a panel is the application's own (`/{shop}/admin`,
 * `{tenant}.example.com`), named like none of the bundle's (PageParametersListener::RESERVED) and not starting with "_"
 * - the same as a parameter of the path of a resource (AsCrud) - except "_locale" and "_format", which Symfony gives
 * a meaning of its own. Asked once the routes of the application are loaded: when they are cached (cache:warmup, a
 * changed routing file), not on every request.
 */
#[AsDecorator('routing.loader', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final readonly class PanelRoutesCheck implements LoaderInterface
{
    /** What may be a parameter of the prefix or the host of a panel though it starts with "_". */
    public const array SYMFONY = ['_locale', '_format'];

    public function __construct(
        #[AutowireDecorated]
        private LoaderInterface $inner,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $loaded = $this->inner->load($resource, $type);
        if ($loaded instanceof RouteCollection) {
            self::check($loaded);
        }

        return $loaded;
    }

    /**
     * @throws LogicException a route of a panel has a parameter the bundle does not let an address choose
     */
    public static function check(RouteCollection $routes): void
    {
        foreach ($routes->all() as $name => $route) {
            $own = $route->getOption(CrudRouteLoader::VARIABLES);
            if (!is_array($own)) {
                continue;
            }
            foreach ($route->compile()->getVariables() as $variable) {
                if (!is_string($variable) || in_array($variable, $own, true) || !self::isReserved($variable)) {
                    continue;
                }
                $scope = $route->getDefault(Scope::ATTRIBUTE);

                throw new LogicException(sprintf(
                    'The route "%s" of the panel "%s" has the parameter "{%s}" from the prefix or the host of its '
                    . 'import ("%s"): the pages of a panel take "%s" from their route, so the address would choose '
                    . 'it. Name it otherwise ({shop}, {tenant}); none of %s nor one starting with "_" but %s.',
                    $name,
                    is_string($scope) ? $scope : '',
                    $variable,
                    $route->getHost() . $route->getPath(),
                    $variable,
                    implode(', ', PageParametersListener::RESERVED),
                    implode(', ', self::SYMFONY),
                ));
            }
        }
    }

    private static function isReserved(string $variable): bool
    {
        return in_array($variable, PageParametersListener::RESERVED, true)
            || (str_starts_with($variable, '_') && !in_array($variable, self::SYMFONY, true));
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }
}
