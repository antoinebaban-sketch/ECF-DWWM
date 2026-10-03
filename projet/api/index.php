<?php
/**
 * Front controller de l'API : toutes les requêtes /api/* arrivent ici (.htaccess).
 * Toute la logique vit dans des classes (namespace App\, dossier src/) ;
 * ce fichier se contente de charger l'autoload, la configuration et les routes.
 */

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/vendor/autoload.php'; // autoload PSR-4 (Composer)

\App\Config::load(__DIR__ . '/.env');

(new \App\Http\Kernel(require __DIR__ . '/routes.php'))->handle();
