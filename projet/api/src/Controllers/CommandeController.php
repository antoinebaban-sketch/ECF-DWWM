<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Enum\StatutCommande;
use App\Mail\Mailer;
use App\Models\Commande;
use App\Mongo\MongoConnection;
use App\Repositories\CommandeRepository;

/**
 * /api/commandes/*  (et /api/factures, alias historique routé ici)
 *
 * GET  /commandes/mes-commandes     → commandes du client connecté
 * GET  /commandes                   → toutes commandes (employé/admin)
 * GET  /commandes/{id}              → détail
 * GET  /commandes/{id}/historique   → timeline statuts
 * POST /commandes                   → passer une commande
 * PUT  /commandes/{id}/statut       → changer statut (employé/admin)
 * PUT  /commandes/{id}/annuler      → annuler (client, si pas encore acceptée)
 * PUT  /commandes/{id}              → modifier (client, si pas encore acceptée)
 * POST /factures                    → demande de facture
 *
 * Première brique de l'API migrée du modèle procédural vers des classes :
 * le routage interne et le contrat JSON renvoyé au front ne changent pas.
 */
final class CommandeController
{
    public function __construct(
        private readonly CommandeRepository $commandes = new CommandeRepository(),
        private readonly AuthService $auth = new AuthService(),
        private readonly Mailer $mailer = new Mailer(),
        private readonly MongoConnection $mongo = new MongoConnection(),
    ) {
    }

    public function handle(string $method, array $parts, array $body): void
    {
        $seg = $parts[1] ?? null;
        $id  = is_numeric($seg) ? (int) $seg : null;
        $sub = $id !== null ? ($parts[2] ?? null) : null;

        if ($parts[0] === 'factures' && $method === 'POST') {
            $this->demanderFacture($body);

            return;
        }

        match (true) {
            $method === 'GET'  && $seg === 'mes-commandes'   => $this->mesCommandes(),
            $method === 'GET'  && $id === null                => $this->toutes(),
            $method === 'GET'  && $id !== null && !$sub       => $this->detail($id),
            $method === 'GET'  && $sub === 'historique'       => $this->historique($id),
            $method === 'POST' && $id === null                => $this->creer($body),
            $method === 'PUT'  && $sub === 'statut'           => $this->changerStatut($id, $body),
            $method === 'PUT'  && $sub === 'annuler'          => $this->annuler($id, $body),
            $method === 'PUT'  && $id !== null && !$sub       => $this->modifier($id, $body),
            default => jsonError('Route commandes inconnue', 404),
        };
    }

    // ─── Mes commandes ─────────────────────────────────────────────────────────

    private function mesCommandes(): void
    {
        $user      = $this->auth->requireUser();
        $commandes = $this->commandes->findByClient((int) $user['id']);

        foreach ($commandes as &$cmd) {
            $cmd['statut_label'] = StatutCommande::tryLabel($cmd['statut_commande']);
        }

        jsonOk($commandes);
    }

    // ─── Toutes les commandes (employé/admin) ─────────────────────────────────

    private function toutes(): void
    {
        $this->auth->requireRole('employe', 'administrateur');

        $commandes = $this->commandes->findAll([
            'statut'    => $_GET['statut'] ?? null,
            'client_id' => $_GET['client_id'] ?? null,
            'q'         => $_GET['q'] ?? null,
        ]);

        foreach ($commandes as &$cmd) {
            $cmd['statut_label'] = StatutCommande::tryLabel($cmd['statut_commande']);

            // Alerte : matériel non rendu 10 jours après le passage en statut "retour_materiel"
            $cmd['retour_materiel_en_retard'] = false;
            if ($cmd['statut_commande'] === 'retour_materiel' && $cmd['date_retour_materiel']) {
                $jours                            = (int) floor((time() - strtotime($cmd['date_retour_materiel'])) / 86400);
                $cmd['jours_depuis_retour']       = $jours;
                $cmd['retour_materiel_en_retard'] = $jours > 10;
            }
        }

        jsonOk($commandes);
    }

    // ─── Détail d'une commande ─────────────────────────────────────────────────

