<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rôles applicatifs (table "role"). La valeur est le libellé stocké en base
 * et en session ; id() donne la clé étrangère utilisateur.role_id.
 */
enum Role: string
{
    case Client         = 'client';
    case Employe        = 'employe';
    case Administrateur = 'administrateur';

    public function id(): int
    {
        return match ($this) {
            self::Client         => 1,
            self::Employe        => 2,
            self::Administrateur => 3,
        };
    }

    /** Employés et administrateurs : comptes du personnel. */
    public function estPersonnel(): bool
    {
        return $this !== self::Client;
    }
}
