<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;

/**
 * Politique de mot de passe (recommandations ANSSI / CNIL) et hachage bcrypt.
 */
final class PasswordPolicy
{
    private const MIN_LENGTH = 10;
    private const BCRYPT_COST = 12;

    /** @throws HttpException 400 avec la première règle non respectée */
    public function validate(string $password): void
    {
        $rules = [
            'Le mot de passe doit contenir au moins ' . self::MIN_LENGTH . ' caractères' => strlen($password) >= self::MIN_LENGTH,
            'Le mot de passe doit contenir une majuscule'                                  => (bool) preg_match('/[A-Z]/', $password),
            'Le mot de passe doit contenir une minuscule'                                  => (bool) preg_match('/[a-z]/', $password),
            'Le mot de passe doit contenir un chiffre'                                     => (bool) preg_match('/[0-9]/', $password),
            'Le mot de passe doit contenir un caractère spécial'                           => (bool) preg_match('/[\W_]/', $password),
        ];

        foreach ($rules as $message => $respected) {
            if (!$respected) {
                throw new HttpException($message);
            }
        }
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /** Jeton aléatoire (lien de réinitialisation / d'activation), 64 caractères hexadécimaux. */
    public function token(): string
    {
        return bin2hex(random_bytes(32));
    }
}
