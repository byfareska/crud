<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Security;

use Byfareska\Crud\Resource\DefaultIdMapper;
use Byfareska\Crud\Security\DefaultUserIdMapper;
use Byfareska\Crud\Security\SecurityActor;
use Byfareska\Crud\Security\UserIdMapper;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityActorTest extends TestCase
{
    public function testTheIdOfTheUserAsText(): void
    {
        self::assertSame('42', $this->actor(DefaultUserIdMapperTest::userWithId(42))->id());
        self::assertSame(
            '0199f0c4-3b8a-7c3e-9d1a-2b6f1e8c4a10',
            $this->actor(DefaultUserIdMapperTest::userWithId(DefaultUserIdMapperTest::uuid()))->id(),
        );
    }

    public function testNobodySignedIn(): void
    {
        self::assertNull($this->actor(null)->id());
        self::assertNull(new SecurityActor(new DefaultUserIdMapper(), new DefaultIdMapper())->id());
    }

    public function testWithoutSecurityNobodyHasARole(): void
    {
        $actor = new SecurityActor(new DefaultUserIdMapper(), new DefaultIdMapper());

        self::assertFalse($actor->isGranted('ROLE_ADMIN'), 'A role nobody can check is not "everybody".');
    }

    public function testAnIdThatIsNoTextIsAnError(): void
    {
        $empty = new class implements UserIdMapper {
            public function idOf(UserInterface $user): string
            {
                return '';
            }
        };

        $this->expectException(LogicException::class);

        $this->actor(DefaultUserIdMapperTest::userWithId(1), $empty)->id();
    }

    private function actor(?UserInterface $user, UserIdMapper $userIds = new DefaultUserIdMapper()): SecurityActor
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new SecurityActor($userIds, new DefaultIdMapper(), $security);
    }
}
