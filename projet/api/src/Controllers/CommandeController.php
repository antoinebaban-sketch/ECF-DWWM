<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enum\StatutCommande;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Mail\Notifier;
use App\Models\Commande;
use App\Mongo\MongoConnection;
use App\Repositories\CommandeRepository;
use App\Support\Sanitizer;

/**
 * /api/commandes/*  (et POST /api/factures)
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
 */
final class CommandeController extends Controller
{
    private readonly CommandeRepository $commandes;
    private readonly Notifier $notifier;
    private readonly MongoConnection $mongo;

    public function __construct()
    {
        parent::__construct();
        $this->commandes = new CommandeRepository();
        $this->notifier  = new Notifier();
        $this->mongo     = new MongoConnection();
    }

    public function mesCommandes(Request $request): JsonResponse
    {
        $user      = $this->auth->requireUser();
        $commandes = $this->commandes->findByClient((int) $user['id']);

        foreach ($commandes as &$cmd) {
            $cmd['statut_label'] = StatutCommande::tryLabel($cmd['statut_commande']);
        }

        return $this->json($commandes);
    }

    public function index(Request $request): JsonResponse
    {
        $this->auth->requireStaff();

        $commandes = $this->commandes->findAll([
            'statut'    => $request->query('statut'),
            'client_id' => $request->query('client_id'),
            'q'         => $request->query('q'),
        ]);

        foreach ($commandes as &$cmd) {
            $cmd['statut_label'] = StatutCommande::tryLabel($cmd['statut_commande']);

            // Alerte : matériel non rendu 10 jours après le passage en statut "retour_materiel"
            $cmd['retour_materiel_en_retard'] = false;
            if ($cmd['statut_commande'] === StatutCommande::RetourMateriel->value && $cmd['date_retour_materiel']) {
                $jours                            = Commande::joursDepuis($cmd['date_retour_materiel']);
                $cmd['jours_depuis_retour']       = $jours;
                $cmd['retour_materiel_en_retard'] = $jours > Commande::DELAI_RETOUR_MATERIEL_JOURS;
            }
        }

        return $this->json($commandes);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->auth->requireUser();
        $cmd  = $this->commandes->findDetail($id) ?? throw HttpException::notFound('Commande introuvable');

        // Un client ne voit que ses propres commandes
        if ($user['role'] === 'client' && (int) $cmd['client_id'] !== (int) $user['id']) {
            throw HttpException::forbidden();
        }

        $cmd['statut_label'] = StatutCommande::tryLabel($cmd['statut_commande']);

        return $this->json($cmd);
    }

    public function historique(Request $request, int $id): JsonResponse
    {
        $user = $this->auth->requireUser();

        if ($user['role'] === 'client' && !$this->commandes->appartientAuClient($id, (int) $user['id'])) {
            throw HttpException::forbidden();
        }

        $historique = $this->commandes->historique($id);
        foreach ($historique as &$etape) {
            $etape['statut_label'] = StatutCommande::tryLabel($etape['statut']);
        }

        return $this->json($historique);
    }

    // ─── Passer une commande ────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $request->requireFields('menu_id', 'nombre_personne', 'date_prestation', 'adresse_livraison');

        $menuId         = (int) $request->input('menu_id');
        $nbPersonnes    = (int) $request->input('nombre_personne');
        $datePrestation = $request->input('date_prestation');
        $distanceKm     = (float) $request->input('distance_km', 0);

        // Vérification du stock et insertions liées : tout ou rien
        [$commandeId, $menu, $calcul] = $this->commandes->transaction(function () use ($request, $user, $menuId, $nbPersonnes, $datePrestation, $distanceKm): array {
            $menu = $this->commandes->findMenuForUpdate($menuId) ?? throw HttpException::notFound('Menu introuvable');

            if ((int) $menu['quantite_restante'] <= 0) {
                throw new HttpException('Ce menu n\'est plus disponible');
            }
            if ($nbPersonnes < (int) $menu['nombre_personne_mini']) {
                throw new HttpException('Ce menu nécessite au minimum ' . $menu['nombre_personne_mini'] . ' personnes');
            }

            $calcul = Commande::calculerPrixTotal(
                (float) $menu['prix_par_personne'],
                $nbPersonnes,
                (int) $menu['nombre_personne_mini'],
                $distanceKm
            );

            $commandeId = $this->commandes->creer([
                'client_id'         => $user['id'],
                'menu_id'           => $menuId,
                'nombre_personne'   => $nbPersonnes,
                'date_prestation'   => $datePrestation,
                'prix_commande'     => $calcul['prix_total'],
                'adresse_livraison' => Sanitizer::text($request->input('adresse_livraison')),
                'theme_id'          => $request->input('theme_id') ? (int) $request->input('theme_id') : null,
            ]);

            $this->commandes->ajouterHistorique($commandeId, StatutCommande::EnAttente->value);
            if ($distanceKm > 0) {
                $this->commandes->creerLivraison($commandeId, $datePrestation, $distanceKm, $calcul['frais_livraison']);
            }
            $this->commandes->decrementerStock($menuId);

            return [$commandeId, $menu, $calcul];
        });

