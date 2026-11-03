<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\UseCase;

use ArrayObject;
use Byfareska\Crud\Attribute\UseCase;
use Byfareska\Crud\UseCase\MapsToRequest;
use Byfareska\Crud\UseCase\UseCaseErrorMapper;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use Byfareska\Crud\UseCase\UseCaseFailed;
use Byfareska\Crud\UseCase\UseCaseMiddleware;
use Byfareska\Crud\UseCase\UseCaseResolver;
use Byfareska\Crud\Tests\Unit\Fixtures\RunDto;
use Byfareska\Crud\Tests\Unit\Fixtures\RunUseCase;
use Closure;
use DomainException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

final class UseCaseExecutorTest extends TestCase
{
    public function testMiddlewaresWrapTheUseCaseTheFirstOutermost(): void
    {
        /** @var ArrayObject<int, string> $calls */
        $calls = new ArrayObject();
        $executor = $this->executor(
            static function (object $request) use ($calls): string {
                $calls[] = 'use case';

                return 'done';
            },
            [$this->middleware('outer', $calls), $this->middleware('inner', $calls)],
        );

        self::assertSame('done', $executor->execute(new RunDto()));
        self::assertSame(['outer', 'inner', 'use case'], $calls->getArrayCopy());
    }

    public function testTheUseCaseOfADtoWithoutTheAttribute(): void
    {
        // A DTO of a library: no #[UseCase] of its own, the resolver of the application names one.
        $dto = new readonly class implements MapsToRequest {
            public function toRequest(): stdClass
            {
                return new stdClass();
            }
        };
        $resolver = new class implements UseCaseResolver {
            public function of(MapsToRequest $dto): UseCase
            {
                return new UseCase(RunUseCase::class);
            }
        };
        $executor = new UseCaseExecutor(
            new ServiceLocator([
                RunUseCase::class => static fn (): Closure => static fn (): string => 'by the resolver',
            ]),
            resolver: $resolver,
        );

        self::assertSame('by the resolver', $executor->execute($dto));
    }

    public function testAnErrorOfEveryUseCaseIsMappedOnce(): void
    {
        $mapper = new class implements UseCaseErrorMapper {
            public function map(MapsToRequest $dto, Throwable $exception): ?UseCaseFailed
            {
                return $exception instanceof DomainException
                    ? new UseCaseFailed(409, $exception->getMessage(), translationKey: 'app.conflict')
                    : null;
            }
        };
        $executor = $this->executor(static fn (): never => throw new DomainException('taken'), errorMappers: [$mapper]);

        try {
            $executor->execute(new RunDto());
            self::fail('Not refused.');
        } catch (UseCaseFailed $failure) {
            self::assertSame([409, 'app.conflict'], [$failure->status, $failure->translationKey]);
        }
    }

    public function testAValidationOfTheDomainIsEachMessageAtItsField(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Too short.', null, [], null, 'title', 'a'),
            new ConstraintViolation('Not an e-mail.', null, [], null, 'contact.email', 'x'),
        ]);
        $executor = $this->executor(
            static fn (): never => throw new ValidationFailedException(new stdClass(), $violations),
        );

        try {
            $executor->execute(new RunDto());
            self::fail('Not refused.');
        } catch (UseCaseFailed $failure) {
            self::assertSame(422, $failure->status);
            self::assertSame(['title' => ['Too short.'], 'contact.email' => ['Not an e-mail.']], $failure->violations);
        }
    }

    public function testAValueTheRequestDoesNotTakeIsRefused(): void
    {
        $this->expectException(UseCaseFailed::class);
        $this->expectExceptionCode(0);

        $this->executor(static fn (): string => 'never')->execute(new RunDto(fails: true));
    }

    /**
     * @param list<UseCaseMiddleware> $middlewares
     * @param list<UseCaseErrorMapper> $errorMappers
     */
    private function executor(Closure $useCase, array $middlewares = [], array $errorMappers = []): UseCaseExecutor
    {
        return new UseCaseExecutor(
            new ServiceLocator([RunUseCase::class => static fn (): Closure => $useCase]),
            $middlewares,
            $errorMappers,
        );
    }

    /**
     * @param ArrayObject<int, string> $calls
     */
    private function middleware(string $name, ArrayObject $calls): UseCaseMiddleware
    {
        return new readonly class ($name, $calls) implements UseCaseMiddleware {
            /**
             * @param ArrayObject<int, string> $calls
             */
            public function __construct(private string $name, private ArrayObject $calls)
            {
            }

            public function handle(MapsToRequest $dto, object $request, Closure $next): mixed
            {
                $this->calls[] = $this->name;

                return $next($request);
            }
        };
    }
}
