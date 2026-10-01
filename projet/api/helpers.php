<?php
// ─── Réponses JSON ───────────────────────────────────────────────────────────

function jsonOk(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Auth par session PHP ─────────────────────────────────────────────────────

/**
 * Retourne l'utilisateur connecté (stocké en session au login) ou renvoie une 401.
 * Délègue à App\Auth\AuthService (src/Auth/AuthService.php), pour que les
 * contrôleurs procéduraux et les contrôleurs en classes partagent la même logique.
 */
function authRequired(): array
{
    return (new \App\Auth\AuthService())->requireUser();
}

/**
 * Vérifie que l'utilisateur connecté a l'un des rôles autorisés.
 */
function roleRequired(string ...$roles): array
{
    return (new \App\Auth\AuthService())->requireRole(...$roles);
}

// ─── Corps de la requête ──────────────────────────────────────────────────────

function getBody(): array
{
    static $body = null;
    if ($body !== null) return $body;
    $raw  = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?? []) : [];
    return $body;
}

function require_fields(array $body, string ...$fields): void
{
    foreach ($fields as $f) {
        if (!isset($body[$f]) || (is_string($body[$f]) && trim($body[$f]) === '')) {
            jsonError("Le champ « $f » est obligatoire");
        }
    }
}

// ─── Email ───────────────────────────────────────────────────────────────────
// Logique réelle portée par App\Mail\Mailer (src/Mail/Mailer.php) ; ces deux
// fonctions ne font plus que déléguer, pour ne pas casser les contrôleurs pas
// encore migrés en classes (contact.php, auth.php, admin.php, utilisateurs.php...).

function sendMail(string $to, string $subject, string $htmlBody): bool
{
    return (new \App\Mail\Mailer())->send($to, $subject, $htmlBody);
}

function mailTemplate(string $titre, string $corps): string
{
    return (new \App\Mail\Mailer())->template($titre, $corps);
}

// ─── Sécurité : entrées ──────────────────────────────────────────────────────

/**
 * Nettoie une chaîne libre : supprime les balises HTML et les espaces extrêmes.
 * À appliquer sur tout champ texte non structuré avant traitement ou stockage.
 */
function sanitize(string $s): string
{
    return trim(strip_tags($s));
}

function sanitizeEmail(string $email): string
{
    return strtolower(trim($email));
}

// ─── Sécurité : mot de passe ──────────────────────────────────────────────────

function validatePassword(string $pwd): void
{
    if (strlen($pwd) < 10)            jsonError('Le mot de passe doit contenir au moins 10 caractères');
    if (!preg_match('/[A-Z]/', $pwd)) jsonError('Le mot de passe doit contenir une majuscule');
    if (!preg_match('/[a-z]/', $pwd)) jsonError('Le mot de passe doit contenir une minuscule');
    if (!preg_match('/[0-9]/', $pwd)) jsonError('Le mot de passe doit contenir un chiffre');
    if (!preg_match('/[\W_]/', $pwd)) jsonError('Le mot de passe doit contenir un caractère spécial');
}

function hashPassword(string $pwd): string
{
    return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
}

const STAFF_ROLES = ['administrateur', 'employe'];

// ─── PDO : récupération ou 404 ────────────────────────────────────────────────

function fetchOrFail(PDO $pdo, string $sql, array $params = [], string $message = 'Ressource introuvable'): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row  = $stmt->fetch();
    if (!$row) jsonError($message, 404);
    return $row;
}
