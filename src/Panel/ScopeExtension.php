<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Changes a panel for the request being answered - what the configuration cannot know: the name and the logo of the
 * shop of a white-label panel (/{shop}/admin, by PageParameters), the theme of a tenant, a role of a feature flag.
 * `$scope->with(brand: $shop->name)`; its name and whether it has a dashboard stay (its routes are made of them).
 * A service, autoconfigured; asked whenever the panel is.
 */
#[AutoconfigureTag(self::TAG)]
interface ScopeExtension
{
    public const string TAG = 'byfareska_crud.scope_extension';

    /** The extension of every panel. */
    public const string EVERY = '*';

    /** The name of the panel, as in byfareska_crud.scopes; EVERY for all. */
    public function scope(): string;

    public function extend(Scope $scope): Scope;
}
