<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Tout le SQL du domaine "menu" (et des tables liées menu_image,
 * menu_composition, menu_regime).
 */
final class MenuRepository extends Repository
{
    // ─── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Catalogue filtrable (filtres combinables, tous optionnels).
     *
     * @param array{theme_id?: mixed, regime_id?: mixed, prix_min?: mixed, prix_max?: mixed, personnes?: mixed, q?: mixed} $filtres
     */
    public function findAll(array $filtres): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtres['theme_id'])) {
            $where[]  = 'm.theme_id = ?';
            $params[] = (int) $filtres['theme_id'];
        }
        if (!empty($filtres['regime_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM menu_regime mr WHERE mr.menu_id = m.menu_id AND mr.regime_id = ?)';
            $params[] = (int) $filtres['regime_id'];
        }
        if (isset($filtres['prix_min'])) {
            $where[]  = 'm.prix_par_personne >= ?';
            $params[] = (float) $filtres['prix_min'];
        }
        if (isset($filtres['prix_max'])) {
            $where[]  = 'm.prix_par_personne <= ?';
            $params[] = (float) $filtres['prix_max'];
        }
        if (!empty($filtres['personnes'])) {
            $where[]  = 'm.nombre_personne_mini <= ?';
            $params[] = (int) $filtres['personnes'];
        }
        if (!empty($filtres['q'])) {
            $where[]  = '(m.titre LIKE ? OR m.description LIKE ?)';
            $q        = '%' . $filtres['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $menus = $this->fetchAll('
            SELECT m.menu_id, m.titre, m.description, m.nombre_personne_mini,
                   m.prix_par_personne, m.quantite_restante, m.delai_prevenance,
                   t.libelle AS theme, t.theme_id,
                   (SELECT mi.image_url FROM menu_image mi WHERE mi.menu_id = m.menu_id LIMIT 1) AS image
            FROM menu m
            LEFT JOIN theme t ON t.theme_id = m.theme_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY m.menu_id DESC
        ', $params);

        return $this->attacherLibellesRegimes($menus);
    }

    /** Les 3 menus les plus commandés (page d'accueil). */
    public function populaires(int $limite = 3): array
    {
        return $this->fetchAll('
            SELECT m.menu_id, m.titre, m.description, m.prix_par_personne, m.nombre_personne_mini,
                   COUNT(c.commande_id) AS nb_commandes,
                   (SELECT mi.image_url FROM menu_image mi WHERE mi.menu_id = m.menu_id LIMIT 1) AS image
            FROM menu m
            LEFT JOIN commande c ON c.menu_id = m.menu_id
            GROUP BY m.menu_id
            ORDER BY nb_commandes DESC, m.menu_id DESC
            LIMIT ' . $limite
        );
    }

    /** Détail complet : menu + images, plats, régimes et allergènes agrégés des plats. */
    public function findDetail(int $id): ?array
    {
        $menu = $this->fetchOne('
            SELECT m.*, t.libelle AS theme
            FROM menu m
            LEFT JOIN theme t ON t.theme_id = m.theme_id
            WHERE m.menu_id = ?
        ', [$id]);
        if ($menu === null) {
            return null;
        }

        $images = $this->pdo->prepare('SELECT image_url FROM menu_image WHERE menu_id = ?');
        $images->execute([$id]);
        $menu['images'] = $images->fetchAll(PDO::FETCH_COLUMN);

        $menu['plats'] = $this->fetchAll('
            SELECT p.plat_id, p.titre_plat, p.type_plat, p.description, p.image_url, p.prix_unitaire
            FROM plat p
            JOIN menu_composition mc ON mc.plat_id = p.plat_id
            WHERE mc.menu_id = ?
            ORDER BY FIELD(p.type_plat, \'Entrée\', \'Plat\', \'Dessert\', \'Boisson\')
        ', [$id]);

        $menu['regimes'] = $this->fetchAll('
            SELECT r.regime_id, r.libelle
            FROM regime r
            JOIN menu_regime mr ON mr.regime_id = r.regime_id
            WHERE mr.menu_id = ?
        ', [$id]);

        $menu['allergenes'] = $this->fetchAll('
            SELECT DISTINCT a.allergene_id, a.libelle
            FROM allergene a
            JOIN plat_allergene_regime par ON par.allergene_id = a.allergene_id
            JOIN menu_composition mc ON mc.plat_id = par.plat_id
            WHERE mc.menu_id = ?
            ORDER BY a.libelle
        ', [$id]);

        return $menu;
    }

    // ─── Écriture ──────────────────────────────────────────────────────────────

    public function creer(array $data): int
    {
        return $this->insert('
            INSERT INTO menu (titre, description, prix_par_personne, nombre_personne_mini, quantite_restante, theme_id, delai_prevenance, conditions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ', [
            $data['titre'], $data['description'], $data['prix_par_personne'], $data['nombre_personne_mini'],
            $data['quantite_restante'], $data['theme_id'], $data['delai_prevenance'], $data['conditions'],
        ]);
    }

    /** Mise à jour partielle : un champ à null garde sa valeur actuelle. */
    public function modifier(int $id, array $data): void
    {
        $this->execute('
            UPDATE menu SET
                titre                = COALESCE(?, titre),
                description          = COALESCE(?, description),
                prix_par_personne    = COALESCE(?, prix_par_personne),
                nombre_personne_mini = COALESCE(?, nombre_personne_mini),
                quantite_restante    = COALESCE(?, quantite_restante),
                theme_id             = COALESCE(?, theme_id),
                delai_prevenance     = COALESCE(?, delai_prevenance),
                conditions           = COALESCE(?, conditions)
            WHERE menu_id = ?
        ', [
            $data['titre'], $data['description'], $data['prix_par_personne'], $data['nombre_personne_mini'],
            $data['quantite_restante'], $data['theme_id'], $data['delai_prevenance'], $data['conditions'], $id,
        ]);
    }

    /** @param string[] $urls */
    public function ajouterImages(int $menuId, array $urls): void
    {
        foreach ($urls as $url) {
            if (is_string($url) && trim($url) !== '') {
                $this->execute('INSERT INTO menu_image (menu_id, image_url) VALUES (?, ?)', [$menuId, trim($url)]);
            }
        }
    }

    /** @param string[] $urls */
    public function remplacerImages(int $menuId, array $urls): void
    {
        $this->execute('DELETE FROM menu_image WHERE menu_id = ?', [$menuId]);
        $this->ajouterImages($menuId, $urls);
    }

    /** @param int[] $platIds */
    public function ajouterPlats(int $menuId, array $platIds): void
    {
        foreach ($platIds as $platId) {
            $this->execute('INSERT IGNORE INTO menu_composition (menu_id, plat_id) VALUES (?, ?)', [$menuId, (int) $platId]);
        }
    }

    /** @param int[] $regimeIds */
    public function ajouterRegimes(int $menuId, array $regimeIds): void
    {
        foreach ($regimeIds as $regimeId) {
            $this->execute('INSERT IGNORE INTO menu_regime (menu_id, regime_id) VALUES (?, ?)', [$menuId, (int) $regimeId]);
        }
    }

    /** Supprime le menu et ses liaisons (images, composition, régimes). */
    public function supprimer(int $id): void
    {
        $this->execute('DELETE FROM menu_composition WHERE menu_id = ?', [$id]);
        $this->execute('DELETE FROM menu_image WHERE menu_id = ?', [$id]);
        $this->execute('DELETE FROM menu_regime WHERE menu_id = ?', [$id]);
        $this->execute('DELETE FROM menu WHERE menu_id = ?', [$id]);
    }

    // ─── Interne ───────────────────────────────────────────────────────────────

    /** Ajoute à chaque menu la liste des libellés de ses régimes (1 requête pour toute la liste). */
    private function attacherLibellesRegimes(array $menus): array
    {
        if (!$menus) {
            return $menus;
        }

        $ids          = array_map('intval', array_column($menus, 'menu_id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows         = $this->fetchAll(
            "SELECT mr.menu_id, r.libelle FROM menu_regime mr JOIN regime r ON r.regime_id = mr.regime_id WHERE mr.menu_id IN ($placeholders)",
            $ids
        );

        $parMenu = [];
        foreach ($rows as $row) {
            $parMenu[$row['menu_id']][] = $row['libelle'];
        }
        foreach ($menus as &$menu) {
            $menu['regimes'] = $parMenu[$menu['menu_id']] ?? [];
        }

        return $menus;
    }
}
