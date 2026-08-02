<?php
/**
 * /api/contact  → formulaire de contact
 * /api/devis    → demande de devis (routé ici aussi)
 */
function handle(string $method, array $parts, array $body): void
{
    if ($method !== 'POST') jsonError('Méthode non autorisée', 405);

    $resource = $parts[0];

    if ($resource === 'devis') {
        envoyerDevis($body);
    } else {
        envoyerContact($body);
    }
}

function envoyerContact(array $body): void
{
    require_fields($body, 'titre', 'email');

    if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) jsonError('Email invalide');

    $body['titre']       = sanitize($body['titre'] ?? '');
    $body['description'] = sanitize($body['description'] ?? '');

    $html = mailTemplate(
        'Message de contact : ' . htmlspecialchars($body['titre']),
        '<p><strong>Expéditeur :</strong> ' . htmlspecialchars($body['email']) . '</p>
         <p><strong>Objet :</strong> ' . htmlspecialchars($body['titre']) . '</p>
         <p><strong>Message :</strong></p>
         <blockquote style="border-left:3px solid #C49A2D;margin:0;padding:8px 16px;background:#f7f1e8">'
         . nl2br(htmlspecialchars($body['description'] ?? ''))
         . '</blockquote>'
    );
    sendMail(MAIL_FROM, 'Contact : ' . $body['titre'], $html);

    // Accusé de réception
    $ack = mailTemplate(
        'Nous avons bien reçu votre message',
        '<p>Bonjour,</p>
         <p>Nous avons bien reçu votre message concernant <strong>« ' . htmlspecialchars($body['titre']) . ' »</strong> et nous reviendrons vers vous dans les plus brefs délais.</p>
         <p>L\'équipe Vite &amp; Gourmand</p>'
    );
    sendMail($body['email'], 'Confirmation de réception — Vite & Gourmand', $ack);

    jsonOk(['message' => 'Message envoyé. Vous allez recevoir un accusé de réception.']);
}

function envoyerDevis(array $body): void
{
    require_fields($body, 'nom', 'email', 'date_evenement', 'nb_personnes');

    if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) jsonError('Email invalide');

    $body['nom']     = sanitize($body['nom'] ?? '');
    $body['message'] = sanitize($body['message'] ?? '');

    $lignesSelection = '';
    if (!empty($body['selection']) && is_array($body['selection'])) {
        foreach ($body['selection'] as $item) {
            $lignesSelection .= '<li>' . htmlspecialchars($item['titre'] ?? '') . ' × ' . ((int)($item['quantite'] ?? 0)) . '</li>';
        }
    } elseif (!empty($body['materiel'])) {
        $lignesSelection = '<li>' . nl2br(htmlspecialchars(sanitize($body['materiel']))) . '</li>';
    }

    $html = mailTemplate(
        'Demande de devis location de matériel',
        '<p><strong>Contact :</strong> ' . htmlspecialchars($body['nom']) . ' (' . htmlspecialchars($body['email']) . ')</p>
         <p><strong>Téléphone :</strong> ' . htmlspecialchars($body['telephone'] ?? 'Non renseigné') . '</p>
         <p><strong>Date de l\'événement :</strong> ' . htmlspecialchars($body['date_evenement']) . '</p>
         <p><strong>Nombre de personnes :</strong> ' . (int)$body['nb_personnes'] . '</p>
         <p><strong>Matériel souhaité :</strong></p><ul>' . ($lignesSelection ?: '<li>Non précisé</li>') . '</ul>
         ' . (!empty($body['message']) ? '<p><strong>Message :</strong> ' . nl2br(htmlspecialchars($body['message'])) . '</p>' : '')
    );
    sendMail(MAIL_FROM, 'Demande de devis location — ' . $body['nom'], $html);

    $ack = mailTemplate(
        'Votre demande de devis est bien reçue',
        '<p>Bonjour <strong>' . htmlspecialchars($body['nom']) . '</strong>,</p>
         <p>Nous avons bien reçu votre demande de devis pour le <strong>' . htmlspecialchars($body['date_evenement']) . '</strong>.</p>
         <p>Un conseiller vous contactera sous 48h pour finaliser votre devis personnalisé.</p>
         <p>L\'équipe Vite &amp; Gourmand</p>'
    );
    sendMail($body['email'], 'Demande de devis reçue — Vite & Gourmand', $ack);

    jsonOk(['message' => 'Demande de devis envoyée. Vous serez contacté sous 48h.']);
}
