<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Dto\DtoRecords;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The text of a toast of a resource ("created", "updated", "deleted"): crud.<name>.toast.<what> when the application
 * has it ("The invoice was issued"), else the bundle's crud.toast.<what>.
 */
final readonly class CrudToast
{
    /**
     * @param array<string, scalar|null> $parameters of the text: {id} and the values of the record ({number},
     *     {name}) - "Faktura {number} wystawiona"
     */
    public static function text(
        TranslatorInterface $translator,
        AsCrud $crud,
        string $what,
        array $parameters = [],
    ): string {
        $key = $crud->key('toast.' . $what);
        $text = $translator->trans($key, $parameters);

        return $text !== $key ? $text : $translator->trans('crud.toast.' . $what, $parameters);
    }

    /**
     * The parameters of a toast about a record: its id and its plain values (of an entity, a DTO, an item of a list).
     *
     * @return array<string, scalar|null>
     */
    public static function parametersOf(?string $id, mixed $record): array
    {
        $parameters = [];
        foreach (DtoRecords::propertiesOf($record) as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $parameters[$name] = $value;
            } elseif ($value instanceof \BackedEnum) {
                $parameters[$name] = $value->value;
            } elseif ($value instanceof \Stringable) {
                $parameters[$name] = (string) $value;
            }
        }

        return ['id' => $id] + $parameters;
    }
}
