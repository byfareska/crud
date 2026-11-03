<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The button of a {@see RunAction} - in the list and its preview drawer the live action "run" of the list, on a page
 * of the record the Crud:Run component - and its doing, by the RecordActionRunner it names.
 */
final readonly class RunActionHandler implements RecordActionHandler, RunsRecordAction
{
    public function __construct(
        private TranslatorInterface $translator,
        #[AutowireLocator(RecordActionRunner::TAG)]
        private ContainerInterface $runners,
    ) {
    }

    public function button(RecordAction $action, RecordView $record): ActionButton
    {
        $action = $this->runAction($action);
        $inList = in_array($record->screen, [RecordScreen::List, RecordScreen::Preview], true);
        $label = $action->label ?? $record->crud->key('action.' . $action->name);

        return new ActionButton(
            $action->name,
            $label,
            $action->icon,
            'button',
            $inList ? $this->attributes($action, $label) + [
                'data-live-action-param' => 'run',
                'data-live-name-param' => $action->name,
                'data-live-id-param' => $record->id,
            ] : [],
            $action->inline,
            danger: $action->danger,
            component: $inList ? null : 'Crud:Run',
            props: $inList ? [] : [
                'resource' => $record->crud->name,
                'id' => $record->id,
                'action' => $action->name,
            ],
            tone: $action->tone,
        );
    }

    /**
     * The attributes of the button that does it: asked first ("confirm" controller) when it has a question.
     *
     * @return array<string, string>
     */
    public function attributes(RunAction $action, string $label): array
    {
        if ($action->message === null && !$action->input instanceof ConfirmInput) {
            return ['type' => 'button', 'data-action' => 'live#action'];
        }

        return array_filter([
            'type' => 'button',
            'data-controller' => 'confirm',
            'data-action' => 'confirm#ask live#action',
            'data-confirm-message-value' => $this->translator->trans($action->message ?? $label),
            'data-confirm-hint-value' => $action->hint !== null ? $this->translator->trans($action->hint) : null,
            'data-confirm-accept-value' => $this->translator->trans($label),
            ...$action->input?->attributes($this->translator) ?? [],
        ], static fn (?string $value): bool => $value !== null);
    }

    public function run(RecordAction $action, RecordView $record, ?string $input): ?BulkResult
    {
        $action = $this->runAction($action);
        if (!$this->runners->has($action->runner)) {
            throw new LogicException(sprintf(
                'The action "%s" is done by %s, which is no service tagged "%s" - is it autoconfigured?',
                $action->name,
                $action->runner,
                RecordActionRunner::TAG,
            ));
        }
        $runner = $this->runners->get($action->runner);
        assert($runner instanceof RecordActionRunner);

        return $runner->run($action, $record, $input);
    }

    public function updates(RecordAction $action): bool
    {
        return $this->runAction($action)->update;
    }

    private function runAction(RecordAction $action): RunAction
    {
        return $action instanceof RunAction ? $action : throw new InvalidArgumentException(sprintf(
            '%s makes the buttons of a %s.',
            self::class,
            RunAction::class,
        ));
    }
}
