<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Lecture de l'utilisateur connecté (stocké en session au login) et contrôle
 * des rôles. authRequired() / roleRequired() (helpers.php) délèguent ici,
 * pour que les contrôleurs procéduraux et les contrôleurs en classes partagent
 * exactement la même logique d'authentification.
 */
final class AuthService
{
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            jsonError('Vous devez être connecté', 401);
        }

        return $user;
    }

    public function requireRole(string ...$roles): array
    {
        $user = $this->requireUser();
        if (!in_array($user['role'], $roles, true)) {
            jsonError('Accès interdit', 403);
        }

        return $user;
    }
}
