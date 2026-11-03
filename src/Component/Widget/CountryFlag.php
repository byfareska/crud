<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Widget;

/**
 * The flag of a country as an emoji, from its ISO 3166-1 alpha-2 code: "PL" -> 🇵🇱 (two regional indicator letters).
 */
final class CountryFlag
{
    public static function of(?string $country): ?string
    {
        if ($country === null || preg_match('/^[A-Za-z]{2}$/', $country) !== 1) {
            return null;
        }
        $country = strtoupper($country);

        return mb_chr(0x1F1E6 + ord($country[0]) - ord('A')) . mb_chr(0x1F1E6 + ord($country[1]) - ord('A'));
    }
}
