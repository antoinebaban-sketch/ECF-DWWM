<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Les 8 étapes du cycle de vie d'une commande. Remplace les tableaux
 * STATUTS / STATUT_LABELS qui vivaient autrefois dans controllers/commandes.php.
 */
enum StatutCommande: string
{
    case EnAttente        = 'en_attente';
    case Acceptee         = 'acceptée';
    case EnPreparation    = 'en_preparation';
    case EnCoursLivraison = 'en_cours_livraison';
    case Livree           = 'livrée';
    case RetourMateriel   = 'retour_materiel';
    case Terminee         = 'terminée';
    case Annulee          = 'annulée';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente        => 'En attente',
            self::Acceptee         => 'Acceptée',
            self::EnPreparation    => 'En préparation',
            self::EnCoursLivraison => 'En cours de livraison',
            self::Livree           => 'Livrée',
            self::RetourMateriel   => 'En attente retour matériel',
            self::Terminee         => 'Terminée',
            self::Annulee          => 'Annulée',
        };
    }

    /** Variante tolérante de from() : renvoie null plutôt que de lever une erreur sur une valeur inconnue. */
    public static function tryLabel(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }

    /** @return string[] Les 8 valeurs, dans l'ordre du cycle de vie — utilisé pour valider le champ "statut" reçu de l'API. */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
