<?php
// Chargement des variables d'environnement depuis .env si présent
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $_ENV[trim($k)] = trim($v);
    }
}

define('DB_HOST',   $_ENV['DB_HOST']   ?? 'localhost');
define('DB_PORT',   (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME',   $_ENV['DB_NAME']   ?? 'vite_et_gourmand');
define('DB_USER',   $_ENV['DB_USER']   ?? 'root');
define('DB_PASS',   $_ENV['DB_PASS']   ?? '');
define('MAIL_FROM', $_ENV['MAIL_FROM'] ?? 'noreply@viteetgourmand.fr');
define('MAIL_NAME', $_ENV['MAIL_NAME'] ?? 'Vite & Gourmand');
define('APP_URL',   $_ENV['APP_URL']   ?? 'http://localhost/ViteEtGourmand/projet/frontend');
define('MONGO_URI', $_ENV['MONGO_URI'] ?? '');
define('MONGO_DB',  $_ENV['MONGO_DB']  ?? 'vite_et_gourmand_logs');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