    private function detail(int $id): void
    {
        $user = $this->auth->requireUser();
        $cmd  = $this->commandes->findDetail($id);
        if ($cmd === null) {
            jsonError('Commande introuvable', 404);
        }

        // Un client ne voit que ses propres commandes
        if ($user['role'] === 'client' && (int) $cmd['client_id'] !== (int) $user['id']) {
            jsonError('Accès interdit', 403);
        }

        $cmd['statut_label'] = StatutCommande::tryLabel($cmd['statut_commande']);
        jsonOk($cmd);
    }

    // ─── Historique des statuts ─────────────────────────────────────────────────

    private function historique(int $id): void
    {
        $user = $this->auth->requireUser();

        if ($user['role'] === 'client' && !$this->commandes->appartientAuClient($id, (int) $user['id'])) {
            jsonError('Accès interdit', 403);
        }

        $hist = $this->commandes->historique($id);
        foreach ($hist as &$h) {
            $h['statut_label'] = StatutCommande::tryLabel($h['statut']);
        }

        jsonOk($hist);
    }

    // ─── Passer une commande ────────────────────────────────────────────────────

    private function creer(array $body): void
    {
        $user = $this->auth->requireUser();
        require_fields($body, 'menu_id', 'nombre_personne', 'date_prestation', 'adresse_livraison');

        $this->commandes->beginTransaction();

        $menu = $this->commandes->findMenuForUpdate((int) $body['menu_id']);
        if ($menu === null) {
            $this->commandes->rollBack();
            jsonError('Menu introuvable', 404);
        }
        if ((int) $menu['quantite_restante'] <= 0) {
            $this->commandes->rollBack();
            jsonError('Ce menu n\'est plus disponible');
        }

        $nbPersonnes = (int) $body['nombre_personne'];
        if ($nbPersonnes < (int) $menu['nombre_personne_mini']) {
            $this->commandes->rollBack();
            jsonError('Ce menu nécessite au minimum ' . $menu['nombre_personne_mini'] . ' personnes');
        }

        $distKm  = (float) ($body['distance_km'] ?? 0);
        $calcul  = Commande::calculerPrixTotal(
            (float) $menu['prix_par_personne'],
            $nbPersonnes,
            (int) $menu['nombre_personne_mini'],
            $distKm
        );

        $commandeId = $this->commandes->creer([
            'client_id'        => $user['id'],
            'menu_id'          => (int) $body['menu_id'],
            'nombre_personne'  => $nbPersonnes,
            'date_prestation'  => $body['date_prestation'],
            'prix_commande'    => $calcul['prix_total'],
            'adresse_livraison'=> sanitize($body['adresse_livraison']),
            'theme_id'         => !empty($body['theme_id']) ? (int) $body['theme_id'] : null,
        ]);

        $this->commandes->ajouterHistorique($commandeId, 'en_attente');

        if ($distKm > 0) {
            $this->commandes->creerLivraison($commandeId, $body['date_prestation'], $distKm, $calcul['frais_livraison']);
        }

        $this->commandes->decrementerStock((int) $body['menu_id']);
        $this->commandes->commit();

        // Mail de confirmation
        $client = $this->commandes->findClientContact((int) $user['id']);
        if ($client !== null) {
            $html = $this->mailer->template(
                'Confirmation de commande n°' . $commandeId,
                '<p>Bonjour <strong>' . htmlspecialchars($client['prenom']) . '</strong>,</p>
                 <p>Votre commande a bien été enregistrée.</p>
                 <table style="width:100%;border-collapse:collapse;margin:16px 0">
                   <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Menu</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . htmlspecialchars($menu['titre']) . '</td></tr>
                   <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Personnes</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . $nbPersonnes . '</td></tr>
                   <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Date</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . htmlspecialchars($body['date_prestation']) . '</td></tr>
                   <tr><td style="padding:8px"><strong>Total</strong></td><td style="padding:8px"><strong>' . number_format($calcul['prix_total'], 2, ',', ' ') . ' €</strong></td></tr>
                 </table>
                 <p>Un de nos employés prendra contact avec vous pour confirmer les détails.</p>'
            );
            $this->mailer->send($client['email'], 'Confirmation commande n°' . $commandeId . ' — Vite & Gourmand', $html);
        }

        // Un document Mongo par commande : sert au graphique "commandes par menu" côté admin
        $this->mongo->insert('commandes', [
            'commande_id' => $commandeId,
            'menu_id'     => (int) $body['menu_id'],
            'menu_titre'  => $menu['titre'],
            'montant'     => $calcul['prix_total'],
            'date'        => date('Y-m-d'),
        ]);

        jsonOk([
            'commande_id'     => $commandeId,
            'prix_total'      => $calcul['prix_total'],
            'frais_livraison' => $calcul['frais_livraison'],
            'message'         => 'Commande enregistrée. Vous allez recevoir un email de confirmation.',
        ], 201);
    }

