<?php

declare(strict_types=1);

namespace App;

/**
 * Charge la configuration : fichier .env s'il existe (local), sinon variables
 * d'environnement injectées par l'hébergeur (production). Les valeurs sont
 * exposées sous forme de constantes (DB_HOST, MAIL_FROM, APP_URL...).
 */
final class Config
{
    private const DEFAULTS = [
        'DB_HOST'   => 'localhost',
        'DB_PORT'   => '3306',
        'DB_NAME'   => 'vite_et_gourmand',
        'DB_USER'   => 'root',
        'DB_PASS'   => '',
        'MAIL_FROM' => 'noreply@viteetgourmand.fr',
        'MAIL_NAME' => 'Vite & Gourmand',
        'APP_URL'   => 'http://localhost/ViteEtGourmand/projet/frontend',
        'MONGO_URI' => '',
        'MONGO_DB'  => 'vite_et_gourmand_logs',
    ];

    public static function load(string $envFile): void
    {
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                [$key, $value]      = array_pad(explode('=', $line, 2), 2, '');
                $_ENV[trim($key)] = trim($value);
            }
        }

        foreach (self::DEFAULTS as $name => $default) {
            if (!defined($name)) {
                $value = $_ENV[$name] ?? $default;
                define($name, $name === 'DB_PORT' ? (int) $value : $value);
            }
        }
    }
}
