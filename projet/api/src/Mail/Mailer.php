<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Envoi d'emails transactionnels : mail() natif en local, API HTTP Brevo en
 * production (un conteneur Docker n'embarque pas de serveur mail).
 *
 * sendMail() / mailTemplate() (helpers.php) délèguent ici, pour que tous les
 * contrôleurs — migrés en classes ou encore procéduraux — envoient leurs
 * emails par le même chemin.
 */
final class Mailer
{
    public function send(string $to, string $subject, string $htmlBody): bool
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

        // Production : mail() nécessite un serveur mail local, absent sur un
        // hébergeur conteneurisé (Render). On passe par l'API HTTP de Brevo.
        $payload = json_encode([
            'sender'      => ['name' => MAIL_NAME, 'email' => MAIL_FROM],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $htmlBody,
        ]);
        if ($payload === false) {
            return false;
        }

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

    public function template(string $titre, string $corps): string
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
}
