<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\MenuRepository;
use App\Support\Sanitizer;

/**
 * /api/menus/*
 * GET    /menus                   → liste avec filtres
 * GET    /menus/populaires        → 3 menus les + commandés
 * GET    /menus/{id}              → détail complet
 * POST   /menus                   → créer (employé/admin)
 * PUT    /menus/{id}              → modifier (employé/admin)
 * DELETE /menus/{id}              → supprimer (employé/admin)
 */
final class MenuController extends Controller
{
    private const DELAI_PREVENANCE_DEFAUT = 7;

    private readonly MenuRepository $menus;

    public function __construct()
    {
        parent::__construct();
        $this->menus = new MenuRepository();
    }

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->menus->findAll($request->query));
    }

    public function populaires(Request $request): JsonResponse
    {
        return $this->json($this->menus->populaires());
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->json($this->menus->findDetail($id) ?? throw HttpException::notFound('Menu introuvable'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->auth->requireStaff();
        $request->requireFields('titre', 'description', 'prix_par_personne', 'nombre_personne_mini', 'quantite_restante', 'delai_prevenance');

        $id = $this->menus->creer([
            'titre'                => Sanitizer::text($request->input('titre')),
            'description'          => Sanitizer::text($request->input('description')),
            'prix_par_personne'    => (float) $request->input('prix_par_personne'),
            'nombre_personne_mini' => (int) $request->input('nombre_personne_mini'),
            'quantite_restante'    => (int) $request->input('quantite_restante'),
            'theme_id'             => $request->input('theme_id') ? (int) $request->input('theme_id') : null,
            'delai_prevenance'     => (int) $request->input('delai_prevenance', self::DELAI_PREVENANCE_DEFAUT),
            'conditions'           => $request->input('conditions') ? Sanitizer::text($request->input('conditions')) : null,
        ]);

        $this->menus->ajouterImages($id, $this->liste($request, 'images'));
        $this->menus->ajouterPlats($id, $this->liste($request, 'plats'));
        $this->menus->ajouterRegimes($id, $this->liste($request, 'regimes'));

        return $this->json(['menu_id' => $id, 'message' => 'Menu créé'], 201);
    }

    /** Mise à jour partielle : seuls les champs envoyés sont modifiés. */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();

        $int   = static fn (mixed $v) => $v === null ? null : (int) $v;
        $float = static fn (mixed $v) => $v === null ? null : (float) $v;

        $this->menus->modifier($id, [
            'titre'                => Sanitizer::textOrNull($request->input('titre')),
            'description'          => Sanitizer::textOrNull($request->input('description')),
            'prix_par_personne'    => $float($request->input('prix_par_personne')),
            'nombre_personne_mini' => $int($request->input('nombre_personne_mini')),
            'quantite_restante'    => $int($request->input('quantite_restante')),
            'theme_id'             => $int($request->input('theme_id')),
            'delai_prevenance'     => $int($request->input('delai_prevenance')),
            'conditions'           => Sanitizer::textOrNull($request->input('conditions')),
        ]);

        // Les images ne sont remplacées que si une nouvelle liste est fournie
        if (is_array($request->input('images'))) {
            $this->menus->remplacerImages($id, $request->input('images'));
        }

        return $this->message('Menu mis à jour');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();
        $this->menus->supprimer($id);

        return $this->message('Menu supprimé');
    }

    /** Champ tableau optionnel du corps JSON (liste vide si absent ou invalide). */
    private function liste(Request $request, string $champ): array
    {
        $valeur = $request->input($champ);

        return is_array($valeur) ? $valeur : [];
    }
}
