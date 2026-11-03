<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * Something done to one record on the server, by a click (asked first, when it has a question): "approve the
 * invoice", "archive", "send again" - the counterpart of a bulk action for one record, done by a service of the
 * application ({@see RecordActionRunner}):
 *
 *     actions: [new RunAction('approve', 'check2', ApproveInvoice::class, message: 'app.invoice.approve_question')]
 *
 * In the list and its preview drawer it is the live action "run" of the list; on a page of the record the Crud:Run
 * component. Either way it goes through RecordActions::run(): offered to the one asking (role), what its dialog asks
 * for checked (input), then RecordChanges - a transaction, the guards of Update (unless $operation says otherwise)
 * and of the action (RecordActionGuard by its name), RecordChanging and RecordChanged - and the runner. Never a link:
 * nothing is changed by a GET a browser could prefetch.
 */
final readonly class RunAction implements PrioritizedAction, AsksForInput
{
    /**
     * @param string $name lower-case; part of the translation key crud.<resource>.action.<name>
     * @param string $icon a Bootstrap Icons name
     * @param class-string<RecordActionRunner> $runner the service that does it
     * @param string|null $message translation key of the question asked first; null = not asked (unless $input)
     * @param string|null $hint translation key of what is said under the question
     * @param ConfirmInput|null $input a field of the question: a comment, a status - given to the runner, checked first
     * @param string|null $role only somebody with this role is offered the action, and may do it
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param bool $danger it destroys something: drawn in red
     * @param string|null $label translation key of its text; null = crud.<resource>.action.<name>
     * @param bool $update whether the guards of Update are asked too (what may not be edited may not be changed so);
     *     false = only those of the action
     * @param list<string> $fields what it needs of an item of the list besides its columns (for a RecordActionGuard)
     * @param int $priority where it stands among the actions of a record, lower first
     * @param ActionTone|null $tone the colour of its button; null = Secondary (Danger when $danger)
     */
    public function __construct(
        public string $name,
        public string $icon,
        public string $runner,
        public ?string $message = null,
        public ?string $hint = null,
        public ?ConfirmInput $input = null,
        public ?string $role = null,
        public bool $inline = false,
        public bool $danger = false,
        public ?string $label = null,
        public bool $update = true,
        public array $fields = [],
        public int $priority = 0,
        public ?ActionTone $tone = null,
    ) {
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function handler(): string
    {
        return RunActionHandler::class;
    }
}
