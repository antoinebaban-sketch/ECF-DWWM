<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enum\StatutAvis;
use App\Enum\StatutCommande;
use App\Models\Avis;

/**
 * Tout le SQL du domaine "avis" (dépôt par le client, modération par le personnel).
 */
final class AvisRepository extends Repository
{
    /** Avis publiés (validés), du plus récent au plus ancien. */
    public function publies(?int $noteMin, int $limite): array
    {
        $where  = ['a.statut_validation = ?'];
        $params = [StatutAvis::Valide->value];

        if ($noteMin) {
            $where[]  = 'a.note >= ?';
            $params[] = $noteMin;
        }

        return $this->fetchAll('
            SELECT a.avis_id, a.note, a.description, a.date_avis,
                   u.prenom, LEFT(u.nom, 1) AS nom_initial
            FROM avis a
            JOIN utilisateur u ON u.utilisateur_id = a.client_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.date_avis DESC
            LIMIT ' . $limite, $params);
    }

    public function findDetail(int $id): ?array
    {
        return $this->fetchOne('
            SELECT a.*, u.prenom, u.nom, u.email,
                   m.titre AS titre_menu
            FROM avis a
            JOIN utilisateur u ON u.utilisateur_id = a.client_id
            LEFT JOIN commande c ON c.commande_id = a.commande_id
            LEFT JOIN menu m     ON m.menu_id      = c.menu_id
            WHERE a.avis_id = ?
        ', [$id]);
    }

    public function findByClient(int $clientId): array
    {
        return $this->fetchAll('
            SELECT a.avis_id, a.note, a.description, a.statut_validation, a.date_avis,
                   m.titre AS menu_titre, c.commande_id, c.date_prestation
            FROM avis a
            LEFT JOIN commande c ON c.commande_id = a.commande_id
            LEFT JOIN menu m ON m.menu_id = c.menu_id
            WHERE a.client_id = ?
            ORDER BY a.date_avis DESC
        ', [$clientId]);
    }

    /** File de modération de l'espace employé / admin. */
    public function findByStatut(string $statut): array
    {
        return $this->fetchAll('
            SELECT a.*, u.prenom, u.nom, u.email
            FROM avis a
            JOIN utilisateur u ON u.utilisateur_id = a.client_id
            WHERE a.statut_validation = ?
            ORDER BY a.date_avis DESC
        ', [$statut]);
    }

    /** Règle : on ne note qu'une commande à soi et déjà terminée. */
    public function commandeTermineeDuClient(int $commandeId, int $clientId): bool
    {
        return $this->fetchOne(
            'SELECT commande_id FROM commande WHERE commande_id = ? AND client_id = ? AND statut_commande = ?',
            [$commandeId, $clientId, StatutCommande::Terminee->value]
        ) !== null;
    }

    /** Règle : un seul avis par commande. */
    public function existePourCommande(int $commandeId, int $clientId): bool
    {
        return $this->fetchOne('SELECT avis_id FROM avis WHERE commande_id = ? AND client_id = ?', [$commandeId, $clientId]) !== null;
    }

    public function enregistrer(Avis $avis): int
    {
        return $this->insert(
            'INSERT INTO avis (client_id, commande_id, note, description, statut_validation) VALUES (?, ?, ?, ?, ?)',
            [$avis->clientId, $avis->commandeId, $avis->note, $avis->description, $avis->statut->value]
        );
    }

    public function changerStatut(int $id, StatutAvis $statut): void
    {
        $this->execute('UPDATE avis SET statut_validation = ? WHERE avis_id = ?', [$statut->value, $id]);
    }

    public function noteMoyenne(): ?float
    {
        $moyenne = $this->fetchValue('SELECT AVG(note) FROM avis WHERE statut_validation = ?', [StatutAvis::Valide->value]);

        return $moyenne ? round((float) $moyenne, 1) : null;
    }
}
