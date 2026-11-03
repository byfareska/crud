<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDriver;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Resource\Column;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\DefaultCrudRecords;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use Byfareska\Crud\UseCase\Page;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

/**
 * What the details of a record show - not what it keeps secret.
 */
final class CrudRecordsTest extends TestCase
{
    private const array USER = [
        'id' => '7',
        'email' => 'jan@example.com',
        'passwordHash' => '$2y$13$abc',
        'resetToken' => 'f00',
        'apiKey' => 'k3y',
        'passport' => 'AB123',
        'note' => 'VIP',
    ];

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        $secret = ['password', 'passwordHash', 'reset_token', 'apiKey', 'APIKey', 'privateKey', 'totpSecret', 'salt'];
        foreach ($secret as $name) {
            yield $name => [$name, true];
        }
        foreach (['hashtag', 'passport', 'passenger', 'footprint', 'tokenizer', 'keyword', 'email'] as $name) {
            yield $name => [$name, false];
        }
    }

    #[DataProvider('names')]
    public function testWhatLooksSecretGoesByTheWordsOfTheName(string $name, bool $secret): void
    {
        self::assertSame($secret, AsCrud::looksSecret($name));
    }

    public function testDetailsOfEveryPropertyLeaveOutWhatLooksSecretAndWhatIsHidden(): void
    {
        $crud = $this->user(hidden: ['note']);

        $details = $this->records()->details($crud, '7');

        self::assertSame(['id', 'email', 'passport'], $this->fields($details['rows']));
        self::assertSame(self::USER, $details['item'], 'What a guard or an action goes by still has every property.');
    }

    public function testWhatTheDetailsNameIsShownButNotWhatIsHidden(): void
    {
        $crud = $this->user(details: ['email', 'apiKey', 'note'], hidden: ['note']);

        $details = $this->records()->details($crud, '7');

        self::assertSame(['email', 'apiKey'], $this->fields($details['rows']));
    }

    /**
     * @param list<array{column: Column, value: mixed}> $rows
     *
     * @return list<string>
     */
    private function fields(array $rows): array
    {
        return array_map(static fn (array $row): string => $row['column']->field, $rows);
    }

    /**
     * @param list<string> $details
     * @param list<string> $hidden
     */
    private function user(array $details = [], array $hidden = []): AsCrud
    {
        return new AsCrud(
            'user',
            '/users',
            'person',
            list: ListThingsDto::class,
            read: ShowThingDto::class,
            details: $details,
            hidden: $hidden,
        );
    }

    private function records(): CrudRecords
    {
        $driver = new readonly class (self::USER) implements CrudDriver {
            /**
             * @param array<string, mixed> $record
             */
            public function __construct(private array $record)
            {
            }

            public function name(): string
            {
                return 'dto';
            }

            public function listForm(AsCrud $crud): FormInterface
            {
                throw new LogicException('Not asked.');
            }

            public function list(AsCrud $crud, FormInterface $form): Page
            {
                throw new LogicException('Not asked.');
            }

            public function read(AsCrud $crud, string $id): array
            {
                return $this->record;
            }

            public function delete(AsCrud $crud, string $id): void
            {
            }

            public function form(AsCrud $crud, ?string $id, array $with = []): FormBuilderInterface
            {
                throw new LogicException('Not asked.');
            }

            public function save(AsCrud $crud, FormInterface $form, ?string $id): ?string
            {
                return $id;
            }
        };

        return new DefaultCrudRecords(new CrudDrivers([$driver]));
    }
}
