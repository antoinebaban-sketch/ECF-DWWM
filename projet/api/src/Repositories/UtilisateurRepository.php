<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enum\Role;
use App\Models\Utilisateur;

/**
 * Tout le SQL des comptes utilisateurs (table utilisateur et préférences
 * alimentaires utilisateur_regime / utilisateur_allergene).
 */
final class UtilisateurRepository extends Repository
{
    private const SELECT_AVEC_ROLE = '
        SELECT u.utilisateur_id, u.email, u.password, u.prenom, u.nom, u.statut_compte,
               r.libelle AS role
        FROM utilisateur u
        JOIN role r ON r.role_id = u.role_id';

    // ─── Lecture ───────────────────────────────────────────────────────────────

    public function findByEmail(string $email): ?Utilisateur
    {
        $row = $this->fetchOne(self::SELECT_AVEC_ROLE . ' WHERE u.email = ?', [$email]);

        return $row ? Utilisateur::fromRow($row) : null;
    }

    public function findById(int $id): ?Utilisateur
    {
        $row = $this->fetchOne(self::SELECT_AVEC_ROLE . ' WHERE u.utilisateur_id = ?', [$id]);

        return $row ? Utilisateur::fromRow($row) : null;
    }

    public function emailExiste(string $email, ?int $saufId = null): bool
    {
        if ($saufId === null) {
            return $this->fetchOne('SELECT utilisateur_id FROM utilisateur WHERE email = ?', [$email]) !== null;
        }

        return $this->fetchOne('SELECT 1 FROM utilisateur WHERE email = ? AND utilisateur_id != ?', [$email, $saufId]) !== null;
    }

    public function profil(int $id): ?array
    {
        return $this->fetchOne('
            SELECT u.utilisateur_id, u.email, u.prenom, u.nom, u.telephone, u.ville, u.pays, u.adresse,
                   r.libelle AS role, u.date_creation
            FROM utilisateur u
            JOIN role r ON r.role_id = u.role_id
            WHERE u.utilisateur_id = ?
        ', [$id]);
    }

    /** Liste pour l'espace admin, éventuellement filtrée par rôle. */
    public function findAll(?string $role = null): array
    {
        $sql = 'SELECT u.utilisateur_id, u.email, u.prenom, u.nom, u.telephone, u.statut_compte, u.date_creation,
                       r.libelle AS role
                FROM utilisateur u
                JOIN role r ON r.role_id = u.role_id'
             . ($role ? ' WHERE r.libelle = ?' : '')
             . ' ORDER BY u.date_creation DESC';

        return $this->fetchAll($sql, $role ? [$role] : []);
    }

    public function findIdByValidResetToken(string $token): ?int
    {
        $id = $this->fetchValue('
            SELECT utilisateur_id FROM utilisateur
            WHERE reset_token = ? AND reset_token_expires > NOW()
        ', [$token]);

        return $id === false ? null : (int) $id;
    }

    public function nombreClientsActifs(): int
    {
        return (int) $this->fetchValue('SELECT COUNT(*) FROM utilisateur WHERE role_id = ? AND statut_compte = 1', [Role::Client->id()]);
    }

    // ─── Écriture ──────────────────────────────────────────────────────────────

    /** Inscription d'un client (rôle "client", compte actif, consentement RGPD donné). */
    public function creerClient(array $data, string $passwordHash): int
    {
        return $this->insert('
            INSERT INTO utilisateur (email, password, prenom, nom, telephone, ville, pays, adresse, role_id, statut_compte, date_creation, consentement_rgpd)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE(), 1)
        ', [
            $data['email'], $passwordHash, $data['prenom'], $data['nom'],
            $data['telephone'], $data['ville'], $data['pays'], $data['adresse'],
            Role::Client->id(),
        ]);
    }

    /** Compte employé créé par l'admin : mot de passe inconnu + jeton d'activation. */
    public function creerEmploye(array $data, string $passwordHash, string $token, string $expires): int
    {
        return $this->insert('
            INSERT INTO utilisateur (email, password, prenom, nom, telephone, ville, pays, adresse, role_id, statut_compte, date_creation, consentement_rgpd, reset_token, reset_token_expires)
            VALUES (?, ?, ?, ?, ?, \'\', \'France\', \'\', ?, 1, CURDATE(), 1, ?, ?)
        ', [
            $data['email'], $passwordHash, $data['prenom'], $data['nom'], $data['telephone'],
            Role::Employe->id(), $token, $expires,
        ]);
    }

