<?php
/**
 * /api/plats/*
 */
function handle(string $method, array $parts, array $body): void
{
    $seg = $parts[1] ?? null;
    $id  = is_numeric($seg) ? (int)$seg : null;

    match(true) {
        $method === 'GET'    && $id === null => getListe(),
        $method === 'GET'    && $id !== null => getPlat($id),
        $method === 'POST'   && $id === null => creer($body),
        $method === 'PUT'    && $id !== null => modifier($id, $body),
        $method === 'DELETE' && $id !== null => supprimer($id),
        default => jsonError('Route plats inconnue', 404)
    };
}

function getListe(): void
{
    $pdo  = getPDO();
    $type = $_GET['type'] ?? null;
    $sql  = 'SELECT p.*, GROUP_CONCAT(DISTINCT a.libelle ORDER BY a.libelle SEPARATOR ", ") AS allergenes,
                    GROUP_CONCAT(DISTINCT r.libelle ORDER BY r.libelle SEPARATOR ", ") AS regimes
             FROM plat p
             LEFT JOIN plat_allergene_regime par ON par.plat_id = p.plat_id
             LEFT JOIN allergene a ON a.allergene_id = par.allergene_id
             LEFT JOIN regime r ON r.regime_id = par.regime_id
             WHERE 1=1' . ($type ? ' AND p.type_plat = ?' : '') .
           ' GROUP BY p.plat_id ORDER BY p.type_plat, p.titre_plat';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($type ? [$type] : []);
    jsonOk($stmt->fetchAll());
}

function getPlat(int $id): void
{
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM plat WHERE plat_id = ?');
    $stmt->execute([$id]);
    $plat = $stmt->fetch();
    if (!$plat) jsonError('Plat introuvable', 404);

    $ar = $pdo->prepare('SELECT a.allergene_id, a.libelle FROM allergene a JOIN plat_allergene_regime par ON par.allergene_id = a.allergene_id WHERE par.plat_id = ? GROUP BY a.allergene_id');
    $ar->execute([$id]);
    $plat['allergenes'] = $ar->fetchAll();

    jsonOk($plat);
}

function creer(array $body): void
{
    roleRequired('employe', 'administrateur');
    require_fields($body, 'titre_plat', 'type_plat', 'prix_unitaire');

    $pdo  = getPDO();
    $pdo->prepare('INSERT INTO plat (titre_plat, type_plat, description, image_url, prix_unitaire) VALUES (?, ?, ?, ?, ?)')
        ->execute([sanitize($body['titre_plat']), $body['type_plat'], sanitize($body['description'] ?? ''), $body['image_url'] ?? null, (float)$body['prix_unitaire']]);

    $id = (int)$pdo->lastInsertId();

    if (!empty($body['allergenes']) && is_array($body['allergenes'])) {
        $r = !empty($body['regime_id']) ? (int)$body['regime_id'] : 1;
        $s = $pdo->prepare('INSERT IGNORE INTO plat_allergene_regime (plat_id, allergene_id, regime_id) VALUES (?, ?, ?)');
        foreach ($body['allergenes'] as $aId) $s->execute([$id, (int)$aId, $r]);
    }

    jsonOk(['plat_id' => $id], 201);
}

function modifier(int $id, array $body): void
{
    roleRequired('employe', 'administrateur');
    $pdo = getPDO();
    $pdo->prepare('UPDATE plat SET titre_plat = COALESCE(?, titre_plat), type_plat = COALESCE(?, type_plat), description = COALESCE(?, description), prix_unitaire = COALESCE(?, prix_unitaire) WHERE plat_id = ?')
        ->execute([
            isset($body['titre_plat'])  ? sanitize($body['titre_plat'])  : null,
            $body['type_plat']          ?? null,
            isset($body['description']) ? sanitize($body['description'])  : null,
            isset($body['prix_unitaire']) ? (float)$body['prix_unitaire'] : null,
            $id,
        ]);
    jsonOk(['message' => 'Plat modifié']);
}

function supprimer(int $id): void
{
    roleRequired('employe', 'administrateur');
    $pdo = getPDO();
    $pdo->prepare('DELETE FROM plat_allergene_regime WHERE plat_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM menu_composition WHERE plat_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM plat WHERE plat_id = ?')->execute([$id]);
    jsonOk(['message' => 'Plat supprimé']);
}
