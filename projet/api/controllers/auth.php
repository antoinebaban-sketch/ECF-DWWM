<?php
/**
 * /api/auth/*
 * POST /auth/login
 * POST /auth/register
 * POST /auth/logout
 * GET  /auth/me
 * POST /auth/forgot-password
 * POST /auth/reset-password
 */
function handle(string $method, array $parts, array $body): void
{
    $action = $parts[1] ?? '';

    match(true) {
        $method === 'POST' && $action === 'login'           => login($body),
        $method === 'POST' && $action === 'register'        => register($body),
        $method === 'POST' && $action === 'logout'          => logout(),
        $method === 'GET'  && $action === 'me'               => me(),
        $method === 'POST' && $action === 'forgot-password' => forgotPassword($body),
        $method === 'POST' && $action === 'reset-password'  => resetPassword($body),
        default => jsonError('Route auth inconnue', 404)
    };
}

// ─── Connexion ────────────────────────────────────────────────────────────────

function login(array $body): void
{
    require_fields($body, 'email', 'password');

    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT u.utilisateur_id, u.email, u.password, u.prenom, u.nom, u.statut_compte,
               r.libelle AS role
        FROM utilisateur u
        JOIN role r ON r.role_id = u.role_id
        WHERE u.email = ?
    ');
    $stmt->execute([sanitizeEmail($body['email'])]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($body['password'], $user['password'])) {
        jsonError('Email ou mot de passe incorrect', 401);
    }
    if (!$user['statut_compte']) {
        jsonError('Ce compte a été désactivé. Contactez l\'administrateur.', 403);
    }

    $_SESSION['user'] = [
        'id'     => $user['utilisateur_id'],
        'email'  => $user['email'],
        'role'   => $user['role'],
        'prenom' => $user['prenom'],
        'nom'    => $user['nom'] ?? '',
    ];

    jsonOk($_SESSION['user']);
}

// ─── Déconnexion ──────────────────────────────────────────────────────────────

function logout(): void
{
    $_SESSION = [];
    session_destroy();
    jsonOk(['message' => 'Déconnecté']);
}

// ─── Utilisateur connecté (utilisé par le frontend pour l'affichage) ─────────

function me(): void
{
    if (empty($_SESSION['user'])) jsonError('Non connecté', 401);
    jsonOk($_SESSION['user']);
}

// ─── Inscription ──────────────────────────────────────────────────────────────

function register(array $body): void
{
    require_fields($body, 'email', 'password', 'prenom', 'nom', 'telephone', 'adresse');

    if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
        jsonError('Adresse email invalide');
    }
    validatePassword($body['password']);
    if (!($body['consentement_rgpd'] ?? false)) jsonError('Le consentement RGPD est obligatoire');

    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT utilisateur_id FROM utilisateur WHERE email = ?');
    $stmt->execute([sanitizeEmail($body['email'])]);
    if ($stmt->fetch()) jsonError('Cette adresse email est déjà utilisée', 409);

    $hash = hashPassword($body['password']);

    // role_id = 1 → "client" (le rôle "utilisateur" par défaut à l'inscription)
    $ins = $pdo->prepare('
        INSERT INTO utilisateur (email, password, prenom, nom, telephone, ville, pays, adresse, role_id, statut_compte, date_creation, consentement_rgpd)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, CURDATE(), 1)
    ');
    $ins->execute([
        sanitizeEmail($body['email']),
        $hash,
        sanitize($body['prenom']),
        sanitize($body['nom']),
        sanitize($body['telephone']),
        sanitize($body['ville'] ?? ''),
        sanitize($body['pays'] ?? ''),
        sanitize($body['adresse']),
    ]);

    $id = (int) $pdo->lastInsertId();

    // Mail de bienvenue
    $html = mailTemplate(
        'Bienvenue chez Vite & Gourmand !',
        '<p>Bonjour <strong>' . htmlspecialchars($body['prenom']) . '</strong>,</p>
         <p>Votre compte a bien été créé. Vous pouvez dès maintenant parcourir nos menus et passer commande.</p>
         <p><a href="' . APP_URL . '/menu.html" style="background:#5C1A1A;color:#C49A2D;padding:10px 20px;text-decoration:none;border-radius:4px">Découvrir nos menus</a></p>
         <p>À bientôt,<br>L\'équipe Vite &amp; Gourmand</p>'
    );
    sendMail($body['email'], 'Bienvenue chez Vite & Gourmand', $html);

    $_SESSION['user'] = [
        'id'     => $id,
        'email'  => sanitizeEmail($body['email']),
        'role'   => 'client',
        'prenom' => $body['prenom'],
        'nom'    => $body['nom'],
    ];

    jsonOk($_SESSION['user'], 201);
}

// ─── Mot de passe oublié ──────────────────────────────────────────────────────

function forgotPassword(array $body): void
{
    require_fields($body, 'email');

    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT utilisateur_id, prenom FROM utilisateur WHERE email = ?');
    $stmt->execute([sanitizeEmail($body['email'])]);
    $user = $stmt->fetch();

    // On répond toujours OK pour ne pas révéler l'existence du compte
    if (!$user) { jsonOk(['message' => 'Si ce compte existe, un email a été envoyé.']); }

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare('UPDATE utilisateur SET reset_token = ?, reset_token_expires = ? WHERE utilisateur_id = ?')
        ->execute([$token, $expires, $user['utilisateur_id']]);

    $link = APP_URL . '/reset-password.html?token=' . $token;
    $html = mailTemplate(
        'Réinitialisation de votre mot de passe',
        '<p>Bonjour <strong>' . htmlspecialchars($user['prenom']) . '</strong>,</p>
         <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe (valable 1 heure) :</p>
         <p><a href="' . $link . '" style="background:#5C1A1A;color:#C49A2D;padding:10px 20px;text-decoration:none;border-radius:4px">Réinitialiser mon mot de passe</a></p>
         <p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>'
    );
    sendMail($body['email'], 'Réinitialisation de votre mot de passe', $html);

    jsonOk(['message' => 'Si ce compte existe, un email a été envoyé.']);
}

// ─── Reset mot de passe ───────────────────────────────────────────────────────

function resetPassword(array $body): void
{
    require_fields($body, 'token', 'password');

    validatePassword($body['password']);

    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT utilisateur_id FROM utilisateur
        WHERE reset_token = ? AND reset_token_expires > NOW()
    ');
    $stmt->execute([$body['token']]);
    $user = $stmt->fetch();

    if (!$user) jsonError('Lien invalide ou expiré', 400);

    $hash = hashPassword($body['password']);
    $pdo->prepare('UPDATE utilisateur SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE utilisateur_id = ?')
        ->execute([$hash, $user['utilisateur_id']]);

    jsonOk(['message' => 'Mot de passe modifié avec succès.']);
}
