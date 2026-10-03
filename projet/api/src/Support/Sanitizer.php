<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nettoyage des entrées utilisateur avant traitement ou stockage. L'échappement
 * à l'affichage reste fait côté front (textContent / escapeHtml) et dans les
 * emails (htmlspecialchars) : défense en profondeur contre le XSS.
 */
final class Sanitizer
{
    /** Chaîne libre : supprime les balises HTML et les espaces extrêmes. */
    public static function text(string $value): string
    {
        return trim(strip_tags($value));
    }

    /** Variante qui laisse passer null (champ optionnel absent). */
    public static function textOrNull(mixed $value): ?string
    {
        return $value === null ? null : self::text((string) $value);
    }

    public static function email(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function isEmail(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