    // ─── Changer le statut (employé/admin) ─────────────────────────────────────

    private function changerStatut(int $id, array $body): void
    {
        $user = $this->auth->requireRole('employe', 'administrateur');
        require_fields($body, 'statut');

        if (StatutCommande::tryFrom($body['statut']) === null) {
            jsonError('Statut invalide. Valeurs acceptées : ' . implode(', ', StatutCommande::values()));
        }

        // L'employé doit avoir contacté le client avant d'annuler une commande
        if ($body['statut'] === StatutCommande::Annulee->value) {
            require_fields($body, 'motif', 'mode_contact');
        }

        $cmd = $this->commandes->findById($id);
        if ($cmd === null) {
            jsonError('Commande introuvable', 404);
        }

        $motif       = isset($body['motif']) ? sanitize($body['motif']) : null;
        $modeContact = isset($body['mode_contact']) ? sanitize($body['mode_contact']) : null;

        $this->commandes->updateStatut($id, $body['statut'], (int) $user['id'], $motif, $modeContact);
        $this->commandes->ajouterHistorique($id, $body['statut']);

        // Mail "en attente retour matériel"
        if ($body['statut'] === StatutCommande::RetourMateriel->value) {
            $client = $this->commandes->findClientContact((int) $cmd['client_id']);
            if ($client !== null) {
                $html = $this->mailer->template(
                    'Retour du matériel — Commande n°' . $id,
                    '<p>Bonjour <strong>' . htmlspecialchars($client['prenom']) . '</strong>,</p>
                     <p>Votre prestation est terminée. Merci de retourner le matériel loué <strong>dans les 10 jours ouvrés</strong>.</p>
                     <p>Passé ce délai, une pénalité de <strong>600 €</strong> sera appliquée conformément à nos conditions générales.</p>
                     <p>Pour organiser le retour : <a href="mailto:contact@viteetgourmand.fr">contact@viteetgourmand.fr</a></p>'
                );
                $this->mailer->send($client['email'], 'Retour matériel requis — Vite & Gourmand', $html);
            }
        }

        // Mail "terminée" → invitation à déposer un avis
        if ($body['statut'] === StatutCommande::Terminee->value) {
            $client = $this->commandes->findClientContact((int) $cmd['client_id']);
            if ($client !== null) {
                $lien = APP_URL . '/MonCompte.html#avis-' . $id;
                $html = $this->mailer->template(
                    'Donnez votre avis sur votre prestation',
                    '<p>Bonjour <strong>' . htmlspecialchars($client['prenom']) . '</strong>,</p>
                     <p>Votre commande n°' . $id . ' est terminée. Nous espérons que vous avez passé un excellent moment !</p>
                     <p>Votre avis nous aide à nous améliorer. Laissez un commentaire en quelques clics :</p>
                     <p><a href="' . $lien . '" style="background:#5C1A1A;color:#C49A2D;padding:10px 20px;text-decoration:none;border-radius:4px">Donner mon avis</a></p>'
                );
                $this->mailer->send($client['email'], 'Votre avis compte — Vite & Gourmand', $html);
            }
        }

        jsonOk(['message' => 'Statut mis à jour']);
    }

    // ─── Annuler une commande (client) ──────────────────────────────────────────
    // Libre tant que l'employé n'a pas encore accepté la commande.

