<?php

declare(strict_types=1);

namespace Byfareska\Crud\Page;

use Byfareska\Crud\Attribute\MapCrud;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The {@see CrudContext} of an argument of a controller with {@see MapCrud}: the resource it names and the record of
 * the route parameter it names, opened by {@see CrudPages}. Asked only for such an argument.
 */
#[AsTargetedValueResolver]
final readonly class CrudContextResolver implements ValueResolverInterface
{
    public function __construct(
        private CrudPages $pages,
        private ?RecordGuards $guards = null,
        private ?FailureMessage $failureMessage = null,
    ) {
    }

    /**
     * @return iterable<CrudContext>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $map = $argument->getAttributesOfType(MapCrud::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
        if (!$map instanceof MapCrud) {
            return [];
        }
        if ($argument->getType() !== CrudContext::class) {
            throw new LogicException(sprintf(
                '#[MapCrud] gives a %s, not a %s: argument "$%s" of "%s()".',
                CrudContext::class,
                (string) $argument->getType(),
                $argument->getName(),
                $argument->getControllerName(),
            ));
        }

        $id = $map->id === null ? null : $request->attributes->get($map->id);
        if ($id !== null && !is_string($id) && !is_int($id)) {
            throw new NotFoundHttpException('No such record.');
        }

        // Changing the record (update): its form's guards and roles; else reading it.
        $context = $this->pages->open(
            $request,
            $map->resource,
            $id === null ? null : (string) $id,
            $map->update ? RecordScreen::Form : RecordScreen::Details,
        );
        if ($map->action !== null && $context->record instanceof RecordView) {
            try {
                $this->guards?->assertActionAllowed($map->action, $context->record);
            } catch (CrudFailure $failure) {
                throw new HttpException(
                    $failure->status,
                    $this->failureMessage?->of($failure) ?? $failure->getMessage(),
                    $failure,
                );
            }
        }

        return [$context];
    }
}
