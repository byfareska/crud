<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Security;

use Byfareska\Crud\Security\DefaultUserIdMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class DefaultUserIdMapperTest extends TestCase
{
    public function testTheIdOfAUserWithOne(): void
    {
        self::assertSame(42, new DefaultUserIdMapper()->idOf(self::userWithId(42)));

        $uuid = self::uuid();
        self::assertSame($uuid, new DefaultUserIdMapper()->idOf(self::userWithId($uuid)));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function noIds(): iterable
    {
        yield 'not saved yet' => [null];
        yield 'empty' => [''];
        yield 'not an id' => [[1]];
    }

    #[DataProvider('noIds')]
    public function testTheIdentifierOfAUserWithoutAnId(mixed $id): void
    {
        self::assertSame('jan', new DefaultUserIdMapper()->idOf(self::userWithId($id)));
        self::assertSame('jan', new DefaultUserIdMapper()->idOf(new InMemoryUser('jan', null)));
    }

    public static function uuid(): Stringable
    {
        return new class implements Stringable {
            public function __toString(): string
            {
                return '0199f0c4-3b8a-7c3e-9d1a-2b6f1e8c4a10';
            }
        };
    }

    public static function userWithId(mixed $id): UserInterface
    {
        return new readonly class ($id) implements UserInterface {
            public function __construct(private mixed $id)
            {
            }

            public function getId(): mixed
            {
                return $this->id;
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'jan';
            }
        };
    }
}
