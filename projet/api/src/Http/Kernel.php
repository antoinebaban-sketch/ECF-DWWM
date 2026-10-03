<?php

declare(strict_types=1);

namespace App\Http;

use PDOException;
use Throwable;

/**
 * Point d'entrée objet de l'API : prépare la requête (CORS, session),
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
        $this->cors();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);

            return;
        }

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
     * On reflète l'origine de la requête pour pouvoir autoriser l'envoi du
     * cookie de session (impossible avec Access-Control-Allow-Origin: *).
     */
    private function cors(): void
    {
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    /**
     * HttpOnly : le cookie PHPSESSID n'est jamais lisible en JS (protection XSS).
     * Secure   : détecté via HTTPS direct ou via le proxy TLS de l'hébergeur (Render
     *            termine le HTTPS en amont, la requête arrive en HTTP côté conteneur).
     * SameSite : "None" (+ Secure, obligatoire ensemble) uniquement en HTTPS pour
     *            autoriser le cookie sur les appels cross-origin avec credentials ;
     *            "Lax" en local HTTP où le cross-origin n'est pas utilisé.
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
            'samesite' => $https ? 'None' : 'Lax',
        ]);
        session_start();
    }
}
