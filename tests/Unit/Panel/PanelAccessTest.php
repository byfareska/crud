<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Panel;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Page\CrudContext;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\ScopeExtension;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Resource\DefaultCrudAccess;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\Gadget;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCrud;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * What is in a panel is no more open than the panel - whichever way it is asked for.
 */
final class PanelAccessTest extends TestCase
{
    private RequestStack $requests;
    private CrudRegistry $registry;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->requests->push(new Request());
        $this->registry = new CrudRegistry([Gadget::class, ThingCrud::class]);
    }

    public function testAResourceWithoutARoleIsStillOnlyForThoseThePanelIsFor(): void
    {
        // "gizmo" names no role; its panel "admin" has one.
        $gizmo = $this->registry->get('gizmo');

        self::assertFalse($this->access(['ROLE_USER'])->isGranted($gizmo));
        self::assertTrue($this->access(['ROLE_ADMIN'])->isGranted($gizmo));
        self::assertTrue($this->access([])->isGranted($this->registry->get('thing')));

        $this->expectException(AccessDeniedHttpException::class);
        $this->access(['ROLE_USER'])->assertGranted($gizmo);
    }

    public function testAnOperationTheRequestRefusesIsNotGranted(): void
    {
        $thing = $this->registry->get('thing')->refusing(CrudOperation::Read);
        $access = $this->access([]);

        self::assertTrue($access->isGranted($thing));
        self::assertTrue($access->isGranted($thing, CrudOperation::List));
        self::assertFalse($access->isGranted($thing, CrudOperation::Read));

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('does not read');
        $access->assertGranted($thing, CrudOperation::Read);
    }

    public function testTheRoleOfAResourceIsAskedAboutIt(): void
    {
        $actor = new RecordingActor(['ROLE_EDITOR']);
        $crud = new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, role: 'ROLE_EDITOR');

        self::assertTrue(new DefaultCrudAccess($actor, $this->scopes([], $actor))->isGranted($crud));
        self::assertSame([['ROLE_EDITOR', $crud]], $actor->asked);
    }

    public function testSomethingOfAPanelNeedsItsOwnRoleAndThatOfThePanel(): void
    {
        $scopes = $this->scopes(['ROLE_ADMIN']);

        self::assertTrue($scopes->isGrantedIn('admin'));
        self::assertTrue($scopes->isGrantedIn(null, 'ROLE_ADMIN'));
        self::assertFalse($scopes->isGrantedIn('admin', 'ROLE_AUDITOR'));
        self::assertFalse($this->scopes(['ROLE_AUDITOR'])->isGrantedIn('admin', 'ROLE_AUDITOR'));
    }

    public function testThePanelOfAPageWithoutAScopeIsThatOfItsResource(): void
    {
        $scopes = $this->scopes([]);
        self::assertSame('panel', $scopes->current()->name);

        $request = $this->requests->getMainRequest();
        $request?->attributes->set(CrudContext::ATTRIBUTE, new CrudContext($this->registry->get('gizmo'), '3'));
        self::assertSame('admin', $scopes->current()->name);

        // The route says it, when it does.
        $request?->attributes->set(Scope::ATTRIBUTE, 'panel');
        self::assertSame('panel', $scopes->current()->name);
    }

    /** @param list<string> $roles */
    private function access(array $roles): CrudAccess
    {
        return new DefaultCrudAccess($this->actor($roles), $this->scopes($roles));
    }

    public function testTheRoleOfAPanelIsAskedAboutIt(): void
    {
        $actor = new RecordingActor(['ROLE_ADMIN']);
        $scopes = $this->scopes([], $actor);

        self::assertTrue($scopes->isGrantedIn('admin'));
        self::assertSame('ROLE_ADMIN', $actor->asked[0][0]);
        self::assertInstanceOf(Scope::class, $actor->asked[0][1]);
        self::assertSame('admin', $actor->asked[0][1]->name);
    }

    public function testAnExtensionChangesAPanelForTheRequest(): void
    {
        $shop = new readonly class implements ScopeExtension {
            public function scope(): string
            {
                return 'admin';
            }

            public function extend(Scope $scope): Scope
            {
                return $scope->with(brand: 'Sklep Ani', theme: 'tailwind');
            }
        };
        $scopes = $this->scopes([], extensions: [$shop]);

        self::assertSame('Sklep Ani', $scopes->get('admin')->brand);
        self::assertSame('tailwind', $scopes->get('admin')->theme);
        self::assertNull($scopes->get('panel')->brand);
        self::assertNull($scopes->declared('admin')->brand, 'What the routes are made of: as configured.');

        $this->expectException(\InvalidArgumentException::class);
        $scopes->get('admin')->with(dashboard: false);
    }

    /**
     * @param list<string> $roles
     * @param list<ScopeExtension> $extensions
     */
    private function scopes(array $roles, ?Actor $actor = null, array $extensions = []): Scopes
    {
        $config = [
            'panel' => [
                'label' => null,
                'icon' => 'house',
                'layout' => null,
                'theme' => null,
                'entries' => [],
                'role' => null,
                'dashboard' => true,
                'home' => null,
            ],
            'admin' => [
                'label' => null,
                'icon' => 'shield',
                'layout' => null,
                'theme' => null,
                'entries' => [],
                'role' => 'ROLE_ADMIN',
                'dashboard' => true,
                'home' => null,
            ],
        ];

        return new Scopes(
            $config,
            'layout.html.twig',
            'bootstrap5',
            ['app'],
            $this->requests,
            $actor ?? $this->actor($roles),
            new CurrentCrud($this->requests),
            $extensions,
        );
    }

    /** @param list<string> $roles */
    private function actor(array $roles): Actor
    {
        return new readonly class ($roles) implements Actor {
            /** @param list<string> $roles */
            public function __construct(private array $roles)
            {
            }

            public function id(): string
            {
                return '1';
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return in_array($attribute, $this->roles, true);
            }
        };
    }
}
