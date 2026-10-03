<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Catalogue des emails transactionnels de l'application. Chaque méthode
 * correspond à un événement métier (inscription, commande, changement de
 * statut...) : les contrôleurs appellent la méthode, sans manipuler de HTML.
 *
 * Toute donnée saisie par un utilisateur est échappée (htmlspecialchars)
 * avant d'être insérée dans le corps du mail.
 */
final class Notifier
{
    private const BOUTON = 'background:#5C1A1A;color:#C49A2D;padding:10px 20px;text-decoration:none;border-radius:4px';

    public function __construct(private readonly Mailer $mailer = new Mailer())
    {
    }

    // ─── Compte ────────────────────────────────────────────────────────────────

    public function bienvenue(string $email, string $prenom): void
    {
        $this->envoyer($email, 'Bienvenue chez Vite & Gourmand', 'Bienvenue chez Vite & Gourmand !',
            '<p>Bonjour <strong>' . $this->e($prenom) . '</strong>,</p>
             <p>Votre compte a bien été créé. Vous pouvez dès maintenant parcourir nos menus et passer commande.</p>
             <p><a href="' . APP_URL . '/menu.html" style="' . self::BOUTON . '">Découvrir nos menus</a></p>
             <p>À bientôt,<br>L\'équipe Vite &amp; Gourmand</p>'
        );
    }

    public function reinitialisationMotDePasse(string $email, string $prenom, string $token): void
    {
        $lien = APP_URL . '/reset-password.html?token=' . $token;
        $this->envoyer($email, 'Réinitialisation de votre mot de passe', 'Réinitialisation de votre mot de passe',
            '<p>Bonjour <strong>' . $this->e($prenom) . '</strong>,</p>
             <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe (valable 1 heure) :</p>
             <p><a href="' . $lien . '" style="' . self::BOUTON . '">Réinitialiser mon mot de passe</a></p>
             <p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>'
        );
    }

    /** L'employé choisit lui-même son mot de passe : il n'est jamais envoyé par email. */
    public function activationEmploye(string $email, string $prenom, string $token): void
    {
        $lien = APP_URL . '/reset-password.html?token=' . $token;
        $this->envoyer($email, 'Votre compte employé — Vite & Gourmand', 'Votre compte employé Vite & Gourmand',
            '<p>Bonjour <strong>' . $this->e($prenom) . '</strong>,</p>
             <p>Un compte employé a été créé pour vous sur le portail Vite &amp; Gourmand.</p>
             <p>Cliquez sur le lien ci-dessous pour choisir votre mot de passe (valable 48 heures) :</p>
             <p><a href="' . $lien . '" style="' . self::BOUTON . '">Définir mon mot de passe</a></p>
             <p>Une fois votre mot de passe défini, connectez-vous depuis : <a href="' . APP_URL . '/employe.html">' . APP_URL . '/employe.html</a></p>'
        );
    }

    // ─── Commandes ─────────────────────────────────────────────────────────────

    public function confirmationCommande(array $client, int $commandeId, string $menuTitre, int $nbPersonnes, string $datePrestation, float $total): void
    {
        $ligne = 'style="padding:8px;border-bottom:1px solid #ede3d0"';
        $this->envoyer($client['email'], 'Confirmation commande n°' . $commandeId . ' — Vite & Gourmand', 'Confirmation de commande n°' . $commandeId,
            '<p>Bonjour <strong>' . $this->e($client['prenom']) . '</strong>,</p>
             <p>Votre commande a bien été enregistrée.</p>
             <table style="width:100%;border-collapse:collapse;margin:16px 0">
               <tr><td ' . $ligne . '><strong>Menu</strong></td><td ' . $ligne . '>' . $this->e($menuTitre) . '</td></tr>
               <tr><td ' . $ligne . '><strong>Personnes</strong></td><td ' . $ligne . '>' . $nbPersonnes . '</td></tr>
               <tr><td ' . $ligne . '><strong>Date</strong></td><td ' . $ligne . '>' . $this->e($datePrestation) . '</td></tr>
               <tr><td style="padding:8px"><strong>Total</strong></td><td style="padding:8px"><strong>' . $this->prix($total) . ' €</strong></td></tr>
             </table>
             <p>Un de nos employés prendra contact avec vous pour confirmer les détails.</p>'
        );
    }

    /** Statut "retour_materiel" : rappel du délai de 10 jours et de la pénalité de 600 €. */
    public function retourMateriel(array $client, int $commandeId): void
    {
        $this->envoyer($client['email'], 'Retour matériel requis — Vite & Gourmand', 'Retour du matériel — Commande n°' . $commandeId,
            '<p>Bonjour <strong>' . $this->e($client['prenom']) . '</strong>,</p>
             <p>Votre prestation est terminée. Merci de retourner le matériel loué <strong>dans les 10 jours ouvrés</strong>.</p>
             <p>Passé ce délai, une pénalité de <strong>600 €</strong> sera appliquée conformément à nos conditions générales.</p>
             <p>Pour organiser le retour : <a href="mailto:contact@viteetgourmand.fr">contact@viteetgourmand.fr</a></p>'
        );
    }

