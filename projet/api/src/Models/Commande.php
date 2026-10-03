<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\StatutCommande;

/**
 * Entité Commande : porte les règles métier du cycle de vie d'une commande
 * (tarification, droits du client). Aucun SQL ici — l'accès aux données reste
 * dans CommandeRepository.
 */
final class Commande
{
    /** Délai accordé au client pour rendre le matériel prêté (pénalité de 600 € au-delà). */
    public const DELAI_RETOUR_MATERIEL_JOURS = 10;

    public function __construct(
        public readonly int $id,
        public readonly int $clientId,
        public readonly ?int $menuId,
        public readonly int $nombrePersonne,
        public readonly string $datePrestation,
        public readonly float $prixCommande,
        public readonly StatutCommande $statut,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['commande_id'],
            clientId: (int) $row['client_id'],
            menuId: isset($row['menu_id']) ? (int) $row['menu_id'] : null,
            nombrePersonne: (int) $row['nombre_personne'],
            datePrestation: (string) $row['date_prestation'],
            prixCommande: (float) $row['prix_commande'],
            statut: StatutCommande::from($row['statut_commande']),
        );
    }

    /**
     * Règle métier : le client ne peut modifier ou annuler librement sa
     * commande que tant qu'elle est encore "en_attente" (pas encore acceptée
     * par le personnel).
     */
    public function modifiableParClient(): bool
    {
        return $this->statut === StatutCommande::EnAttente;
    }

    /** Nombre de jours entiers écoulés depuis une date (ex. passage en "retour_materiel"). */
    public static function joursDepuis(string $date): int
    {
        return (int) floor((time() - strtotime($date)) / 86400);
    }

    /**
     * Calcule le prix total d'une commande.
     * Règles de l'énoncé :
     *  - réduction de 10 % si le nombre de convives dépasse d'au moins 5 le
     *    minimum requis par le menu ;
     *  - frais de livraison forfaitaires de 5 € + 0,59 €/km hors Bordeaux.
     *
     * @return array{prix_total: float, frais_livraison: float}
     */
    public static function calculerPrixTotal(
        float $prixParPersonne,
        int $nombrePersonne,
        int $nombrePersonneMini,
        float $distanceKm
    ): array {
        $prixUnitaire = $prixParPersonne;
        if ($nombrePersonne - $nombrePersonneMini >= 5) {
            $prixUnitaire *= 0.9;
        }

        $fraisLivraison = $distanceKm > 0 ? round(5 + 0.59 * $distanceKm, 2) : 0.0;

        return [
            'prix_total'      => round($prixUnitaire * $nombrePersonne + $fraisLivraison, 2),
            'frais_livraison' => $fraisLivraison,
        ];
    }
}
