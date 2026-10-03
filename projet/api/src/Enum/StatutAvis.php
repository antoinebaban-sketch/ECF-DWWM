<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de modération d'un avis (colonne avis.statut_validation) : un avis
 * n'est publié sur le site qu'une fois "validé" par un employé.
 */
enum StatutAvis: string
{
    case EnAttente = 'en_attente';
    case Valide    = 'validé';
    case Refuse    = 'refusé';

    /** Décisions qu'un employé peut prendre sur un avis en attente. */
    public static function decision(string $value): ?self
    {
        $statut = self::tryFrom($value);

        return $statut === self::EnAttente ? null : $statut;
    }
}
