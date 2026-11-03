<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\CrudFailure;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A field of the dialog that asks before an action: the reason of a delete (an audit wants one), the status the
 * chosen records are set to, the salesperson they are given to. What is typed or chosen comes with the live action as
 * its "input" (CrudList::delete(), bulk(); BulkSelection::$input; RecordDeleting / RecordDeleted::$input).
 *
 *     new DeleteAction(input: new ConfirmInput('app.invoice.delete_reason'))
 *     new ConfirmInput('app.task.status', choices: ['app.status.open' => 'open', 'app.status.done' => 'done'])
 */
final readonly class ConfirmInput
{
    /** Translation keys of what is wrong with what was sent. */
    public const string REQUIRED = 'crud.error.input_required';

    public const string NOT_A_CHOICE = 'crud.error.input_choice';

    /** What is typed: a text of several lines, one line, a number or a day (Y-m-d). */
    public const array TYPES = ['text', 'line', 'number', 'date'];

    public const string NOT_A_NUMBER = 'crud.error.input_number';

    public const string NOT_A_DAY = 'crud.error.input_date';

    /**
     * @param string $label translation key of the field
     * @param bool $required whether the dialog is not answered "yes" without it
     * @param array<string, string> $choices a choice in place of a text: translation key of the label => value
     * @param string $type what is typed (TYPES): a "text" of several lines, a "line", a "number", a "date" (Y-m-d)
     * @param bool $translateChoices false when the labels of the choices are finished texts (the names of people)
     */
    public function __construct(
        public string $label,
        public bool $required = true,
        public array $choices = [],
        public string $type = 'text',
        public bool $translateChoices = true,
    ) {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'A dialog asks for one of %s, not "%s".',
                implode(', ', self::TYPES),
                $type,
            ));
        }
    }

    /**
     * What was sent, once checked on the server: trimmed, null for nothing. The dialog asks in the browser, which could
     * send anything - a request without the reason an audit wants, a status none of the choices.
     *
     * @throws CrudFailure 422 when it is required and missing, or it is none of the choices
     */
    public function accepted(?string $value): ?string
    {
        $value = $value === null || trim($value) === '' ? null : trim($value);
        if ($value === null) {
            return $this->required
                ? throw new CrudFailure(422, 'The dialog asks for a value.', translationKey: self::REQUIRED)
                : null;
        }
        if ($this->choices !== [] && !in_array($value, $this->choices, true)) {
            throw new CrudFailure(422, 'The value is none of the choices.', translationKey: self::NOT_A_CHOICE);
        }
        if ($this->choices === [] && $this->type === 'number' && !is_numeric($value)) {
            throw new CrudFailure(422, 'The value is no number.', translationKey: self::NOT_A_NUMBER);
        }
        if ($this->choices === [] && $this->type === 'date') {
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$day instanceof DateTimeImmutable || $day->format('Y-m-d') !== $value) {
                throw new CrudFailure(422, 'The value is no day.', translationKey: self::NOT_A_DAY);
            }
        }

        return $value;
    }

    /**
     * The attribute of the "confirm" controller describing it, translated.
     *
     * @return array<string, string>
     */
    public function attributes(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach ($this->choices as $label => $value) {
            $choices[] = ['label' => $this->translateChoices ? $translator->trans($label) : $label, 'value' => $value];
        }

        return ['data-confirm-input-value' => json_encode([
            'label' => $translator->trans($this->label),
            'required' => $this->required,
            'choices' => $choices,
            'type' => $this->type,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
    }
}
