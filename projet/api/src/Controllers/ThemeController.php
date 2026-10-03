<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\ReferentielRepository;

/**
 * /api/themes/*
 * GET    /themes         → liste (public)
 * POST   /themes         → créer (employé/admin)
 * PUT    /themes/{id}    → renommer (employé/admin)
 * DELETE /themes/{id}    → supprimer (administrateur uniquement)
 */
final class ThemeController extends Controller
{
    private readonly ReferentielRepository $referentiel;

    public function __construct()
    {
        parent::__construct();
        $this->referentiel = new ReferentielRepository();
    }

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->referentiel->themes());
    }

    public function store(Request $request): JsonResponse
    {
        $this->auth->requireStaff();
        $request->requireFields('libelle');

        $libelle = trim($request->input('libelle'));
        $id      = $this->referentiel->creerTheme($libelle);

        return $this->json(['theme_id' => $id, 'libelle' => $libelle], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();
        $request->requireFields('libelle');

        $this->referentiel->modifierTheme($id, trim($request->input('libelle')));

        return $this->message('Thème mis à jour');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->auth->requireRole('administrateur');
        $this->referentiel->supprimerTheme($id);

        return $this->message('Thème supprimé');
    }
}
