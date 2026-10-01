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
 *
 * Logique réelle portée par App\Mongo\MongoConnection (src/Mongo/MongoConnection.php) ;
 * ces trois fonctions ne font plus que déléguer, pour ne pas casser les
 * contrôleurs pas encore migrés en classes (admin.php).
 */

function getMongoDB(): ?MongoDB\Driver\Manager
{
    return \App\Mongo\MongoConnection::manager();
}

function mongoInsert(string $collection, array $doc): void
{
    (new \App\Mongo\MongoConnection())->insert($collection, $doc);
}

function mongoAggregate(string $collection, array $pipeline): array
{
    return (new \App\Mongo\MongoConnection())->aggregate($collection, $pipeline);
}
