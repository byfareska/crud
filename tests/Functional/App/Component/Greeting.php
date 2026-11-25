<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Component;

use Byfareska\Crud\Routing\PageParameters;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * A live component of the application's, of no panel: answered wherever it is asked for. It prints the shop of the
 * page parameters, as a DoctrineScope of the application would narrow by it.
 */
#[AsLiveComponent('App:Greeting', template: 'components/App/Greeting.html.twig')]
final class Greeting
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $name = 'world';

    public function __construct(
        private readonly PageParameters $parameters,
    ) {
    }

    public function getShop(): string
    {
        return $this->parameters->get('shop') ?? '-';
    }
}
