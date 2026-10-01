<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Tout le SQL du domaine "commande" (et des tables liées historique_commande,
 * livraison) vit ici. CommandeController et Commande (le modèle) n'exécutent
 * aucune requête directement.
 */
final class CommandeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    // ─── Transaction (passage de commande : vérif. stock + insertions liées) ──

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    // ─── Lecture ───────────────────────────────────────────────────────────────

    public function findByClient(int $clientId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.commande_id, c.nombre_personne, c.date_commande, c.date_prestation,
                   c.prix_commande, c.statut_commande, c.adresse_livraison,
                   c.motif_annulation, c.mode_contact,
                   m.titre AS menu_titre, m.prix_par_personne,
                   t.libelle AS theme
            FROM commande c
            LEFT JOIN menu m ON m.menu_id = c.menu_id
            LEFT JOIN theme t ON t.theme_id = c.theme_id
            WHERE c.client_id = ?
            ORDER BY c.date_commande DESC
        ');
        $stmt->execute([$clientId]);

        return $stmt->fetchAll();
    }

    /** @param array{statut?: string, client_id?: int, q?: string} $filtres */
    public function findAll(array $filtres): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtres['statut'])) {
            $where[]  = 'c.statut_commande = ?';
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['client_id'])) {
            $where[]  = 'c.client_id = ?';
            $params[] = (int) $filtres['client_id'];
        }
        if (!empty($filtres['q'])) {
            $where[]  = '(u.prenom LIKE ? OR u.nom LIKE ? OR u.email LIKE ?)';
            $q        = '%' . $filtres['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql = 'SELECT c.commande_id, c.nombre_personne, c.date_commande, c.date_prestation,
                       c.prix_commande, c.statut_commande, c.adresse_livraison, c.motif_annulation,
                       m.titre AS menu_titre,
                       u.prenom, u.nom, u.email, u.telephone,
                       h.date_retour_materiel
                FROM commande c
                LEFT JOIN menu m ON m.menu_id = c.menu_id
                LEFT JOIN utilisateur u ON u.utilisateur_id = c.client_id
                LEFT JOIN (
                    SELECT commande_id, MAX(date_changement_statut) AS date_retour_materiel
                    FROM historique_commande
                    WHERE statut = \'retour_materiel\'
                    GROUP BY commande_id
                ) h ON h.commande_id = c.commande_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.date_commande DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findDetail(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*, m.titre AS menu_titre, m.prix_par_personne,
                   u.prenom, u.nom, u.email, u.telephone,
                   t.libelle AS theme
            FROM commande c
            LEFT JOIN menu m ON m.menu_id = c.menu_id
            LEFT JOIN utilisateur u ON u.utilisateur_id = c.client_id
            LEFT JOIN theme t ON t.theme_id = c.theme_id
            WHERE c.commande_id = ?
        ');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM commande WHERE commande_id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByIdAndClient(int $id, int $clientId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM commande WHERE commande_id = ? AND client_id = ?');
        $stmt->execute([$id, $clientId]);

        return $stmt->fetch() ?: null;
    }

    public function findForModification(int $id, int $clientId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*, m.prix_par_personne, m.nombre_personne_mini
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.commande_id = ? AND c.client_id = ?
        ');
        $stmt->execute([$id, $clientId]);

        return $stmt->fetch() ?: null;
    }

    public function findFacture(int $id, int $clientId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*, m.titre AS menu_titre, u.prenom, u.nom, u.email, u.adresse, u.ville
            FROM commande c
            LEFT JOIN menu m ON m.menu_id = c.menu_id
            LEFT JOIN utilisateur u ON u.utilisateur_id = c.client_id
            WHERE c.commande_id = ? AND c.client_id = ?
        ');
        $stmt->execute([$id, $clientId]);

        return $stmt->fetch() ?: null;
    }

    public function appartientAuClient(int $id, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('SELECT commande_id FROM commande WHERE commande_id = ? AND client_id = ?');
        $stmt->execute([$id, $clientId]);

        return (bool) $stmt->fetch();
    }

    public function historique(int $id): array
    {
        $stmt = $this->pdo->prepare('
            SELECT statut, date_changement_statut
            FROM historique_commande
            WHERE commande_id = ?
            ORDER BY date_changement_statut ASC
        ');
        $stmt->execute([$id]);

        return $stmt->fetchAll();
    }

    /** @return array{prenom: string, nom: string, email: string}|null */
    public function findClientContact(int $clientId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT prenom, nom, email FROM utilisateur WHERE utilisateur_id = ?');
        $stmt->execute([$clientId]);

        return $stmt->fetch() ?: null;
    }

    public function findMenuForUpdate(int $menuId): ?array
    {
        // FOR UPDATE verrouille la ligne : empêche deux commandes simultanées
        // de décrémenter le même stock au-delà de zéro (condition de course).
        $stmt = $this->pdo->prepare('SELECT * FROM menu WHERE menu_id = ? FOR UPDATE');
        $stmt->execute([$menuId]);

        return $stmt->fetch() ?: null;
    }

    // ─── Écriture ──────────────────────────────────────────────────────────────

    public function creer(array $data): int
    {
        $this->pdo->prepare('
            INSERT INTO commande
                (client_id, menu_id, nombre_personne, date_commande, date_prestation,
                 prix_commande, statut_commande, adresse_livraison, theme_id)
            VALUES (?, ?, ?, CURDATE(), ?, ?, \'en_attente\', ?, ?)
        ')->execute([
            $data['client_id'],
            $data['menu_id'],
            $data['nombre_personne'],
            $data['date_prestation'],
            $data['prix_commande'],
            $data['adresse_livraison'],
            $data['theme_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function ajouterHistorique(int $commandeId, string $statut): void
    {
        $this->pdo->prepare('INSERT INTO historique_commande (commande_id, statut) VALUES (?, ?)')
            ->execute([$commandeId, $statut]);
    }

    public function creerLivraison(int $commandeId, string $datePrestation, float $distanceKm, float $fraisLivraison): void
    {
        $this->pdo->prepare('
            INSERT INTO livraison (commande_id, date_livraison, distance_km, frais_livraison)
            VALUES (?, ?, ?, ?)
        ')->execute([$commandeId, $datePrestation, $distanceKm, $fraisLivraison]);
    }

    public function decrementerStock(int $menuId): void
    {
        $this->pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id = ?')
            ->execute([$menuId]);
    }

    public function incrementerStock(int $menuId): void
    {
        $this->pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante + 1 WHERE menu_id = ?')
            ->execute([$menuId]);
    }

    public function updateStatut(int $id, string $statut, int $employeId, ?string $motif, ?string $modeContact): void
    {
        $this->pdo->prepare('
            UPDATE commande SET
                statut_commande   = ?,
                employe_id        = ?,
                motif_annulation  = COALESCE(?, motif_annulation),
                mode_contact      = COALESCE(?, mode_contact)
            WHERE commande_id = ?
        ')->execute([$statut, $employeId, $motif, $modeContact, $id]);
    }

    public function annuler(int $id): void
    {
        $this->pdo->prepare('UPDATE commande SET statut_commande = \'annulée\' WHERE commande_id = ?')
            ->execute([$id]);
    }

    public function modifier(int $id, int $nombrePersonne, ?string $datePrestation, ?string $adresseLivraison, float $prixTotal): void
    {
        $this->pdo->prepare('
            UPDATE commande SET
                nombre_personne    = ?,
                date_prestation    = COALESCE(?, date_prestation),
                adresse_livraison  = COALESCE(?, adresse_livraison),
                prix_commande      = ?
            WHERE commande_id = ?
        ')->execute([$nombrePersonne, $datePrestation, $adresseLivraison, $prixTotal, $id]);
    }
}
