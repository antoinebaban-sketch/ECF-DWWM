<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\UtilisateurRepository;
use App\Security\PasswordPolicy;
use App\Support\Sanitizer;

/**
 * /api/utilisateurs/moi/*  — espace personnel de l'utilisateur connecté
 * GET    /utilisateurs/moi                  → profil
 * PUT    /utilisateurs/moi                  → modifier son profil
 * DELETE /utilisateurs/moi                  → supprimer le compte (pseudonymisation RGPD Art.17)
 * GET    /utilisateurs/moi/export           → export des données personnelles (RGPD Art.20)
 * GET    /utilisateurs/moi/preferences      → régimes et allergènes enregistrés
 * PUT    /utilisateurs/moi/preferences      → mettre à jour les préférences alimentaires
 */
final class UtilisateurController extends Controller
{
    private readonly UtilisateurRepository $utilisateurs;
    private readonly PasswordPolicy $passwords;

    public function __construct()
    {
        parent::__construct();
        $this->utilisateurs = new UtilisateurRepository();
        $this->passwords    = new PasswordPolicy();
    }

    public function profil(Request $request): JsonResponse
    {
        $user = $this->auth->requireUser();

        return $this->json($this->utilisateurs->profil((int) $user['id']) ?? throw HttpException::notFound('Utilisateur introuvable'));
    }

    public function modifierProfil(Request $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $id   = (int) $user['id'];

        // Changement de mot de passe optionnel
        if ($request->input('password')) {
            $this->passwords->validate($request->input('password'));
            $this->utilisateurs->changerMotDePasse($id, $this->passwords->hash($request->input('password')));
        }

        // Changement d'email : il doit rester unique
        $email = $request->input('email');
        if ($email && $email !== $user['email'] && $this->utilisateurs->emailExiste($email, $id)) {
            throw HttpException::conflict('Cette adresse email est déjà utilisée.');
        }

        $this->utilisateurs->modifierProfil($id, [
            'prenom'    => Sanitizer::text($request->input('prenom', '')),
            'nom'       => Sanitizer::text($request->input('nom', '')),
            'email'     => Sanitizer::email($request->input('email', '')),
            'telephone' => Sanitizer::text($request->input('telephone', '')),
            'ville'     => Sanitizer::text($request->input('ville', '')),
            'pays'      => Sanitizer::text($request->input('pays', '')),
            'adresse'   => Sanitizer::text($request->input('adresse', '')),
        ]);

        // Garder la session à jour avec les nouvelles infos affichées (navbar, etc.)
        $this->auth->refresh(array_filter([
            'prenom' => $request->input('prenom') ? Sanitizer::text($request->input('prenom')) : null,
            'nom'    => $request->input('nom') ? Sanitizer::text($request->input('nom')) : null,
            'email'  => $request->input('email') ? Sanitizer::email($request->input('email')) : null,
        ], static fn ($v) => $v !== null));

        return $this->message('Profil mis à jour');
    }

    // ─── Préférences alimentaires ─────────────────────────────────────────────

    public function preferences(Request $request): JsonResponse
    {
        $id = (int) $this->auth->requireUser()['id'];

        return $this->json([
            'regimes'    => $this->utilisateurs->regimesPreferes($id),
            'allergenes' => $this->utilisateurs->allergenesDeclares($id),
        ]);
    }

    public function modifierPreferences(Request $request): JsonResponse
    {
        $id = (int) $this->auth->requireUser()['id'];

        $this->utilisateurs->remplacerPreferences(
            $id,
            array_map('intval', (array) $request->input('regimes', [])),
            array_map('intval', (array) $request->input('allergenes', []))
        );

        return $this->message('Préférences enregistrées');
    }

    // ─── Suppression de compte — pseudonymisation (RGPD Art. 17) ─────────────

    public function supprimerCompte(Request $request): JsonResponse
    {
        $id   = (int) $this->auth->requireUser()['id'];
        $user = $this->utilisateurs->findById($id);

        if ($user !== null && !$user->peutSupprimerSonCompte()) {
            throw HttpException::forbidden('Les comptes employés et administrateurs ne peuvent pas être supprimés via cette route.');
        }

        // Les commandes restent rattachées à la ligne (obligation comptable)
        $this->utilisateurs->pseudonymiser($id, 'supprime_' . $id . '_' . time() . '@anonyme.local', 'Compte supprimé');
        $this->auth->logout();

        return $this->message('Votre compte a été supprimé. Vos données personnelles ont été effacées.');
    }

    // ─── Export des données personnelles (RGPD Art. 20 — portabilité) ────────

    public function exporter(Request $request): JsonResponse
    {
        $id = (int) $this->auth->requireUser()['id'];

        return new JsonResponse(
            [
                'export_date' => date('c'),
                'profil'      => $this->utilisateurs->exportProfil($id) ?? false,
                'commandes'   => $this->utilisateurs->exportCommandes($id),
                'avis'        => $this->utilisateurs->exportAvis($id),
            ],
            200,
            ['Content-Disposition' => 'attachment; filename="mes-donnees-viteetgourmand.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }
}
