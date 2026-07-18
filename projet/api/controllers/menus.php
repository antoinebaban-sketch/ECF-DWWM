<?php
/**
 * /api/menus/*
 * GET    /menus                   → liste avec filtres
 * GET    /menus/populaires        → 3 menus les + commandés
 * GET    /menus/{id}              → détail complet
 * POST   /menus                   → créer (employé/admin)
 * PUT    /menus/{id}              → modifier (employé/admin)
 * DELETE /menus/{id}              → supprimer (admin)
 */
function handle(string $method, array $parts, array $body): void
{
    $seg = $parts[1] ?? null;
    $id  = (is_numeric($seg)) ? (int)$seg : null;

    match(true) {
        $method === 'GET'    && $seg === 'populaires'   => getPopulaires(),
        $method === 'GET'    && $id === null            => getMenus(),
        $method === 'GET'    && $id !== null            => getMenu($id),
        $method === 'POST'   && $id === null            => creerMenu($body),
        $method === 'PUT'    && $id !== null            => modifierMenu($id, $body),
        $method === 'DELETE' && $id !== null            => supprimerMenu($id),
        default => jsonError('Route menus inconnue', 404)
    };
}

// ─── Liste des menus (avec filtres) ──────────────────────────────────────────

function getMenus(): void
{
    $pdo    = getPDO();
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['theme_id'])) {
        $where[]  = 'm.theme_id = ?';
        $params[] = (int)$_GET['theme_id'];
    }
    if (!empty($_GET['regime_id'])) {
        $where[]  = 'EXISTS (SELECT 1 FROM menu_regime mr WHERE mr.menu_id = m.menu_id AND mr.regime_id = ?)';
        $params[] = (int)$_GET['regime_id'];
    }
    if (isset($_GET['prix_min'])) {
        $where[]  = 'm.prix_par_personne >= ?';
        $params[] = (float)$_GET['prix_min'];
    }
    if (isset($_GET['prix_max'])) {
        $where[]  = 'm.prix_par_personne <= ?';
        $params[] = (float)$_GET['prix_max'];
    }
    if (!empty($_GET['personnes'])) {
        $where[]  = 'm.nombre_personne_mini <= ?';
        $params[] = (int)$_GET['personnes'];
    }
    if (!empty($_GET['q'])) {
        $where[]  = '(m.titre LIKE ? OR m.description LIKE ?)';
        $q        = '%' . $_GET['q'] . '%';
        $params[] = $q;
        $params[] = $q;
    }

    $sql = 'SELECT m.menu_id, m.titre, m.description, m.nombre_personne_mini,
                   m.prix_par_personne, m.quantite_restante, m.delai_prevenance,
                   t.libelle AS theme, t.theme_id,
                   (SELECT mi.image_url FROM menu_image mi WHERE mi.menu_id = m.menu_id LIMIT 1) AS image
            FROM menu m
            LEFT JOIN theme t ON t.theme_id = m.theme_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY m.menu_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $menus = $stmt->fetchAll();

    // Attacher les régimes à chaque menu
    if ($menus) {
        $ids   = implode(',', array_map('intval', array_column($menus, 'menu_id')));
        $regs  = $pdo->query("SELECT mr.menu_id, r.libelle FROM menu_regime mr JOIN regime r ON r.regime_id = mr.regime_id WHERE mr.menu_id IN ($ids)")->fetchAll();
        $byId  = [];
        foreach ($regs as $row) $byId[$row['menu_id']][] = $row['libelle'];
        foreach ($menus as &$m) $m['regimes'] = $byId[$m['menu_id']] ?? [];
    }

    jsonOk($menus);
}

// ─── 3 menus les plus commandés ───────────────────────────────────────────────

