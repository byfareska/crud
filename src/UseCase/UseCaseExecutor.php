<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;
use ValueError;

/**
 * Runs the use case a DTO points at: the single path from a DTO to the application layer, whoever calls it - the
 * "dto" driver of the CRUD pages, a controller of the application, an API.
 */
final readonly class UseCaseExecutor
{
    /** Every service implementing Query or Command gets it; a use case of another shape can be tagged by hand. */
    public const string TAG = 'byfareska_crud.use_case';

    /**
     * @param iterable<UseCaseMiddleware> $middlewares
     * @param iterable<UseCaseErrorMapper> $errorMappers
     */
    public function __construct(
        // Every Query and Command, by class name (autoconfigured by the bundle).
        #[AutowireLocator(self::TAG)]
        private ContainerInterface $useCases,
        #[AutowireIterator(UseCaseMiddleware::TAG)]
        private iterable $middlewares = [],
        #[AutowireIterator(UseCaseErrorMapper::TAG)]
        private iterable $errorMappers = [],
        private UseCaseResolver $resolver = new AttributeUseCaseResolver(),
    ) {
    }

    /**
     * @return mixed whatever the use case returns
     *
     * @throws UseCaseFailed for the exceptions listed in the DTO's #[UseCase], for an id that is not one and for an
     *     operation the one asking is not allowed (AccessDeniedException)
     */
    public function execute(MapsToRequest $dto): mixed
    {
        $metadata = $this->resolver->of($dto);
        $useCase = $this->useCases->get($metadata->class);
        if (!is_callable($useCase)) {
            throw new LogicException(sprintf('The use case "%s" is not invokable.', $metadata->class));
        }

        try {
            try {
                $request = $dto->toRequest();
            } catch (ValueError $exception) {
                // A value of the form the request does not take (an enum of another case): the form's to correct.
                throw new UseCaseFailed(422, $exception->getMessage(), null, $exception, 'crud.error.refused');
            }
            $run = static fn (object $request): mixed => $useCase($request);
            foreach (array_reverse([...$this->middlewares]) as $middleware) {
                $next = $run;
                $run = static fn (object $request): mixed => $middleware->handle($dto, $request, $next);
            }

            return $run($request);
        } catch (UseCaseFailed $exception) {
            throw $exception;
        } catch (InvalidIdException $exception) {
            // Something that is not an id cannot name anything that exists.
            throw new UseCaseFailed(404, 'Not found.', null, $exception, 'crud.error.not_found');
        } catch (AccessDeniedException $exception) {
            throw new UseCaseFailed(403, $exception->getMessage(), null, $exception, 'crud.error.forbidden');
        } catch (Throwable $exception) {
            foreach ($metadata->errors as $class => $failure) {
                if ($exception instanceof $class) {
                    throw new UseCaseFailed(
                        $failure->status,
                        $exception->getMessage(),
                        $failure->field,
                        $exception,
                        $failure->message,
                    );
                }
            }

            foreach ($this->errorMappers as $mapper) {
                $failure = $mapper->map($dto, $exception);
                if ($failure instanceof UseCaseFailed) {
                    throw $failure;
                }
            }
            // A validation of the domain (Symfony Validator): each message at its field.
            if ($exception instanceof ValidationFailedException) {
                $violations = [];
                foreach ($exception->getViolations() as $violation) {
                    $violations[$violation->getPropertyPath()][] = (string) $violation->getMessage();
                }

                throw new UseCaseFailed(422, $exception->getMessage(), null, $exception, violations: $violations);
            }

            throw $exception;
        }
    }
}
