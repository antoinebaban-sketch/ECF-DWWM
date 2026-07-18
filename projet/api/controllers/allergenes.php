<?php
function handle(string $method, array $parts, array $body): void
{
    if ($method === 'GET') {
        jsonOk(getPDO()->query('SELECT * FROM allergene ORDER BY libelle')->fetchAll());
    }
    jsonError('Route allergènes inconnue', 404);
}