    private function annuler(int $id, array $body): void
    {
        $user = $this->auth->requireUser();

        $row = $this->commandes->findByIdAndClient($id, (int) $user['id']);
        if ($row === null) {
            jsonError('Commande introuvable', 404);
        }

        $cmd = Commande::fromRow($row);
        if (!$cmd->modifiableParClient()) {
            jsonError('Cette commande ne peut plus être annulée (statut : ' . $row['statut_commande'] . ')');
        }

        $this->commandes->annuler($id);
        $this->commandes->ajouterHistorique($id, StatutCommande::Annulee->value);

        if ($cmd->menuId !== null) {
            $this->commandes->incrementerStock($cmd->menuId);
        }

        jsonOk(['message' => 'Commande annulée']);
    }

    // ─── Modifier une commande (client) ─────────────────────────────────────────
    // Tout est modifiable sauf le menu, tant que le statut est "en_attente".

    private function modifier(int $id, array $body): void
    {
        $user = $this->auth->requireUser();

        $row = $this->commandes->findForModification($id, (int) $user['id']);
        if ($row === null) {
            jsonError('Commande introuvable', 404);
        }

        $cmd = Commande::fromRow($row);
        if (!$cmd->modifiableParClient()) {
            jsonError('Cette commande ne peut plus être modifiée (statut : ' . $row['statut_commande'] . ')');
        }

        $nbPersonnes = isset($body['nombre_personne']) ? (int) $body['nombre_personne'] : (int) $row['nombre_personne'];
        if ($nbPersonnes < (int) $row['nombre_personne_mini']) {
            jsonError('Ce menu nécessite au minimum ' . $row['nombre_personne_mini'] . ' personnes');
        }

        $distKm = isset($body['distance_km']) ? (float) $body['distance_km'] : 0.0;
        $calcul = Commande::calculerPrixTotal(
            (float) $row['prix_par_personne'],
            $nbPersonnes,
            (int) $row['nombre_personne_mini'],
            $distKm
        );

        $this->commandes->modifier(
            $id,
            $nbPersonnes,
            $body['date_prestation'] ?? null,
            isset($body['adresse_livraison']) ? sanitize($body['adresse_livraison']) : null,
            $calcul['prix_total']
        );

        jsonOk(['message' => 'Commande mise à jour', 'prix_total' => $calcul['prix_total']]);
    }

    // ─── Demander une facture ────────────────────────────────────────────────────

    private function demanderFacture(array $body): void
    {
        $user = $this->auth->requireUser();
        require_fields($body, 'commande_id');

        $cmd = $this->commandes->findFacture((int) $body['commande_id'], (int) $user['id']);
        if ($cmd === null) {
            jsonError('Commande introuvable', 404);
        }

        // Email de réception éventuellement différent de celui du compte (facturation
        // entreprise par ex.) — saisi dans le formulaire, à transmettre tel quel.
        $emailReception = !empty($body['email']) ? sanitize($body['email']) : $cmd['email'];
        $entreprise     = !empty($body['entreprise']) ? sanitize($body['entreprise']) : null;

        $html = $this->mailer->template(
            'Demande de facture — Commande n°' . $cmd['commande_id'],
            '<p>Bonjour,</p>
             <p><strong>' . htmlspecialchars($cmd['prenom'] . ' ' . $cmd['nom']) . '</strong> demande une facture pour la commande suivante :</p>
             <ul>
               <li>Commande n° : <strong>' . $cmd['commande_id'] . '</strong></li>
               <li>Menu : ' . htmlspecialchars($cmd['menu_titre'] ?? '') . '</li>
               <li>Date : ' . $cmd['date_prestation'] . '</li>
               <li>Montant : ' . number_format((float) $cmd['prix_commande'], 2, ',', ' ') . ' €</li>
               <li>Adresse : ' . htmlspecialchars($cmd['adresse'] . ', ' . $cmd['ville']) . '</li>
               <li>Email client : ' . htmlspecialchars($cmd['email']) . '</li>
               <li>Email de réception souhaité : ' . htmlspecialchars($emailReception) . '</li>'
               . ($entreprise ? '<li>Entreprise : ' . htmlspecialchars($entreprise) . '</li>' : '') . '
             </ul>
             ' . (!empty($body['commentaire']) ? '<p>Commentaire : ' . htmlspecialchars($body['commentaire']) . '</p>' : '')
        );
        $this->mailer->send(MAIL_FROM, 'Demande de facture — Commande n°' . $cmd['commande_id'], $html);

        jsonOk(['message' => 'Votre demande de facture a été transmise.']);
    }
}
