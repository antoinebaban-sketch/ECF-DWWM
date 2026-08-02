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
 */
function authRequired(): array
{
    if (empty($_SESSION['user'])) {
        jsonError('Vous devez être connecté', 401);
    }
    return $_SESSION['user'];
}

/**
 * Vérifie que l'utilisateur connecté a l'un des rôles autorisés.
 */
function roleRequired(string ...$roles): array
{
    $user = authRequired();
    if (!in_array($user['role'], $roles, true)) {
        jsonError('Accès interdit', 403);
    }
    return $user;
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

function sendMail(string $to, string $subject, string $htmlBody): bool
{
    $apiKey = $_ENV['BREVO_API_KEY'] ?? '';

    // Environnement local (XAMPP) : pas de clé configurée → mail() natif
    if ($apiKey === '') {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . MAIL_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
    }

    // Production : mail() nécessite un serveur mail local, absent sur un hébergeur
    // conteneurisé (Render). On passe par l'API HTTP de Brevo à la place, avec
    // curl (déjà utilisé nulle part ailleurs, mais extension standard de PHP).
    $payload = json_encode([
        'sender'      => ['name' => MAIL_NAME, 'email' => MAIL_FROM],
        'to'          => [['email' => $to]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody,
    ]);
    if ($payload === false) return false;

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}

function mailTemplate(string $titre, string $corps): string
{
    return <<<HTML
    <!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
    <style>body{font-family:Georgia,serif;color:#2d1a1a;background:#f7f1e8;margin:0;padding:0}
    .wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden}
    .head{background:#5C1A1A;color:#C49A2D;padding:24px 32px;font-size:22px;font-style:italic}
    .body{padding:32px;line-height:1.7}.foot{background:#ede3d0;padding:16px 32px;font-size:13px;color:#5c1a1a;text-align:center}</style>
    </head><body><div class="wrap">
    <div class="head">Vite &amp; <em>Gourmand</em></div>
    <div class="body"><h2 style="color:#5C1A1A">$titre</h2>$corps</div>
    <div class="foot">© 2026 Vite &amp; Gourmand · Traiteur à Bordeaux · <a href="mailto:contact@viteetgourmand.fr" style="color:#5c1a1a">contact@viteetgourmand.fr</a></div>
    </div></body></html>
    HTML;
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
