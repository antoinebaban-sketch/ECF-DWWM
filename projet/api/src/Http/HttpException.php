<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Erreur "attendue" (validation, droits, ressource absente) : levée n'importe
 * où dans un contrôleur, un service ou un modèle, puis convertie par le Kernel
 * en réponse JSON {"error": "..."} avec le bon code HTTP.
 */
final class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function notFound(string $message = 'Ressource introuvable'): self
    {
        return new self($message, 404);
    }

    public static function forbidden(string $message = 'Accès interdit'): self
    {
        return new self($message, 403);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
