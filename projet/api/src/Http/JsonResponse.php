<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Réponse JSON renvoyée par un contrôleur. Le contrôleur la construit, le
 * Kernel l'envoie : aucune méthode métier n'écrit directement dans la sortie.
 */
final class JsonResponse
{
    /** @param array<string, string> $headers En-têtes supplémentaires */
    public function __construct(
        public readonly mixed $data,
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly int $flags = JSON_UNESCAPED_UNICODE,
    ) {
    }

    public static function error(string $message, int $status = 400): self
    {
        return new self(['error' => $message], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo json_encode($this->data, $this->flags);
    }
}
