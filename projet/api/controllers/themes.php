<?php
function handle(string $method, array $parts, array $body): void
{
    $id = is_numeric($parts[1] ?? null) ? (int)$parts[1] : null;
    match(true) {
        $method === 'GET'    && $id === null => jsonOk(getPDO()->query('SELECT * FROM theme ORDER BY libelle')->fetchAll()),
        $method === 'POST'   && $id === null => creerTheme($body),
        $method === 'PUT'    && $id !== null => modifierTheme($id, $body),
        $method === 'DELETE' && $id !== null => supprimerTheme($id),
        default => jsonError('Route themes inconnue', 404)
    };
}

function creerTheme(array $body): void
{
    roleRequired('employe', 'administrateur');
    require_fields($body, 'libelle');
    $pdo = getPDO();
    $pdo->prepare('INSERT INTO theme (libelle) VALUES (?)')->execute([trim($body['libelle'])]);
    jsonOk(['theme_id' => (int)$pdo->lastInsertId(), 'libelle' => trim($body['libelle'])], 201);
}

function modifierTheme(int $id, array $body): void
{
    roleRequired('employe', 'administrateur');
    require_fields($body, 'libelle');
    getPDO()->prepare('UPDATE theme SET libelle = ? WHERE theme_id = ?')->execute([trim($body['libelle']), $id]);
    jsonOk(['message' => 'Thème mis à jour']);
}

function supprimerTheme(int $id): void
{
    roleRequired('administrateur');
    getPDO()->prepare('DELETE FROM theme WHERE theme_id = ?')->execute([$id]);
    jsonOk(['message' => 'Thème supprimé']);
}
