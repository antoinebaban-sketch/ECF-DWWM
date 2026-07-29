<?php
/**
 * Connexion MongoDB — utilise ext-mongodb (sans Composer)
 * Nécessite : extension mongodb activée dans php.ini
 *
 * Utilisée uniquement pour stocker les commandes (collection "commandes") et
 * calculer les statistiques "nombre de commandes par menu" de l'espace admin
 * (base de données non relationnelle exigée par le sujet).
 *
 * Variables d'environnement :
 *   MONGO_URI  — ex: mongodb+srv://user:pass@cluster.mongodb.net/
 *   MONGO_DB   — ex: vite_et_gourmand_logs
 */

function getMongoDB(): ?MongoDB\Driver\Manager
{
    static $mgr = null;
    if ($mgr !== null) return $mgr;

    $uri = defined('MONGO_URI') ? MONGO_URI : null;
    if (!$uri || !extension_loaded('mongodb')) return null;

    try {
        $mgr = new MongoDB\Driver\Manager($uri);
    } catch (Throwable) {
        $mgr = null;
    }
    return $mgr;
}

/**
 * Insère un document dans la collection donnée.
 * Silencieux si MongoDB est indisponible (l'application continue de fonctionner).
 */
function mongoInsert(string $collection, array $doc): void
{
    $mgr = getMongoDB();
    if ($mgr === null) return;

    $ns   = MONGO_DB . '.' . $collection;
    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->insert($doc);

    try {
        $mgr->executeBulkWrite($ns, $bulk);
    } catch (Throwable) {
        // MongoDB indisponible → on ignore
    }
}

/**
 * Exécute un pipeline d'agrégation (équivalent d'un GROUP BY SQL) sur une collection.
 * Retourne un tableau de résultats ou [] si MongoDB est indisponible.
 */
function mongoAggregate(string $collection, array $pipeline): array
{
    $mgr = getMongoDB();
    if ($mgr === null) return [];

    try {
        $cmd    = new MongoDB\Driver\Command(['aggregate' => $collection, 'pipeline' => $pipeline, 'cursor' => new stdClass()]);
        $cursor = $mgr->executeCommand(MONGO_DB, $cmd);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        return $cursor->toArray();
    } catch (Throwable) {
        return [];
    }
}
