<?php
/**
 * /api/avis/*
 * GET /avis                       → avis validés (public)
 * POST /avis                      → déposer un avis (client connecté)
 * PUT /avis/{id}/validation       → valider / refuser (employé/admin)
 */
function handle(string $method, array $parts, array $body): void
{
    $seg = $parts[1] ?? null;
    $id  = is_numeric($seg) ? (int)$seg : null;
    $sub = $id ? ($parts[2] ?? null) : null;

    match(true) {
        $method === 'GET'  && $seg === 'mes-avis'   => mesAvis(),
        $method === 'GET'  && $id === null          => getAvis(),
        $method === 'GET'  && $id !== null          => getAvisDetail($id),
        $method === 'POST' && $id === null          => deposerAvis($body),
        $method === 'PUT'  && $sub === 'validation' => validerAvis($id, $body),
        default => jsonError('Route avis inconnue', 404)
    };
}

function getAvis(): void
{
    $pdo    = getPDO();
    $where  = ["a.statut_validation = 'validé'"];
    $params = [];

    if (!empty($_GET['note_min'])) {
        $where[]  = 'a.note >= ?';
        $params[] = (int)$_GET['note_min'];
    }

    $limit  = min((int)($_GET['limit'] ?? 10), 50);
    $sql    = 'SELECT a.avis_id, a.note, a.description, a.date_avis,
                      u.prenom, LEFT(u.nom, 1) AS nom_initial
               FROM avis a
               JOIN utilisateur u ON u.utilisateur_id = a.client_id
               WHERE ' . implode(' AND ', $where) . '
               ORDER BY a.date_avis DESC
               LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonOk($stmt->fetchAll());
}

function getAvisDetail(int $id): void
{
    roleRequired('employe', 'administrateur');
    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT a.*, u.prenom, u.nom, u.email,
               m.titre AS titre_menu
        FROM avis a
        JOIN utilisateur u ON u.utilisateur_id = a.client_id
        LEFT JOIN commande c ON c.commande_id = a.commande_id
        LEFT JOIN menu m     ON m.menu_id      = c.menu_id
        WHERE a.avis_id = ?
    ');
    $stmt->execute([$id]);
    $avis = $stmt->fetch();
    if (!$avis) jsonError('Avis introuvable', 404);
    jsonOk($avis);
}


function mesAvis(): void
{
    $user = authRequired();
    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT a.avis_id, a.note, a.description, a.statut_validation, a.date_avis,
               m.titre AS menu_titre, c.commande_id, c.date_prestation
        FROM avis a
        LEFT JOIN commande c ON c.commande_id = a.commande_id
        LEFT JOIN menu m ON m.menu_id = c.menu_id
        WHERE a.client_id = ?
        ORDER BY a.date_avis DESC
    ');
    $stmt->execute([$user['id']]);
    jsonOk($stmt->fetchAll());
}

function deposerAvis(array $body): void
{
    $user = authRequired();
    require_fields($body, 'note');

    $note = (int)$body['note'];
    if ($note < 1 || $note > 5) jsonError('La note doit être entre 1 et 5');

    $pdo = getPDO();

    // Vérifier que la commande appartient au client et est terminée
    if (!empty($body['commande_id'])) {
        $stmt = $pdo->prepare("SELECT commande_id FROM commande WHERE commande_id = ? AND client_id = ? AND statut_commande = 'terminée'");
        $stmt->execute([(int)$body['commande_id'], $user['id']]);
        if (!$stmt->fetch()) jsonError('Commande introuvable ou non terminée');

        // Un seul avis par commande
        $dup = $pdo->prepare('SELECT avis_id FROM avis WHERE commande_id = ? AND client_id = ?');
        $dup->execute([(int)$body['commande_id'], $user['id']]);
        if ($dup->fetch()) jsonError('Vous avez déjà déposé un avis pour cette commande', 409);
    }

    $pdo->prepare('INSERT INTO avis (client_id, commande_id, note, description, statut_validation) VALUES (?, ?, ?, ?, "en_attente")')
        ->execute([
            $user['id'],
            !empty($body['commande_id']) ? (int)$body['commande_id'] : null,
            $note,
            sanitize($body['description'] ?? ''),
        ]);

    jsonOk(['message' => 'Avis enregistré. Il sera publié après validation.'], 201);
}

function validerAvis(int $id, array $body): void
{
    roleRequired('employe', 'administrateur');
    require_fields($body, 'statut');

    if (!in_array($body['statut'], ['validé', 'refusé'], true)) {
        jsonError('Statut invalide : "validé" ou "refusé" attendu');
    }

    $pdo = getPDO();
    $pdo->prepare('UPDATE avis SET statut_validation = ? WHERE avis_id = ?')
        ->execute([$body['statut'], $id]);

    jsonOk(['message' => 'Avis ' . $body['statut']]);
}
