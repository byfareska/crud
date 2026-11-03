<?php

declare(strict_types=1);

namespace Byfareska\Crud\Page;

use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\OwnRecords;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The bundle's {@see CrudPages}.
 */
final readonly class DefaultCrudPages implements CrudPages
{
    public function __construct(
        private CrudRegistry $registry,
        private CrudRecords $records,
        private FailureMessage $failureMessage,
        private CrudAccess $access,
        private RecordGuards $guards,
        private ?OwnRecords $ownRecords = null,
    ) {
    }

    /**
     * @param string $resource the name of the resource
     * @param string|null $id of the record, as in the address; null on a page of the whole resource
     * @param RecordScreen $screen what the page is: the edit form asks the guards for updating, any other for reading
     *
     * @throws AccessDeniedHttpException the resource is not for the one asking
     * @throws HttpException the record cannot be read: 404 for one that does not exist, 403 for one refused
     */
    public function open(
        Request $request,
        string $resource,
        ?string $id = null,
        RecordScreen $screen = RecordScreen::Details,
    ): CrudContext {
        $crud = $this->registry->get($resource);
        if ($crud->own) {
            // The record of the one signed in: its address has no id.
            $this->access->assertGranted($crud);
            $id = $this->ownRecords?->idOf($crud) ?? $this->access->ownId();
        }
        // The list, the page to add a record, its details (and the pages about it), its edit form.
        $this->access->assertGranted($crud, match (true) {
            $id === null => $screen === RecordScreen::Form ? CrudOperation::Create : CrudOperation::List,
            default => $screen === RecordScreen::Form ? CrudOperation::Update : CrudOperation::Read,
        });

        $details = null;
        if ($id !== null && $crud->can(CrudOperation::Read)) {
            // Answer a link to a record that does not exist with a 404 page, not with an empty page or a broken form.
            try {
                $details = $this->records->details($crud, $id);
            } catch (CrudFailure $failure) {
                throw new HttpException($failure->status, $this->failureMessage->of($failure), $failure);
            }
        }

        $record = null;
        if ($id !== null) {
            $record = new RecordView($crud, $id, $details['item'] ?? [], $screen);
            $operation = $screen === RecordScreen::Form ? CrudOperation::Update : CrudOperation::Read;
            try {
                $this->guards->assertAllowed($operation, $record);
            } catch (CrudFailure $failure) {
                throw new HttpException($failure->status, $this->failureMessage->of($failure), $failure);
            }
        }

        $context = new CrudContext($crud, $id, $details, $record);
        $request->attributes->set(CrudContext::ATTRIBUTE, $context);

        return $context;
    }
}