    /** Met à jour les champs non vides du profil (les champs vides gardent leur valeur). */
    public function modifierProfil(int $id, array $data): void
    {
        $this->execute("
            UPDATE utilisateur SET
                prenom    = COALESCE(NULLIF(?, ''), prenom),
                nom       = COALESCE(NULLIF(?, ''), nom),
                email     = COALESCE(NULLIF(?, ''), email),
                telephone = COALESCE(NULLIF(?, ''), telephone),
                ville     = COALESCE(NULLIF(?, ''), ville),
                pays      = COALESCE(NULLIF(?, ''), pays),
                adresse   = COALESCE(NULLIF(?, ''), adresse)
            WHERE utilisateur_id = ?
        ", [
            $data['prenom'], $data['nom'], $data['email'], $data['telephone'],
            $data['ville'], $data['pays'], $data['adresse'], $id,
        ]);
    }

    public function changerMotDePasse(int $id, string $passwordHash): void
    {
        $this->execute(
            'UPDATE utilisateur SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE utilisateur_id = ?',
            [$passwordHash, $id]
        );
    }

    public function enregistrerResetToken(int $id, string $token, string $expires): void
    {
        $this->execute('UPDATE utilisateur SET reset_token = ?, reset_token_expires = ? WHERE utilisateur_id = ?', [$token, $expires, $id]);
    }

    public function changerStatut(int $id, bool $actif): void
    {
        $this->execute('UPDATE utilisateur SET statut_compte = ? WHERE utilisateur_id = ?', [$actif ? 1 : 0, $id]);
    }

    /**
     * Pseudonymisation (RGPD Art. 17) : les données personnelles sont effacées
     * mais la ligne est conservée pour garder l'historique des commandes
     * (obligation comptable — Art. L.123-22 C. com.).
     */
    public function pseudonymiser(int $id, string $emailPseudonyme, string $libelle): void
    {
        $this->execute('
            UPDATE utilisateur SET
                email      = ?,
                password   = \'\',
                prenom     = ?,
                nom        = ?,
                telephone  = \'\',
                adresse    = \'\',
                ville      = \'\',
                statut_compte = 0,
                reset_token = NULL,
                reset_token_expires = NULL
            WHERE utilisateur_id = ?
        ', [$emailPseudonyme, $libelle, $libelle, $id]);
    }

    // ─── Préférences alimentaires ──────────────────────────────────────────────

    public function regimesPreferes(int $id): array
    {
        return $this->fetchAll('
            SELECT r.regime_id, r.libelle
            FROM utilisateur_regime ur
            JOIN regime r ON r.regime_id = ur.regime_id
            WHERE ur.utilisateur_id = ?
            ORDER BY r.regime_id
        ', [$id]);
    }

    public function allergenesDeclares(int $id): array
    {
        return $this->fetchAll('
            SELECT a.allergene_id, a.libelle
            FROM utilisateur_allergene ua
            JOIN allergene a ON a.allergene_id = ua.allergene_id
            WHERE ua.utilisateur_id = ?
            ORDER BY a.allergene_id
        ', [$id]);
    }

    /**
     * Remplace toutes les préférences en une transaction (tout ou rien).
     *
     * @param int[] $regimes
     * @param int[] $allergenes
     */
    public function remplacerPreferences(int $id, array $regimes, array $allergenes): void
    {
        $this->transaction(function () use ($id, $regimes, $allergenes): void {
            $this->execute('DELETE FROM utilisateur_regime    WHERE utilisateur_id = ?', [$id]);
            $this->execute('DELETE FROM utilisateur_allergene WHERE utilisateur_id = ?', [$id]);

            foreach (array_filter($regimes, static fn (int $r) => $r > 0) as $regimeId) {
                $this->execute('INSERT IGNORE INTO utilisateur_regime (utilisateur_id, regime_id) VALUES (?, ?)', [$id, $regimeId]);
            }
            foreach (array_filter($allergenes, static fn (int $a) => $a > 0) as $allergeneId) {
                $this->execute('INSERT IGNORE INTO utilisateur_allergene (utilisateur_id, allergene_id) VALUES (?, ?)', [$id, $allergeneId]);
            }
        });
    }

    // ─── Export RGPD (Art. 20) ─────────────────────────────────────────────────

    public function exportProfil(int $id): ?array
    {
        return $this->fetchOne('
            SELECT u.utilisateur_id, u.email, u.prenom, u.nom, u.telephone, u.ville, u.pays, u.adresse, u.date_creation, r.libelle AS role
            FROM utilisateur u JOIN role r ON r.role_id = u.role_id
            WHERE u.utilisateur_id = ?
        ', [$id]);
    }

    public function exportCommandes(int $id): array
    {
        return $this->fetchAll('
            SELECT c.commande_id, c.date_commande, c.date_prestation, c.nombre_personne,
                   c.prix_commande, c.statut_commande, c.adresse_livraison, m.titre AS menu
            FROM commande c LEFT JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.client_id = ? ORDER BY c.date_commande DESC
        ', [$id]);
    }

    public function exportAvis(int $id): array
    {
        return $this->fetchAll('SELECT avis_id, note, description, statut_validation, date_avis FROM avis WHERE client_id = ?', [$id]);
    }
}
