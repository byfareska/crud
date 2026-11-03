<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Which record an "own" resource (#[AsCrud(own: true)]) is for the one signed in, when it is not their user: "my
 * company" (the company of the user), "my shop", "my profile" kept apart from the account. A service, autoconfigured;
 * the first resolver of the resource that tells it wins, else it is the id of the one signed in (CrudAccess::ownId()).
 */
#[AutoconfigureTag(self::TAG)]
interface OwnRecordResolver
{
    public const string TAG = 'byfareska_crud.own_record_resolver';

    /** The resource of a resolver of every own resource. */
    public const string EVERY = '*';

    /** The name of the resource, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    /**
     * The id of the record, as in an address; null = not this resolver's to say.
     *
     * @throws AccessDeniedHttpException the one asking has none (no company)
     */
    public function ownId(AsCrud $crud): ?string;
}
