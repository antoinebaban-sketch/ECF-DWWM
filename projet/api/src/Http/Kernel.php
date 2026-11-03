<?php

declare(strict_types=1);

namespace App\Http;

use PDOException;
use Throwable;

/**
 * Point d'entrée objet de l'API : prépare la requête (session),
 * la confie au Router, puis envoie la réponse. C'est aussi le seul endroit
 * qui transforme les exceptions en réponses JSON.
 */
final class Kernel
{
    public function __construct(private readonly Router $router)
    {
    }

    public function handle(): void
    {
        $this->startSession();

        try {
            $response = $this->router->dispatch(Request::fromGlobals());
        } catch (HttpException $e) {
            $response = JsonResponse::error($e->getMessage(), $e->status());
        } catch (PDOException $e) {
            error_log('[VG PDO] ' . $e->getMessage());
            $response = JsonResponse::error('Erreur base de données', 500);
        } catch (Throwable $e) {
            error_log('[VG] ' . $e->getMessage());
            $response = JsonResponse::error('Erreur serveur', 500);
        }

        $response->send();
    }

    /**
     * Pas d'en-têtes CORS : le front-end et l'API sont servis par le même
     * domaine, aucun appel n'est cross-origin. Le navigateur bloque donc la
     * lecture des réponses par un autre site.
     *
     * HttpOnly : le cookie PHPSESSID n'est jamais lisible en JS (protection XSS).
     * Secure   : détecté via HTTPS direct ou via le proxy TLS de l'hébergeur (Render
     *            termine le HTTPS en amont, la requête arrive en HTTP côté conteneur).
     * SameSite : "Lax", le cookie n'est pas envoyé sur les requêtes POST, PUT ou
     *            DELETE émises depuis un autre site (protection CSRF).
     */
    private function startSession(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