    /** Statut "terminée" : invitation à déposer un avis. */
    public function invitationAvis(array $client, int $commandeId): void
    {
        $lien = APP_URL . '/MonCompte.html#avis-' . $commandeId;
        $this->envoyer($client['email'], 'Votre avis compte — Vite & Gourmand', 'Donnez votre avis sur votre prestation',
            '<p>Bonjour <strong>' . $this->e($client['prenom']) . '</strong>,</p>
             <p>Votre commande n°' . $commandeId . ' est terminée. Nous espérons que vous avez passé un excellent moment !</p>
             <p>Votre avis nous aide à nous améliorer. Laissez un commentaire en quelques clics :</p>
             <p><a href="' . $lien . '" style="' . self::BOUTON . '">Donner mon avis</a></p>'
        );
    }

    /** Transmise à l'équipe (MAIL_FROM), pas au client. */
    public function demandeFacture(array $cmd, string $emailReception, ?string $entreprise, ?string $commentaire): void
    {
        $this->envoyer(MAIL_FROM, 'Demande de facture — Commande n°' . $cmd['commande_id'], 'Demande de facture — Commande n°' . $cmd['commande_id'],
            '<p>Bonjour,</p>
             <p><strong>' . $this->e($cmd['prenom'] . ' ' . $cmd['nom']) . '</strong> demande une facture pour la commande suivante :</p>
             <ul>
               <li>Commande n° : <strong>' . $cmd['commande_id'] . '</strong></li>
               <li>Menu : ' . $this->e($cmd['menu_titre'] ?? '') . '</li>
               <li>Date : ' . $cmd['date_prestation'] . '</li>
               <li>Montant : ' . $this->prix((float) $cmd['prix_commande']) . ' €</li>
               <li>Adresse : ' . $this->e($cmd['adresse'] . ', ' . $cmd['ville']) . '</li>
               <li>Email client : ' . $this->e($cmd['email']) . '</li>
               <li>Email de réception souhaité : ' . $this->e($emailReception) . '</li>'
               . ($entreprise ? '<li>Entreprise : ' . $this->e($entreprise) . '</li>' : '') . '
             </ul>
             ' . ($commentaire ? '<p>Commentaire : ' . $this->e($commentaire) . '</p>' : '')
        );
    }

    // ─── Formulaires publics ───────────────────────────────────────────────────

    /** Message transmis à l'équipe + accusé de réception à l'expéditeur. */
    public function contact(string $email, string $titre, string $description): void
    {
        $this->envoyer(MAIL_FROM, 'Contact : ' . $titre, 'Message de contact : ' . $this->e($titre),
            '<p><strong>Expéditeur :</strong> ' . $this->e($email) . '</p>
             <p><strong>Objet :</strong> ' . $this->e($titre) . '</p>
             <p><strong>Message :</strong></p>
             <blockquote style="border-left:3px solid #C49A2D;margin:0;padding:8px 16px;background:#f7f1e8">'
             . nl2br($this->e($description))
             . '</blockquote>'
        );

        $this->envoyer($email, 'Confirmation de réception — Vite & Gourmand', 'Nous avons bien reçu votre message',
            '<p>Bonjour,</p>
             <p>Nous avons bien reçu votre message concernant <strong>« ' . $this->e($titre) . ' »</strong> et nous reviendrons vers vous dans les plus brefs délais.</p>
             <p>L\'équipe Vite &amp; Gourmand</p>'
        );
    }

    /**
     * Demande de devis de location de matériel transmise à l'équipe + accusé
     * de réception au demandeur.
     *
     * @param string[] $lignesMateriel Lignes déjà prêtes à afficher (non échappées)
     */
    public function devis(array $demande, array $lignesMateriel): void
    {
        $liste = $lignesMateriel
            ? implode('', array_map(fn (string $l) => '<li>' . nl2br($this->e($l)) . '</li>', $lignesMateriel))
            : '<li>Non précisé</li>';

        $this->envoyer(MAIL_FROM, 'Demande de devis location — ' . $demande['nom'], 'Demande de devis location de matériel',
            '<p><strong>Contact :</strong> ' . $this->e($demande['nom']) . ' (' . $this->e($demande['email']) . ')</p>
             <p><strong>Téléphone :</strong> ' . $this->e($demande['telephone'] ?? 'Non renseigné') . '</p>
             <p><strong>Date de l\'événement :</strong> ' . $this->e($demande['date_evenement']) . '</p>
             <p><strong>Nombre de personnes :</strong> ' . (int) $demande['nb_personnes'] . '</p>
             <p><strong>Matériel souhaité :</strong></p><ul>' . $liste . '</ul>
             ' . (!empty($demande['message']) ? '<p><strong>Message :</strong> ' . nl2br($this->e($demande['message'])) . '</p>' : '')
        );

        $this->envoyer($demande['email'], 'Demande de devis reçue — Vite & Gourmand', 'Votre demande de devis est bien reçue',
            '<p>Bonjour <strong>' . $this->e($demande['nom']) . '</strong>,</p>
             <p>Nous avons bien reçu votre demande de devis pour le <strong>' . $this->e($demande['date_evenement']) . '</strong>.</p>
             <p>Un conseiller vous contactera sous 48h pour finaliser votre devis personnalisé.</p>
             <p>L\'équipe Vite &amp; Gourmand</p>'
        );
    }

    // ─── Interne ───────────────────────────────────────────────────────────────

    private function envoyer(string $to, string $sujet, string $titre, string $corps): void
    {
        $this->mailer->send($to, $sujet, $this->mailer->template($titre, $corps));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value);
    }

    private function prix(float $montant): string
    {
        return number_format($montant, 2, ',', ' ');
    }
}
