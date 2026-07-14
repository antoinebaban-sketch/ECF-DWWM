<?php
/**
 * /api/admin/*
 * GET  /admin/utilisateurs                 → liste tous les utilisateurs
 * POST /admin/employes                     → créer un compte employé
 * PUT  /admin/employes/{id}/desactiver     → désactiver un compte
 * PUT  /admin/employes/{id}/activer        → réactiver un compte
 * GET  /admin/stats                        → statistiques (commandes, CA, menus)
 * GET  /admin/avis                         → tous les avis (en attente)
 */
function handle(string $method, array $parts, array $body): void
{
    $seg = $parts[1] ?? null;
    $id  = is_numeric($parts[2] ?? null) ? (int)$parts[2] : null;
    $sub = $parts[3] ?? null;

    match(true) {
        $method === 'GET'  && $seg === 'utilisateurs'                     => listerUtilisateurs(),
        $method === 'POST' && $seg === 'employes'                         => creerEmploye($body),
        $method === 'PUT'  && $seg === 'employes' && $sub === 'desactiver'=> toggleCompte($id, false),
        $method === 'PUT'  && $seg === 'employes' && $sub === 'activer'   => toggleCompte($id, true),
        $method === 'GET'  && $seg === 'stats'                            => getStats(),
        $method === 'GET'  && $seg === 'avis'                             => getAvisAdmin(),
        default => jsonError('Route admin inconnue', 404)
    };
}

function listerUtilisateurs(): void
{
    roleRequired('administrateur');
    $pdo  = getPDO();
    $role = $_GET['role'] ?? null;
    $sql  = 'SELECT u.utilisateur_id, u.email, u.prenom, u.nom, u.telephone, u.statut_compte, u.date_creation,
                    r.libelle AS role
             FROM utilisateur u
             JOIN role r ON r.role_id = u.role_id'
           . ($role ? ' WHERE r.libelle = ?' : '')
           . ' ORDER BY u.date_creation DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($role ? [$role] : []);
    jsonOk($stmt->fetchAll());
}

function creerEmploye(array $body): void
{
    roleRequired('administrateur');
    require_fields($body, 'email', 'prenom', 'nom', 'telephone');

    if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) jsonError('Email invalide');

    $pdo  = getPDO();
    $dup  = $pdo->prepare('SELECT utilisateur_id FROM utilisateur WHERE email = ?');
    $dup->execute([sanitizeEmail($body['email'])]);
    if ($dup->fetch()) jsonError('Cet email est déjà utilisé', 409);

    // Mot de passe temporaire aléatoire
    $tmpPwd  = ucfirst(bin2hex(random_bytes(4))) . '!' . random_int(10, 99);
    $hash    = hashPassword($tmpPwd);

    $pdo->prepare('
        INSERT INTO utilisateur (email, password, prenom, nom, telephone, ville, pays, adresse, role_id, statut_compte, date_creation, consentement_rgpd)
        VALUES (?, ?, ?, ?, ?, "", "France", "", 2, 1, CURDATE(), 1)
    ')->execute([
        sanitizeEmail($body['email']),
        $hash,
        sanitize($body['prenom']),
        sanitize($body['nom']),
        sanitize($body['telephone']),
    ]);

    // Mail de notification avec le mot de passe temporaire
    $html = mailTemplate(
        'Votre compte employé Vite & Gourmand',
        '<p>Bonjour <strong>' . htmlspecialchars($body['prenom']) . '</strong>,</p>
         <p>Un compte employé a été créé pour vous sur le portail Vite &amp; Gourmand.</p>
         <table style="width:100%;border-collapse:collapse;margin:16px 0">
           <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Email</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . htmlspecialchars($body['email']) . '</td></tr>
           <tr><td style="padding:8px"><strong>Mot de passe temporaire</strong></td><td style="padding:8px"><code style="background:#f7f1e8;padding:2px 6px;border-radius:3px">' . $tmpPwd . '</code></td></tr>
         </table>
         <p><strong>Changez votre mot de passe dès votre première connexion.</strong></p>
         <p><a href="' . APP_URL . '/employe.html" style="background:#5C1A1A;color:#C49A2D;padding:10px 20px;text-decoration:none;border-radius:4px">Accéder à l\'espace employé</a></p>'
    );
    sendMail($body['email'], 'Votre compte employé — Vite & Gourmand', $html);

    jsonOk(['message' => 'Compte employé créé. Un email avec le mot de passe temporaire a été envoyé.'], 201);
}

