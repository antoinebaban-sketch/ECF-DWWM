<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Requête HTTP entrante, construite une seule fois par le Kernel à partir des
 * superglobales. Les contrôleurs ne lisent jamais $_GET / php://input
 * directement : tout passe par cet objet.
 */
final class Request
{
    /**
     * @param string[]             $segments Chemin après /api, ex. ["menus", "3"]
     * @param array<string, mixed> $query    Paramètres de l'URL ($_GET)
     * @param array<string, mixed> $body     Corps JSON décodé
     */
    public function __construct(
        public readonly string $method,
        public readonly array $segments,
        public readonly array $query = [],
        public readonly array $body = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '';
        $path = preg_replace('#^.*/api/?#', '', $path) ?? '';

        $raw  = file_get_contents('php://input');
        $body = $raw ? json_decode($raw, true) : [];

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            segments: array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s) => $s !== '')),
            query: $_GET,
            body: is_array($body) ? $body : [],
        );
    }

    /** Valeur du corps JSON, ou $default si absente. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Valeur de la query string, ou $default si absente. */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Vérifie la présence des champs obligatoires du corps JSON
     * (une chaîne composée uniquement d'espaces est considérée comme vide).
     *
     * @throws HttpException 400 au premier champ manquant
     */
    public function requireFields(string ...$fields): void
    {
        foreach ($fields as $field) {
            $value = $this->body[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new HttpException("Le champ « $field » est obligatoire");
            }
        }
    }
}
