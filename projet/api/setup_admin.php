<?php
/**
 * Script de configuration initiale — À exécuter UNE SEULE FOIS depuis le navigateur.
 * Accès : http://localhost/ViteEtGourmand/projet/api/setup_admin.php?secret=vg-setup-2026
 * SUPPRIMER ce fichier après utilisation !
 */

// Protéger l'accès
$secret = $_GET['secret'] ?? '';
if ($secret !== 'vg-setup-2026') {
    http_response_code(403);
    die('Accès refusé. Ajoutez ?secret=vg-setup-2026 à l\'URL.');
}

require_once __DIR__ . '/config.php';

$pdo = getPDO();
$log = [];

// ─── 1. Hash du mot de passe admin ───────────────────────────────────────────
// Le compte admin n'est jamais créé depuis l'application (voir sujet ECF) : il
// est créé ici, une seule fois, à l'installation.

$adminPwd  = 'Admin2026!';
$adminHash = password_hash($adminPwd, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $check = $pdo->prepare("SELECT utilisateur_id FROM utilisateur WHERE email = 'admin@viteetgourmand.fr'");
    $check->execute();

    if ($check->fetch()) {
        $pdo->prepare("UPDATE utilisateur SET password = ? WHERE email = 'admin@viteetgourmand.fr'")
            ->execute([$adminHash]);
        $log[] = '✅ Mot de passe admin mis à jour';
    } else {
        $pdo->prepare("INSERT INTO utilisateur (email, password, prenom, nom, telephone, ville, pays, adresse, role_id, statut_compte, date_creation, consentement_rgpd)
                       VALUES ('admin@viteetgourmand.fr', ?, 'Administrateur', 'Vite&Gourmand', '0556000000', 'Bordeaux', 'France', '12 rue des Chartrons', 3, 1, CURDATE(), 1)")
            ->execute([$adminHash]);
        $log[] = '✅ Compte admin créé';
    }
} catch (PDOException $e) {
    $log[] = '❌ Admin : ' . $e->getMessage();
}

// ─── 2. Hash de tous les comptes de test ─────────────────────────────────────

$comptes = [
    ['employe@viteetgourmand.fr', 'Employe2026!'],
    ['jean.martin@email.fr',      'Client2026!'],
    ['sophie.b@email.fr',         'Client2026!'],
    ['pierre.d@email.fr',         'Client2026!'],
];

foreach ($comptes as [$mail, $pwd]) {
    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $chk = $pdo->prepare("SELECT utilisateur_id FROM utilisateur WHERE email = ?");
        $chk->execute([$mail]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE utilisateur SET password = ? WHERE email = ?")
                ->execute([$hash, $mail]);
            $log[] = "✅ Hash mis à jour : $mail";
        } else {
            $log[] = "⏭️ Compte non trouvé (il sera créé à l'inscription) : $mail";
        }
    } catch (PDOException $e) {
        $log[] = "❌ $mail : " . $e->getMessage();
    }
}

// ─── 3. Vérification données ──────────────────────────────────────────────────

try {
    $nbMenus  = (int)$pdo->query('SELECT COUNT(*) FROM menu')->fetchColumn();
    $nbPlats  = (int)$pdo->query('SELECT COUNT(*) FROM plat')->fetchColumn();
    $nbThemes = (int)$pdo->query('SELECT COUNT(*) FROM theme')->fetchColumn();
    $nbAvis   = (int)$pdo->query('SELECT COUNT(*) FROM avis')->fetchColumn();
    $nbCmd    = (int)$pdo->query('SELECT COUNT(*) FROM commande')->fetchColumn();
    $log[] = "✅ Base de données : $nbMenus menus · $nbPlats plats · $nbThemes thèmes · $nbAvis avis · $nbCmd commandes";
} catch (PDOException $e) {
    $log[] = '⚠️ Vérification : ' . $e->getMessage();
}

// ─── 4. Seed MongoDB : miroir des commandes existantes (démo stats admin) ────
// La base non relationnelle sert uniquement au graphique "commandes par menu"
// de l'espace admin — on y recopie les commandes déjà présentes en base MySQL.

require_once __DIR__ . '/mongodb.php';

if (getMongoDB() === null) {
    $log[] = '⏭️ MongoDB non configuré (MONGO_URI vide) — seed ignoré';
} else {
    try {
        $commandes = $pdo->query('
            SELECT c.commande_id, c.menu_id, m.titre AS menu_titre, c.prix_commande, c.date_commande, c.statut_commande
            FROM commande c JOIN menu m ON m.menu_id = c.menu_id
        ')->fetchAll();

        foreach ($commandes as $c) {
            mongoInsert('commandes', [
                'commande_id' => (int)$c['commande_id'],
                'menu_id'     => (int)$c['menu_id'],
                'menu_titre'  => $c['menu_titre'],
                'montant'     => (float)$c['prix_commande'],
                'date'        => $c['date_commande'],
                'statut'      => $c['statut_commande'],
            ]);
        }
        $log[] = '✅ MongoDB : ' . count($commandes) . ' commandes de démo insérées';
    } catch (Throwable $e) {
        $log[] = '⚠️ MongoDB seed : ' . $e->getMessage();
    }
}

// ─── Affichage ────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Setup — Vite & Gourmand</title>
<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:2rem;line-height:1.8}
h1{color:#C49A2D}pre{background:#2d2d2d;padding:1rem;border-radius:6px;border-left:4px solid #C49A2D}
.ok{color:#4ec9b0}.warn{color:#dcdcaa}.err{color:#f44747}
table{border-collapse:collapse;margin-top:1rem}td,th{padding:8px 16px;border:1px solid #444;text-align:left}
th{background:#2d2d2d;color:#C49A2D}</style>
</head>
<body>
<h1>🍽️ Vite & Gourmand — Setup</h1>
<pre><?php
foreach ($log as $line) {
    $class = str_starts_with($line, '✅') ? 'ok' : (str_starts_with($line, '❌') ? 'err' : 'warn');
    echo "<span class=\"$class\">" . htmlspecialchars($line) . "</span>\n";
}
?></pre>

<h2>Identifiants de test</h2>
<table>
<tr><th>Rôle</th><th>Email</th><th>Mot de passe</th></tr>
<tr><td>Administrateur</td><td>admin@viteetgourmand.fr</td><td><?= $adminPwd ?></td></tr>
<tr><td>Employé</td><td>employe@viteetgourmand.fr</td><td>Employe2026!</td></tr>
<tr><td>Client 1</td><td>jean.martin@email.fr</td><td>Client2026!</td></tr>
<tr><td>Client 2</td><td>sophie.b@email.fr</td><td>Client2026!</td></tr>
<tr><td>Client 3</td><td>pierre.d@email.fr</td><td>Client2026!</td></tr>
</table>

<p style="margin-top:2rem;color:#f44747;font-weight:bold">
⚠️ SUPPRIMEZ CE FICHIER avant la mise en production !<br>
<code>rm projet/api/setup_admin.php</code>
</p>
</body>
</html>
