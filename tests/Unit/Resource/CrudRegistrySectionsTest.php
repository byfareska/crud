<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use ArrayObject;
use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Resource\CrudAdditionsProvider;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCrud;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingSections;
use PHPUnit\Framework\TestCase;

final class CrudRegistrySectionsTest extends TestCase
{
    public function testASectionWithARoleIsOnlyForSomebodyWhoHasIt(): void
    {
        $finance = $this->registry(['ROLE_FINANCE']);
        self::assertSame(['notes', 'costs'], array_keys($finance->formSectionsOf('thing')));
        self::assertSame(
            ['summary', 'history', 'margins'],
            array_keys($finance->detailsSectionsOf('thing')),
            'By priority.',
        );

        $other = $this->registry([]);
        self::assertSame(['notes'], array_keys($other->formSectionsOf('thing')));
        self::assertSame(['summary', 'history'], array_keys($other->detailsSectionsOf('thing')));
    }

    public function testTheSectionsOfARoleAreForgottenAfterTheRequest(): void
    {
        /** @var ArrayObject<int, string> $roles */
        $roles = new ArrayObject(['ROLE_FINANCE']);
        $actor = new readonly class ($roles) implements Actor {
            /** @param ArrayObject<int, string> $roles */
            public function __construct(private ArrayObject $roles)
            {
            }

            public function id(): string
            {
                return '1';
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return in_array($attribute, $this->roles->getArrayCopy(), true);
            }
        };
        $registry = new CrudRegistry([ThingCrud::class], [ThingSections::class], [ThingSections::class], actor: $actor);
        self::assertContains('costs', array_keys($registry->formSectionsOf('thing')));

        // A worker answers the next request, of somebody without the role.
        $roles->exchangeArray([]);
        $registry->reset();

        self::assertNotContains('costs', array_keys($registry->formSectionsOf('thing')));
        self::assertNotContains('margins', array_keys($registry->detailsSectionsOf('thing')));
    }

    public function testSectionsAndPagesOfAProvider(): void
    {
        $erp = new readonly class implements CrudAdditionsProvider {
            public function pages(): iterable
            {
                yield new AsCrudPage('thing', 'erp-log', 'App:ErpLog');
            }

            public function formSections(): iterable
            {
                return [];
            }

            public function detailsSections(): iterable
            {
                yield new AsCrudDetailsSection(AsCrudDetailsSection::EVERY, 'erp', 'App:ErpStatus');
            }
        };
        $registry = new CrudRegistry([ThingCrud::class], additions: [$erp]);

        self::assertSame(['erp'], array_keys($registry->detailsSectionsOf('thing')));
        self::assertSame(['erp-log'], array_keys($registry->pagesOf('thing')));
    }

    /**
     * @param list<string> $roles
     */
    private function registry(array $roles): CrudRegistry
    {
        $actor = new readonly class ($roles) implements Actor {
            /**
             * @param list<string> $roles
             */
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

        return new CrudRegistry(
            [ThingCrud::class],
            [ThingSections::class],
            [ThingSections::class],
            actor: $actor,
        );
    }
}
