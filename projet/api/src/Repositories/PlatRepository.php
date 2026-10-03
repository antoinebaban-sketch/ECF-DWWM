<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Tout le SQL du domaine "plat" (et de la liaison plat_allergene_regime).
 */
final class PlatRepository extends Repository
{
    /** Liste des plats avec leurs allergènes et régimes, filtrable par type (Entrée, Plat...). */
    public function findAll(?string $type = null): array
    {
        return $this->fetchAll('
            SELECT p.*, GROUP_CONCAT(DISTINCT a.libelle ORDER BY a.libelle SEPARATOR \', \') AS allergenes,
                        GROUP_CONCAT(DISTINCT r.libelle ORDER BY r.libelle SEPARATOR \', \') AS regimes
            FROM plat p
            LEFT JOIN plat_allergene_regime par ON par.plat_id = p.plat_id
            LEFT JOIN allergene a ON a.allergene_id = par.allergene_id
            LEFT JOIN regime r ON r.regime_id = par.regime_id
            WHERE 1=1' . ($type ? ' AND p.type_plat = ?' : '') . '
            GROUP BY p.plat_id ORDER BY p.type_plat, p.titre_plat
        ', $type ? [$type] : []);
    }

    public function findDetail(int $id): ?array
    {
        $plat = $this->fetchOne('SELECT * FROM plat WHERE plat_id = ?', [$id]);
        if ($plat === null) {
            return null;
        }

        $plat['allergenes'] = $this->fetchAll('
            SELECT a.allergene_id, a.libelle
            FROM allergene a
            JOIN plat_allergene_regime par ON par.allergene_id = a.allergene_id
            WHERE par.plat_id = ?
            GROUP BY a.allergene_id
        ', [$id]);

        return $plat;
    }

    public function creer(array $data): int
    {
        return $this->insert(
            'INSERT INTO plat (titre_plat, type_plat, description, image_url, prix_unitaire) VALUES (?, ?, ?, ?, ?)',
            [$data['titre_plat'], $data['type_plat'], $data['description'], $data['image_url'], $data['prix_unitaire']]
        );
    }

    /** @param int[] $allergeneIds */
    public function associerAllergenes(int $platId, array $allergeneIds, int $regimeId): void
    {
        foreach ($allergeneIds as $allergeneId) {
            $this->execute(
                'INSERT IGNORE INTO plat_allergene_regime (plat_id, allergene_id, regime_id) VALUES (?, ?, ?)',
                [$platId, (int) $allergeneId, $regimeId]
            );
        }
    }

    /** Mise à jour partielle : un champ à null garde sa valeur actuelle. */
    public function modifier(int $id, array $data): void
    {
        $this->execute('
            UPDATE plat SET
                titre_plat    = COALESCE(?, titre_plat),
                type_plat     = COALESCE(?, type_plat),
                description   = COALESCE(?, description),
                prix_unitaire = COALESCE(?, prix_unitaire)
            WHERE plat_id = ?
        ', [$data['titre_plat'], $data['type_plat'], $data['description'], $data['prix_unitaire'], $id]);
    }

    /** Supprime le plat et ses liaisons (allergènes, composition des menus). */
    public function supprimer(int $id): void
    {
        $this->execute('DELETE FROM plat_allergene_regime WHERE plat_id = ?', [$id]);
        $this->execute('DELETE FROM menu_composition WHERE plat_id = ?', [$id]);
        $this->execute('DELETE FROM plat WHERE plat_id = ?', [$id]);
    }
}