function toggleCompte(int $id, bool $actif): void
{
    roleRequired('administrateur');
    $pdo = getPDO();

    // On ne peut pas désactiver un administrateur
    $stmt = $pdo->prepare('SELECT r.libelle FROM utilisateur u JOIN role r ON r.role_id = u.role_id WHERE u.utilisateur_id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) jsonError('Utilisateur introuvable', 404);
    if ($user['libelle'] === 'administrateur') jsonError('Impossible de désactiver un administrateur');

    $pdo->prepare('UPDATE utilisateur SET statut_compte = ? WHERE utilisateur_id = ?')
        ->execute([$actif ? 1 : 0, $id]);

    jsonOk(['message' => $actif ? 'Compte réactivé' : 'Compte désactivé']);
}

function getStats(): void
{
    roleRequired('administrateur');
    $pdo = getPDO();

    $ca = $pdo->query("
        SELECT DATE_FORMAT(date_commande, '%Y-%m') AS mois, COUNT(*) AS nb_commandes, SUM(prix_commande) AS ca
        FROM commande
        WHERE statut_commande NOT IN ('annulée')
          AND date_commande >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mois
        ORDER BY mois ASC
    ")->fetchAll();

    // Chiffre d'affaires par menu, filtrable par menu et par période (MySQL)
    $where  = ["c.statut_commande NOT IN ('annulée')"];
    $params = [];
    if (!empty($_GET['menu_id'])) {
        $where[]  = 'c.menu_id = ?';
        $params[] = (int)$_GET['menu_id'];
    }
    if (!empty($_GET['date_debut'])) {
        $where[]  = 'c.date_commande >= ?';
        $params[] = $_GET['date_debut'];
    }
    if (!empty($_GET['date_fin'])) {
        $where[]  = 'c.date_commande <= ?';
        $params[] = $_GET['date_fin'];
    }
    $stmt = $pdo->prepare("
        SELECT m.menu_id, m.titre, COUNT(c.commande_id) AS nb, SUM(c.prix_commande) AS ca_menu
        FROM commande c
        JOIN menu m ON m.menu_id = c.menu_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY m.menu_id
        ORDER BY ca_menu DESC
    ");
    $stmt->execute($params);
    $caParMenu = $stmt->fetchAll();

    // Nombre de commandes par menu — exigé depuis la base non relationnelle (MongoDB)
    $commandesParMenu = mongoAggregate('commandes', [
        ['$group' => [
            '_id'         => '$menu_id',
            'menu_titre'  => ['$first' => '$menu_titre'],
            'nb_commandes'=> ['$sum' => 1],
        ]],
        ['$sort' => ['nb_commandes' => -1]],
    ]);

    $globales = $pdo->query("
        SELECT
            COUNT(*) AS total_commandes,
            SUM(CASE WHEN statut_commande = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
            SUM(CASE WHEN statut_commande = 'annulée'    THEN 1 ELSE 0 END) AS annulees,
            SUM(CASE WHEN statut_commande NOT IN ('annulée') THEN prix_commande ELSE 0 END) AS ca_total
        FROM commande
    ")->fetch();

    $nbClients   = $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role_id = 1 AND statut_compte = 1")->fetchColumn();
    $noteMoyenne = $pdo->query("SELECT AVG(note) FROM avis WHERE statut_validation = 'validé'")->fetchColumn();

    jsonOk([
        'ca_par_mois'         => $ca,
        'ca_par_menu'         => $caParMenu,
        'commandes_par_menu'  => $commandesParMenu,
        'globales'            => $globales,
        'nb_clients'          => (int)$nbClients,
        'note_moyenne'        => $noteMoyenne ? round((float)$noteMoyenne, 1) : null,
    ]);
}

function getAvisAdmin(): void
{
    roleRequired('employe', 'administrateur');
    $pdo    = getPDO();
    $statut = $_GET['statut'] ?? 'en_attente';
    $stmt   = $pdo->prepare('
        SELECT a.*, u.prenom, u.nom, u.email
        FROM avis a
        JOIN utilisateur u ON u.utilisateur_id = a.client_id
        WHERE a.statut_validation = ?
        ORDER BY a.date_avis DESC
    ');
    $stmt->execute([$statut]);
    jsonOk($stmt->fetchAll());
}
