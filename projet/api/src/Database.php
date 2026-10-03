<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Connexion MySQL (PDO), une seule instance par requête HTTP (singleton),
 * partagée par tous les repositories (voir Repositories\Repository).
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Aiven (production) exige une connexion SSL ; en local (XAMPP), DB_HOST
        // vaut "localhost" et le certificat n'est pas nécessaire.
        $caFile = dirname(__DIR__) . '/aiven-ca.pem';
        if (!in_array(DB_HOST, ['localhost', '127.0.0.1'], true) && file_exists($caFile)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        }

        self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

        return self::$instance;
    }
}
