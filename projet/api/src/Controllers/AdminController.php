<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enum\StatutAvis;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Mail\Notifier;
use App\Mongo\MongoConnection;
use App\Repositories\AvisRepository;
use App\Repositories\StatistiqueRepository;
use App\Repositories\UtilisateurRepository;
use App\Security\PasswordPolicy;
use App\Support\Sanitizer;

/**
 * /api/admin/*
 * GET  /admin/utilisateurs                 → liste tous les utilisateurs
 * POST /admin/employes                     → créer un compte employé
 * PUT  /admin/employes/{id}/desactiver     → désactiver un compte
 * PUT  /admin/employes/{id}/activer        → réactiver un compte
 * GET  /admin/stats                        → statistiques (commandes, CA, menus)
 * GET  /admin/avis                         → avis à modérer (employé/admin)
 */
final class AdminController extends Controller
{
    /** Le lien d'activation employé dure plus longtemps que "mot de passe oublié" (1h) : pas d'urgence. */
    private const ACTIVATION_TOKEN_TTL = 48 * 3600;

    private readonly UtilisateurRepository $utilisateurs;
    private readonly StatistiqueRepository $statistiques;
    private readonly AvisRepository $avis;
    private readonly PasswordPolicy $passwords;
    private readonly Notifier $notifier;
    private readonly MongoConnection $mongo;

    public function __construct()
    {
        parent::__construct();
        $this->utilisateurs = new UtilisateurRepository();
        $this->statistiques = new StatistiqueRepository();
        $this->avis         = new AvisRepository();
        $this->passwords    = new PasswordPolicy();
        $this->notifier     = new Notifier();
        $this->mongo        = new MongoConnection();
    }

    public function utilisateurs(Request $request): JsonResponse
    {
        $this->auth->requireRole('administrateur');

        return $this->json($this->utilisateurs->findAll($request->query('role')));
    }

    /**
     * Pas de mot de passe transmis par l'admin ni envoyé par email : le compte est
     * créé avec un mot de passe aléatoire jamais communiqué (connexion impossible
     * tant qu'il n'est pas remplacé) et un jeton d'activation à usage unique —
     * même mécanisme que "mot de passe oublié", réutilisé pour ce premier réglage.
     */
    public function creerEmploye(Request $request): JsonResponse
    {
        $this->auth->requireRole('administrateur');
        $request->requireFields('email', 'prenom', 'nom', 'telephone');

        if (!Sanitizer::isEmail($request->input('email'))) {
            throw new HttpException('Email invalide');
        }

        $email = Sanitizer::email($request->input('email'));
        if ($this->utilisateurs->emailExiste($email)) {
            throw HttpException::conflict('Cet email est déjà utilisé');
        }

        $token = $this->passwords->token();
        $this->utilisateurs->creerEmploye(
            [
                'email'     => $email,
                'prenom'    => Sanitizer::text($request->input('prenom')),
                'nom'       => Sanitizer::text($request->input('nom')),
                'telephone' => Sanitizer::text($request->input('telephone')),
            ],
            $this->passwords->hash($this->passwords->token()),
            $token,
            date('Y-m-d H:i:s', time() + self::ACTIVATION_TOKEN_TTL)
        );

        $this->notifier->activationEmploye($request->input('email'), $request->input('prenom'), $token);

        return $this->message('Compte employé créé. Un email d\'activation a été envoyé.', 201);
    }

    public function desactiver(Request $request, int $id): JsonResponse
    {
        return $this->changerStatutCompte($id, false);
    }

    public function activer(Request $request, int $id): JsonResponse
    {
        return $this->changerStatutCompte($id, true);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->auth->requireRole('administrateur');

        // Nombre de commandes par menu — exigé depuis la base non relationnelle (MongoDB)
        $commandesParMenu = $this->mongo->aggregate('commandes', [
            ['$group' => [
                '_id'          => '$menu_id',
                'menu_titre'   => ['$first' => '$menu_titre'],
                'nb_commandes' => ['$sum' => 1],
            ]],
            ['$sort' => ['nb_commandes' => -1]],
        ]);

        return $this->json([
            'ca_par_mois'        => $this->statistiques->chiffreAffairesParMois(),
            'ca_par_menu'        => $this->statistiques->chiffreAffairesParMenu([
                'menu_id'    => $request->query('menu_id'),
                'date_debut' => $request->query('date_debut'),
                'date_fin'   => $request->query('date_fin'),
            ]),
            'commandes_par_menu' => $commandesParMenu,
            'globales'           => $this->statistiques->compteursGlobaux(),
            'nb_clients'         => $this->utilisateurs->nombreClientsActifs(),
            'note_moyenne'       => $this->avis->noteMoyenne(),
        ]);
    }

    public function avis(Request $request): JsonResponse
    {
        $this->auth->requireStaff();

        return $this->json($this->avis->findByStatut($request->query('statut', StatutAvis::EnAttente->value)));
    }

    private function changerStatutCompte(int $id, bool $actif): JsonResponse
    {
        $this->auth->requireRole('administrateur');

        $user = $this->utilisateurs->findById($id) ?? throw HttpException::notFound('Utilisateur introuvable');
        if (!$actif && !$user->peutEtreDesactive()) {
            throw new HttpException('Impossible de désactiver un administrateur');
        }

        $this->utilisateurs->changerStatut($id, $actif);

        return $this->message($actif ? 'Compte réactivé' : 'Compte désactivé');
    }
}
