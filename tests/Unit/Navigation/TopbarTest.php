<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Navigation;

use Byfareska\Crud\Component\Navigation\Topbar;
use Byfareska\Crud\Navigation\TopbarWidget;
use Byfareska\Crud\Navigation\TopbarWidgetSource;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class TopbarTest extends TestCase
{
    public function testAWidgetOfOnePanelAndOfARoleWithItsProps(): void
    {
        $source = new class implements TopbarWidgetSource {
            public function widgets(): iterable
            {
                yield new TopbarWidget('App:Bell', 10, props: ['max' => 9]);
                yield new TopbarWidget('App:Approvals', 5, scope: 'admin');
                yield new TopbarWidget('App:Billing', 1, role: 'ROLE_FINANCE');
            }
        };

        self::assertSame(['App:Bell'], $this->topbar($source, 'panel')->getWidgets());
        $admin = $this->topbar($source, 'admin')->getShown();
        self::assertSame(['App:Approvals', 'App:Bell'], array_column($admin, 'component'));
        self::assertSame(['max' => 9], $admin[1]->props);
    }

    private function topbar(TopbarWidgetSource $source, string $scope): Topbar
    {
        $request = new Request();
        $request->attributes->set(Scope::ATTRIBUTE, $scope);
        $requests = new RequestStack();
        $requests->push($request);
        $actor = new class implements Actor {
            public function id(): ?string
            {
                return null;
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return false;
            }
        };
        $config = static fn (): array => [
            'label' => null,
            'icon' => 'house',
            'layout' => null,
            'theme' => null,
            'entries' => [],
            'role' => null,
            'dashboard' => false,
            'home' => null,
        ];

        return new Topbar(
            [$source],
            new Scopes(
                ['panel' => $config(), 'admin' => $config()],
                'layout.html.twig',
                'bootstrap5',
                ['app'],
                $requests,
                $actor,
                new CurrentCrud($requests),
            ),
        );
    }
}
