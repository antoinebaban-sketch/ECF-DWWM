<?php
function handle(string $method, array $parts, array $body): void
{
    if ($method === 'GET') {
        jsonOk(getPDO()->query('SELECT * FROM regime ORDER BY libelle')->fetchAll());
    }
    jsonError('Route régimes inconnue', 404);
}
