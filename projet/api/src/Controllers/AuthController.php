<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enum\Role;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Mail\Notifier;
use App\Repositories\UtilisateurRepository;
use App\Security\PasswordPolicy;
use App\Support\Sanitizer;

/**
 * /api/auth/*
 * POST /auth/login             → connexion (ouvre la session)
 * POST /auth/register          → inscription d'un client
 * POST /auth/logout            → déconnexion
 * GET  /auth/me                → utilisateur connecté (affichage front)
 * POST /auth/forgot-password   → envoi d'un lien de réinitialisation
 * POST /auth/reset-password    → nouveau mot de passe via ce lien
 */
final class AuthController extends Controller
{
    /** Durée de validité du lien "mot de passe oublié". */
    private const RESET_TOKEN_TTL = 3600;

    private readonly UtilisateurRepository $utilisateurs;
    private readonly PasswordPolicy $passwords;
    private readonly Notifier $notifier;

    public function __construct()
    {
        parent::__construct();
        $this->utilisateurs = new UtilisateurRepository();
        $this->passwords    = new PasswordPolicy();
        $this->notifier     = new Notifier();
    }

    public function login(Request $request): JsonResponse
    {
        $request->requireFields('email', 'password');

        $user = $this->utilisateurs->findByEmail(Sanitizer::email($request->input('email')));

        // Même message que le compte existe ou non : on ne révèle pas les emails inscrits
        if ($user === null || !$this->passwords->verify($request->input('password'), $user->passwordHash())) {
            throw new HttpException('Email ou mot de passe incorrect', 401);
        }
        if (!$user->actif) {
            throw HttpException::forbidden('Ce compte a été désactivé. Contactez l\'administrateur.');
        }

        return $this->json($this->auth->login($user->toSession()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout();

        return $this->message('Déconnecté');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->json($this->auth->user() ?: throw new HttpException('Non connecté', 401));
    }

    public function register(Request $request): JsonResponse
    {
        $request->requireFields('email', 'password', 'prenom', 'nom', 'telephone', 'adresse');

        if (!Sanitizer::isEmail($request->input('email'))) {
            throw new HttpException('Adresse email invalide');
        }
        $this->passwords->validate($request->input('password'));
        if (!$request->input('consentement_rgpd', false)) {
            throw new HttpException('Le consentement RGPD est obligatoire');
        }

        $email = Sanitizer::email($request->input('email'));
        if ($this->utilisateurs->emailExiste($email)) {
            throw HttpException::conflict('Cette adresse email est déjà utilisée');
        }

        $id = $this->utilisateurs->creerClient([
            'email'     => $email,
            'prenom'    => Sanitizer::text($request->input('prenom')),
            'nom'       => Sanitizer::text($request->input('nom')),
            'telephone' => Sanitizer::text($request->input('telephone')),
            'ville'     => Sanitizer::text($request->input('ville', '')),
            'pays'      => Sanitizer::text($request->input('pays', '')),
            'adresse'   => Sanitizer::text($request->input('adresse')),
        ], $this->passwords->hash($request->input('password')));

        $this->notifier->bienvenue($request->input('email'), $request->input('prenom'));

        $session = $this->auth->login([
            'id'     => $id,
            'email'  => $email,
            'role'   => Role::Client->value,
            'prenom' => $request->input('prenom'),
            'nom'    => $request->input('nom'),
        ]);

        return $this->json($session, 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->requireFields('email');

        // Réponse identique que le compte existe ou non (pas d'énumération des comptes)
        $reponse = $this->message('Si ce compte existe, un email a été envoyé.');

        $user = $this->utilisateurs->findByEmail(Sanitizer::email($request->input('email')));
        if ($user === null) {
            return $reponse;
        }

        $token = $this->passwords->token();
        $this->utilisateurs->enregistrerResetToken($user->id, $token, date('Y-m-d H:i:s', time() + self::RESET_TOKEN_TTL));
        $this->notifier->reinitialisationMotDePasse($request->input('email'), $user->prenom, $token);

        return $reponse;
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->requireFields('token', 'password');
        $this->passwords->validate($request->input('password'));

        $id = $this->utilisateurs->findIdByValidResetToken($request->input('token'))
            ?? throw new HttpException('Lien invalide ou expiré');

        $this->utilisateurs->changerMotDePasse($id, $this->passwords->hash($request->input('password')));

        return $this->message('Mot de passe modifié avec succès.');
    }
}
