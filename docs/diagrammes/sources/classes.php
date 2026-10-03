<?php
/**
 * Génère les diagrammes de classes (syntaxe Mermaid) PAR RÉFLEXION sur le code
 * réel de l'API (projet/api/src) : le diagramme ne peut pas diverger du code.
 *
 *   DIR=LR php classes.php vue-ensemble > classes-vue-ensemble.mmd   (DIR=TB|LR, NS=0 : sans paquets)
 *   php classes.php commande      > classes-domaine-commande.mmd
 *
 * Prérequis : composer install dans projet/api (autoload PSR-4).
 */

declare(strict_types=1);

$api = dirname(__DIR__, 3) . '/projet/api';
require $api . '/vendor/autoload.php';

// ─── Chargement de toutes les classes de src/ ────────────────────────────────
$classes = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($api . '/src'));
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relatif = substr($file->getPathname(), strlen($api . '/src/'), -4);
    $fqcn    = 'App\\' . str_replace(['/', '\\'], '\\', $relatif);
    $classes[(new ReflectionClass($fqcn))->getShortName()] = new ReflectionClass($fqcn);
}
ksort($classes);

$mode = $argv[1] ?? 'vue-ensemble';

// Périmètre du diagramme détaillé : le domaine "commande" et tout ce qu'il traverse
$detail = ['Controller', 'CommandeController', 'Repository', 'CommandeRepository', 'Database', 'Commande', 'StatutCommande'];
$perimetre = $mode === 'commande' ? array_intersect_key($classes, array_flip($detail)) : $classes;

// ─── Utilitaires ─────────────────────────────────────────────────────────────
$court = static function (?ReflectionType $t): string {
    if ($t === null) {
        return '';
    }
    $s = (string) $t;

    return preg_replace('/(?:[A-Za-z_]+\\\\)+/', '', $s); // App\Http\Request → Request
};
$visibilite = static fn ($m) => $m->isPrivate() ? '-' : ($m->isProtected() ? '#' : '+');
$typesApp   = static function (?ReflectionType $t) use ($classes): array {
    if ($t === null) {
        return [];
    }
    $noms  = $t instanceof ReflectionUnionType ? $t->getTypes() : [$t];
    $trouv = [];
    foreach ($noms as $n) {
        $court = substr(strrchr('\\' . $n->getName(), '\\'), 1);
        if (isset($classes[$court])) {
            $trouv[] = $court;
        }
    }

    return $trouv;
};

$paquet = static fn (ReflectionClass $c) => explode('\\', $c->getNamespaceName())[1] ?? 'App';

// ─── Sortie Mermaid ──────────────────────────────────────────────────────────
echo "classDiagram\n";
echo 'direction ' . (getenv('DIR') ?: 'TB') . "\n";
$avecPaquets = getenv('NS') !== '0'; // NS=0 : sans cadres de paquet (mise en page plus compacte)

$parPaquet = [];
foreach ($perimetre as $nom => $c) {
    $parPaquet[$paquet($c)][] = $nom;
}

foreach ($parPaquet as $p => $noms) {
    echo $avecPaquets ? "namespace $p {\n" : '';
    foreach ($noms as $nom) {
        $c = $perimetre[$nom];
        echo "  class $nom {\n";
        if ($c->isEnum()) {
            echo "    <<enumeration>>\n";
        } elseif ($c->isAbstract()) {
            echo "    <<abstract>>\n";
        }

        if ($mode === 'commande') {
            if ($c->isEnum()) {
                foreach ((new ReflectionEnum($c->getName()))->getCases() as $case) {
                    echo "    {$case->getName()} = '{$case->getBackingValue()}'\n";
                }
            } else {
                foreach ($c->getReflectionConstants() as $k) {
                    if ($k->getDeclaringClass()->getName() === $c->getName()) {
                        echo '    ' . $visibilite($k) . $k->getName() . "$\n";
                    }
                }
                foreach ($c->getProperties() as $prop) {
                    if ($prop->getDeclaringClass()->getName() === $c->getName()) {
                        echo '    ' . $visibilite($prop) . $court($prop->getType()) . ' ' . $prop->getName() . ($prop->isStatic() ? '$' : '') . "\n";
                    }
                }
            }
            foreach ($c->getMethods() as $m) {
                if ($m->getDeclaringClass()->getName() !== $c->getName() || $m->getName() === '__construct'
                    || ($c->isEnum() && in_array($m->getName(), ['cases', 'from', 'tryFrom'], true))) {
                    continue;
                }
                $params = implode(', ', array_map(fn ($p) => $p->getName(), $m->getParameters())); // noms seuls : lignes plus courtes
                $retour = $court($m->getReturnType());
                echo '    ' . $visibilite($m) . $m->getName() . "($params)" . ($m->isStatic() ? '$' : '') . ($retour !== '' ? " $retour" : '') . "\n";
            }
        }
        echo "  }\n";
    }
    echo $avecPaquets ? "}\n" : '';
}

// Relations : héritage, puis associations (propriétés typées) et dépendances (signatures)
$vus = [];
foreach ($perimetre as $nom => $c) {
    $parent = $c->getParentClass();
    if ($parent && isset($perimetre[$parent->getShortName()])) {
        echo "{$parent->getShortName()} <|-- $nom\n";
    }
    foreach ($c->getProperties() as $prop) {
        if ($prop->getDeclaringClass()->getName() !== $c->getName()) {
            continue;
        }
        foreach ($typesApp($prop->getType()) as $cible) {
            if ($cible !== $nom && isset($perimetre[$cible]) && !isset($vus["$nom>$cible"])) {
                $vus["$nom>$cible"] = true;
                echo "$nom --> $cible\n";
            }
        }
    }
    if ($mode === 'commande') {
        foreach ($c->getMethods() as $m) {
            if ($m->getDeclaringClass()->getName() !== $c->getName()) {
                continue;
            }
            $types = array_merge($typesApp($m->getReturnType()), ...array_map(fn ($p) => $typesApp($p->getType()), $m->getParameters()));
            foreach ($types as $cible) {
                if ($cible !== $nom && isset($perimetre[$cible]) && !isset($vus["$nom>$cible"])) {
                    $vus["$nom>$cible"] = true;
                    echo "$nom ..> $cible\n";
                }
            }
        }
    }
}

// Dépendances par appel statique (invisibles par réflexion des signatures)
$statiques = [
    ['Router', 'Controller', 'dispatch()'], ['Repository', 'Database', 'connection()'],
    ['CommandeController', 'Commande', 'calculerPrixTotal()'], ['CommandeController', 'StatutCommande', ''],
    ['CommandeController', 'Sanitizer', ''], ['AvisController', 'Avis', 'new'], ['UtilisateurRepository', 'Utilisateur', 'fromRow()'],
    ['Kernel', 'Request', 'fromGlobals()'], ['Kernel', 'JsonResponse', ''],
];
foreach ($statiques as [$de, $vers, $label]) {
    if (isset($perimetre[$de], $perimetre[$vers]) && !isset($vus["$de>$vers"])) {
        $vus["$de>$vers"] = true;
        echo "$de ..> $vers" . ($label !== '' ? " : $label" : '') . "\n";
    }
}
