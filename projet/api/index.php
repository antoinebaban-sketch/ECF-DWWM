<?php
declare(strict_types=1);
ini_set('display_errors', '0');

// ─── CORS (avant tout traitement) ────────────────────────────────────────────
// On reflète l'origine de la requête pour pouvoir autoriser l'envoi du cookie
// de session (impossible avec Access-Control-Allow-Origin: *).
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Session (authentification) ──────────────────────────────────────────────
session_start();

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mongodb.php';

// ─── Routage ─────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

// Extrait le chemin après /api  →  ["auth","login"] ou ["menus","3","statut"]
$raw    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$raw    = preg_replace('#^.*/api/?#', '', $raw ?? '');
$parts  = array_values(array_filter(explode('/', trim($raw, '/'))));

$resource = $parts[0] ?? '';

try {
    $ctrl = match($resource) {
        'auth'          => 'auth',
        'menus'         => 'menus',
        'plats'         => 'plats',
        'themes'        => 'themes',
        'regimes'       => 'regimes',
        'allergenes'    => 'allergenes',
        'horaires'      => 'horaires',
        'commandes'     => 'commandes',
        'avis'          => 'avis',
        'utilisateurs'  => 'utilisateurs',
        'admin'         => 'admin',
        'contact'       => 'contact',
        'devis'         => 'contact',      // alias → même contrôleur
        'factures'      => 'commandes',    // alias → commandes
        default         => null
    };

    if ($ctrl === null) {
        jsonError('Route inconnue : ' . htmlspecialchars($resource), 404);
    }

    require_once __DIR__ . "/controllers/{$ctrl}.php";
    handle($method, $parts, getBody());

} catch (PDOException $e) {
    error_log('[VG PDO] ' . $e->getMessage());
    jsonError('Erreur base de données', 500);
} catch (Throwable $e) {
    error_log('[VG] ' . $e->getMessage());
    jsonError('Erreur serveur', 500);
}
