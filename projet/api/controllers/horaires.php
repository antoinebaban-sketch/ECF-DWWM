<?php
function handle(string $method, array $parts, array $body): void
{
    $id = is_numeric($parts[1] ?? null) ? (int)$parts[1] : null;

    match(true) {
        $method === 'GET' && $id === null => jsonOk(getPDO()->query('SELECT * FROM horaire ORDER BY horaire_id')->fetchAll()),
        $method === 'PUT' && $id !== null => modifierHoraire($id, $body),
        default => jsonError('Route horaires inconnue', 404)
    };
}

function modifierHoraire(int $id, array $body): void
{
    roleRequired('employe', 'administrateur');
    getPDO()->prepare('UPDATE horaire SET heure_ouverture = COALESCE(?, heure_ouverture), heure_fermeture = COALESCE(?, heure_fermeture) WHERE horaire_id = ?')
        ->execute([$body['heure_ouverture'] ?? null, $body['heure_fermeture'] ?? null, $id]);
    jsonOk(['message' => 'Horaire mis à jour']);
}
