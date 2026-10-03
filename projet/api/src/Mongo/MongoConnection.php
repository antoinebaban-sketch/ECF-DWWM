<?php

declare(strict_types=1);

namespace App\Mongo;

use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use stdClass;
use Throwable;

/**
 * Connexion à la base NoSQL (MongoDB, pilote ext-mongodb — sans Composer, pas
 * de paquet mongodb/mongodb). Utilisée uniquement pour la collection
 * "commandes" (statistique "nombre de commandes par menu" de l'espace admin).
 *
 * Dégradation gracieuse : si MONGO_URI est absente ou le serveur injoignable,
 * toutes les méthodes renvoient un résultat neutre au lieu de lever une
 * exception — le cœur de l'application (prise de commande, navigation) ne
 * dépend jamais de ce service secondaire.
 */
final class MongoConnection
{
    private static ?Manager $instance = null;
    private static bool $attempted = false;

    public static function manager(): ?Manager
    {
        if (self::$attempted) {
            return self::$instance;
        }
        self::$attempted = true;

        $uri = defined('MONGO_URI') ? MONGO_URI : null;
        if (!$uri || !extension_loaded('mongodb')) {
            return null;
        }

        try {
            self::$instance = new Manager($uri);
        } catch (Throwable) {
            self::$instance = null;
        }

        return self::$instance;
    }

    public function insert(string $collection, array $doc): void
    {
        $mgr = self::manager();
        if ($mgr === null) {
            return;
        }

        $bulk = new BulkWrite();
        $bulk->insert($doc);

        try {
            $mgr->executeBulkWrite(MONGO_DB . '.' . $collection, $bulk);
        } catch (Throwable) {
            // MongoDB indisponible → on ignore, l'application continue de fonctionner.
        }
    }

    public function aggregate(string $collection, array $pipeline): array
    {
        $mgr = self::manager();
        if ($mgr === null) {
            return [];
        }

        try {
            $cmd    = new Command(['aggregate' => $collection, 'pipeline' => $pipeline, 'cursor' => new stdClass()]);
            $cursor = $mgr->executeCommand(MONGO_DB, $cmd);
            $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);

            return $cursor->toArray();
        } catch (Throwable) {
            return [];
        }
    }
}
