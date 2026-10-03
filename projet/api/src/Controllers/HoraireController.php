<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\ReferentielRepository;

/**
 * /api/horaires/*
 * GET /horaires        → horaires d'ouverture (pied de page)
 * PUT /horaires/{id}   → modifier un jour (employé/admin)
 */
final class HoraireController extends Controller
{
    private readonly ReferentielRepository $referentiel;

    public function __construct()
    {
        parent::__construct();
        $this->referentiel = new ReferentielRepository();
    }

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->referentiel->horaires());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();
        $this->referentiel->modifierHoraire($id, $request->input('heure_ouverture'), $request->input('heure_fermeture'));

        return $this->message('Horaire mis à jour');
    }
}
