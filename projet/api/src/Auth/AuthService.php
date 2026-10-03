<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\HttpException;

/**
 * Seule classe qui lit ou écrit l'utilisateur connecté dans la session PHP
 * ($_SESSION['user'], rempli au login). Les contrôleurs l'utilisent pour
 * identifier l'utilisateur et contrôler son rôle.
 */
final class AuthService
{
    /** Rôles qui donnent accès aux espaces de gestion. */
    public const STAFF = ['employe', 'administrateur'];

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** @throws HttpException 401 si personne n'est connecté */
    public function requireUser(): array
    {
        return $this->user() ?? throw new HttpException('Vous devez être connecté', 401);
    }

    /** @throws HttpException 401 si non connecté, 403 si le rôle ne fait pas partie de $roles */
    public function requireRole(string ...$roles): array
    {
        $user = $this->requireUser();
        if (!in_array($user['role'], $roles, true)) {
            throw HttpException::forbidden();
        }

        return $user;
    }

    /** @throws HttpException 403 si l'utilisateur n'est ni employé ni administrateur */
    public function requireStaff(): array
    {
        return $this->requireRole(...self::STAFF);
    }

    /** @param array{id: int, email: string, role: string, prenom: string, nom: string} $user */
    public function login(array $user): array
    {
        $_SESSION['user'] = $user;

        return $user;
    }

    /** Met à jour les informations affichées (navbar...) après une modification de profil. */
    public function refresh(array $fields): void
    {
        if (isset($_SESSION['user'])) {
            $_SESSION['user'] = array_merge($_SESSION['user'], $fields);
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
