<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enum\StatutCommande;

/**
 * Requêtes d'agrégation MySQL du tableau de bord administrateur.
 * (Le nombre de commandes par menu, lui, vient de MongoDB — voir AdminController.)
 */
final class StatistiqueRepository extends Repository
{
    /** Nombre de commandes et chiffre d'affaires par mois sur les 6 derniers mois. */
    public function chiffreAffairesParMois(): array
    {
        return $this->fetchAll("
            SELECT DATE_FORMAT(date_commande, '%Y-%m') AS mois, COUNT(*) AS nb_commandes, SUM(prix_commande) AS ca
            FROM commande
            WHERE statut_commande NOT IN (?)
              AND date_commande >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY mois
            ORDER BY mois ASC
        ", [StatutCommande::Annulee->value]);
    }

    /**
     * Chiffre d'affaires par menu, filtrable par menu et par période.
     *
     * @param array{menu_id?: mixed, date_debut?: mixed, date_fin?: mixed} $filtres
     */
    public function chiffreAffairesParMenu(array $filtres): array
    {
        $where  = ['c.statut_commande NOT IN (?)'];
        $params = [StatutCommande::Annulee->value];

        if (!empty($filtres['menu_id'])) {
            $where[]  = 'c.menu_id = ?';
            $params[] = (int) $filtres['menu_id'];
        }
        if (!empty($filtres['date_debut'])) {
            $where[]  = 'c.date_commande >= ?';
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $where[]  = 'c.date_commande <= ?';
            $params[] = $filtres['date_fin'];
        }

        return $this->fetchAll('
            SELECT m.menu_id, m.titre, COUNT(c.commande_id) AS nb, SUM(c.prix_commande) AS ca_menu
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY m.menu_id
            ORDER BY ca_menu DESC
        ', $params);
    }

    /** Compteurs globaux : total, en attente, annulées, CA hors annulations. */
    public function compteursGlobaux(): ?array
    {
        $annulee = StatutCommande::Annulee->value;

        return $this->fetchOne('
            SELECT
                COUNT(*) AS total_commandes,
                SUM(CASE WHEN statut_commande = ? THEN 1 ELSE 0 END) AS en_attente,
                SUM(CASE WHEN statut_commande = ? THEN 1 ELSE 0 END) AS annulees,
                SUM(CASE WHEN statut_commande NOT IN (?) THEN prix_commande ELSE 0 END) AS ca_total
            FROM commande
        ', [StatutCommande::EnAttente->value, $annulee, $annulee]);
    }
}