function getPopulaires(): void
{
    $pdo  = getPDO();
    $rows = $pdo->query('
        SELECT m.menu_id, m.titre, m.description, m.prix_par_personne, m.nombre_personne_mini,
               COUNT(c.commande_id) AS nb_commandes,
               (SELECT mi.image_url FROM menu_image mi WHERE mi.menu_id = m.menu_id LIMIT 1) AS image
        FROM menu m
        LEFT JOIN commande c ON c.menu_id = m.menu_id
        GROUP BY m.menu_id
        ORDER BY nb_commandes DESC, m.menu_id DESC
        LIMIT 3
    ')->fetchAll();

    jsonOk($rows);
}

// ─── Détail d'un menu ─────────────────────────────────────────────────────────

function getMenu(int $id): void
{
    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT m.*, t.libelle AS theme
        FROM menu m
        LEFT JOIN theme t ON t.theme_id = m.theme_id
        WHERE m.menu_id = ?
    ');
    $stmt->execute([$id]);
    $menu = $stmt->fetch();
    if (!$menu) jsonError('Menu introuvable', 404);

    // Images
    $imgStmt = $pdo->prepare('SELECT image_url FROM menu_image WHERE menu_id = ?');
    $imgStmt->execute([$id]);
    $menu['images'] = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    // Composition (plats)
    $platStmt = $pdo->prepare('
        SELECT p.plat_id, p.titre_plat, p.type_plat, p.description, p.image_url, p.prix_unitaire
        FROM plat p
        JOIN menu_composition mc ON mc.plat_id = p.plat_id
        WHERE mc.menu_id = ?
        ORDER BY FIELD(p.type_plat, \'Entrée\', \'Plat\', \'Dessert\', \'Boisson\')
    ');
    $platStmt->execute([$id]);
    $menu['plats'] = $platStmt->fetchAll();

    // Régimes
    $regStmt = $pdo->prepare('
        SELECT r.regime_id, r.libelle
        FROM regime r
        JOIN menu_regime mr ON mr.regime_id = r.regime_id
        WHERE mr.menu_id = ?
    ');
    $regStmt->execute([$id]);
    $menu['regimes'] = $regStmt->fetchAll();

    // Allergènes (agrégés des plats)
    $allerStmt = $pdo->prepare('
        SELECT DISTINCT a.allergene_id, a.libelle
        FROM allergene a
        JOIN plat_allergene_regime par ON par.allergene_id = a.allergene_id
        JOIN menu_composition mc ON mc.plat_id = par.plat_id
        WHERE mc.menu_id = ?
        ORDER BY a.libelle
    ');
    $allerStmt->execute([$id]);
    $menu['allergenes'] = $allerStmt->fetchAll();

    jsonOk($menu);
}

// ─── Créer un menu ────────────────────────────────────────────────────────────

function creerMenu(array $body): void
{
    roleRequired('employe', 'administrateur');
    require_fields($body, 'titre', 'description', 'prix_par_personne', 'nombre_personne_mini', 'quantite_restante', 'delai_prevenance');

    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        INSERT INTO menu (titre, description, prix_par_personne, nombre_personne_mini, quantite_restante, theme_id, delai_prevenance, conditions)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        sanitize($body['titre']),
        sanitize($body['description']),
        (float)$body['prix_par_personne'],
        (int)$body['nombre_personne_mini'],
        (int)$body['quantite_restante'],
        !empty($body['theme_id']) ? (int)$body['theme_id'] : null,
        (int)($body['delai_prevenance'] ?? 7),
        !empty($body['conditions']) ? sanitize($body['conditions']) : null,
    ]);
    $id = (int)$pdo->lastInsertId();

    // Associer des images
    if (!empty($body['images']) && is_array($body['images'])) {
        $imgStmt = $pdo->prepare('INSERT INTO menu_image (menu_id, image_url) VALUES (?, ?)');
        foreach ($body['images'] as $url) {
            if (is_string($url) && trim($url)) $imgStmt->execute([$id, trim($url)]);
        }
    }

    // Associer des plats
    if (!empty($body['plats']) && is_array($body['plats'])) {
        $compStmt = $pdo->prepare('INSERT IGNORE INTO menu_composition (menu_id, plat_id) VALUES (?, ?)');
        foreach ($body['plats'] as $platId) {
            $compStmt->execute([$id, (int)$platId]);
        }
    }

    // Associer des régimes
    if (!empty($body['regimes']) && is_array($body['regimes'])) {
        $regStmt = $pdo->prepare('INSERT IGNORE INTO menu_regime (menu_id, regime_id) VALUES (?, ?)');
        foreach ($body['regimes'] as $regId) {
            $regStmt->execute([$id, (int)$regId]);
        }
    }

    jsonOk(['menu_id' => $id, 'message' => 'Menu créé'], 201);
}

// ─── Modifier un menu ─────────────────────────────────────────────────────────

function modifierMenu(int $id, array $body): void
{
    roleRequired('employe', 'administrateur');

    $pdo = getPDO();
    $pdo->prepare('
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
    ')->execute([
        isset($body['titre'])       ? sanitize($body['titre'])       : null,
        isset($body['description']) ? sanitize($body['description']) : null,
        isset($body['prix_par_personne'])    ? (float)$body['prix_par_personne']    : null,
        isset($body['nombre_personne_mini']) ? (int)$body['nombre_personne_mini']   : null,
        isset($body['quantite_restante'])    ? (int)$body['quantite_restante']      : null,
        isset($body['theme_id'])             ? (int)$body['theme_id']               : null,
        isset($body['delai_prevenance'])     ? (int)$body['delai_prevenance']       : null,
        isset($body['conditions'])  ? sanitize($body['conditions'])  : null,
        $id,
    ]);

    // Remplacer les images si fournies
    if (isset($body['images']) && is_array($body['images'])) {
        $pdo->prepare('DELETE FROM menu_image WHERE menu_id = ?')->execute([$id]);
        $imgStmt = $pdo->prepare('INSERT INTO menu_image (menu_id, image_url) VALUES (?, ?)');
        foreach ($body['images'] as $url) {
            if (is_string($url) && trim($url)) $imgStmt->execute([$id, trim($url)]);
        }
    }

    jsonOk(['message' => 'Menu mis à jour']);
}

// ─── Supprimer un menu ────────────────────────────────────────────────────────

function supprimerMenu(int $id): void
{
    roleRequired('employe', 'administrateur');
    $pdo = getPDO();
    $pdo->prepare('DELETE FROM menu_composition WHERE menu_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM menu_image WHERE menu_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM menu_regime WHERE menu_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM menu WHERE menu_id = ?')->execute([$id]);
    jsonOk(['message' => 'Menu supprimé']);
}
