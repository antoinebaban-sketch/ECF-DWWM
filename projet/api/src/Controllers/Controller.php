<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Http\JsonResponse;

/**
 * Classe mère des contrôleurs : donne accès au service d'authentification et
 * au raccourci de construction des réponses JSON.
 *
 * Rôle d'un contrôleur (le "C" de MVC) : contrôler les droits, valider
 * l'entrée, appeler le modèle / le repository, puis renvoyer une réponse.
 * Il ne contient ni SQL (repositories) ni règle de calcul (modèles).
 */
abstract class Controller
{
    protected readonly AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    protected function message(string $message, int $status = 200): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