        $client = $this->commandes->findClientContact((int) $user['id']);
        if ($client !== null) {
            $this->notifier->confirmationCommande($client, $commandeId, $menu['titre'], $nbPersonnes, $datePrestation, $calcul['prix_total']);
        }

        // Un document Mongo par commande : sert au graphique "commandes par menu" côté admin
        $this->mongo->insert('commandes', [
            'commande_id' => $commandeId,
            'menu_id'     => $menuId,
            'menu_titre'  => $menu['titre'],
            'montant'     => $calcul['prix_total'],
            'date'        => date('Y-m-d'),
        ]);

        return $this->json([
            'commande_id'     => $commandeId,
            'prix_total'      => $calcul['prix_total'],
            'frais_livraison' => $calcul['frais_livraison'],
            'message'         => 'Commande enregistrée. Vous allez recevoir un email de confirmation.',
        ], 201);
    }

    // ─── Changer le statut (employé/admin) ─────────────────────────────────────

    public function changerStatut(Request $request, int $id): JsonResponse
    {
        $user = $this->auth->requireStaff();
        $request->requireFields('statut');

        $statut = StatutCommande::tryFrom($request->input('statut'))
            ?? throw new HttpException('Statut invalide. Valeurs acceptées : ' . implode(', ', StatutCommande::values()));

        // L'employé doit avoir contacté le client avant d'annuler une commande
        if ($statut === StatutCommande::Annulee) {
            $request->requireFields('motif', 'mode_contact');
        }

        $cmd = $this->commandes->findById($id) ?? throw HttpException::notFound('Commande introuvable');

        $this->commandes->updateStatut(
            $id,
            $statut->value,
            (int) $user['id'],
            Sanitizer::textOrNull($request->input('motif')),
            Sanitizer::textOrNull($request->input('mode_contact'))
        );
        $this->commandes->ajouterHistorique($id, $statut->value);

        $client = match ($statut) {
            StatutCommande::RetourMateriel, StatutCommande::Terminee => $this->commandes->findClientContact((int) $cmd['client_id']),
            default => null,
        };
        if ($client !== null) {
            $statut === StatutCommande::RetourMateriel
                ? $this->notifier->retourMateriel($client, $id)
                : $this->notifier->invitationAvis($client, $id);
        }

        return $this->message('Statut mis à jour');
    }

    // ─── Annuler une commande (client) ──────────────────────────────────────────
    // Libre tant que l'employé n'a pas encore accepté la commande.

    public function annuler(Request $request, int $id): JsonResponse
    {
        $user = $this->auth->requireUser();
        $row  = $this->commandes->findByIdAndClient($id, (int) $user['id']) ?? throw HttpException::notFound('Commande introuvable');

        $cmd = Commande::fromRow($row);
        if (!$cmd->modifiableParClient()) {
            throw new HttpException('Cette commande ne peut plus être annulée (statut : ' . $row['statut_commande'] . ')');
        }

        $this->commandes->annuler($id);
        $this->commandes->ajouterHistorique($id, StatutCommande::Annulee->value);
        if ($cmd->menuId !== null) {
            $this->commandes->incrementerStock($cmd->menuId);
        }

        return $this->message('Commande annulée');
    }

    // ─── Modifier une commande (client) ─────────────────────────────────────────
    // Tout est modifiable sauf le menu, tant que le statut est "en_attente".

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->auth->requireUser();
        $row  = $this->commandes->findForModification($id, (int) $user['id']) ?? throw HttpException::notFound('Commande introuvable');

        $cmd = Commande::fromRow($row);
        if (!$cmd->modifiableParClient()) {
            throw new HttpException('Cette commande ne peut plus être modifiée (statut : ' . $row['statut_commande'] . ')');
        }

        $nbPersonnes = (int) $request->input('nombre_personne', $row['nombre_personne']);
        if ($nbPersonnes < (int) $row['nombre_personne_mini']) {
            throw new HttpException('Ce menu nécessite au minimum ' . $row['nombre_personne_mini'] . ' personnes');
        }

        $calcul = Commande::calculerPrixTotal(
            (float) $row['prix_par_personne'],
            $nbPersonnes,
            (int) $row['nombre_personne_mini'],
            (float) $request->input('distance_km', 0)
        );

        $this->commandes->modifier(
            $id,
            $nbPersonnes,
            $request->input('date_prestation'),
            Sanitizer::textOrNull($request->input('adresse_livraison')),
            $calcul['prix_total']
        );

        return $this->json(['message' => 'Commande mise à jour', 'prix_total' => $calcul['prix_total']]);
    }

    // ─── Demander une facture ────────────────────────────────────────────────────

    public function demanderFacture(Request $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $request->requireFields('commande_id');

        $cmd = $this->commandes->findFacture((int) $request->input('commande_id'), (int) $user['id'])
            ?? throw HttpException::notFound('Commande introuvable');

        // Email de réception éventuellement différent de celui du compte (facturation
        // entreprise par ex.) — saisi dans le formulaire, à transmettre tel quel.
        $this->notifier->demandeFacture(
            $cmd,
            $request->input('email') ? Sanitizer::text($request->input('email')) : $cmd['email'],
            $request->input('entreprise') ? Sanitizer::text($request->input('entreprise')) : null,
            $request->input('commentaire') ?: null
        );

        return $this->message('Votre demande de facture a été transmise.');
    }
}
