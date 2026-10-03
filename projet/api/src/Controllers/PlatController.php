<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\PlatRepository;
use App\Support\Sanitizer;

/**
 * /api/plats/*
 * GET    /plats          → liste (filtre ?type=Entrée|Plat|Dessert|Boisson)
 * GET    /plats/{id}     → détail avec allergènes
 * POST   /plats          → créer (employé/admin)
 * PUT    /plats/{id}     → modifier (employé/admin)
 * DELETE /plats/{id}     → supprimer (employé/admin)
 */
final class PlatController extends Controller
{
    /** Régime associé par défaut aux allergènes d'un nouveau plat. */
    private const REGIME_PAR_DEFAUT = 1;

    private readonly PlatRepository $plats;

    public function __construct()
    {
        parent::__construct();
        $this->plats = new PlatRepository();
    }

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->plats->findAll($request->query('type') ?: null));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->json($this->plats->findDetail($id) ?? throw HttpException::notFound('Plat introuvable'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->auth->requireStaff();
        $request->requireFields('titre_plat', 'type_plat', 'prix_unitaire');

        $id = $this->plats->creer([
            'titre_plat'    => Sanitizer::text($request->input('titre_plat')),
            'type_plat'     => $request->input('type_plat'),
            'description'   => Sanitizer::text($request->input('description', '')),
            'image_url'     => $request->input('image_url'),
            'prix_unitaire' => (float) $request->input('prix_unitaire'),
        ]);

        $allergenes = $request->input('allergenes');
        if (is_array($allergenes) && $allergenes) {
            $regimeId = $request->input('regime_id') ? (int) $request->input('regime_id') : self::REGIME_PAR_DEFAUT;
            $this->plats->associerAllergenes($id, $allergenes, $regimeId);
        }

        return $this->json(['plat_id' => $id], 201);
    }

    /** Mise à jour partielle : seuls les champs envoyés sont modifiés. */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();

        $prix = $request->input('prix_unitaire');
        $this->plats->modifier($id, [
            'titre_plat'    => Sanitizer::textOrNull($request->input('titre_plat')),
            'type_plat'     => $request->input('type_plat'),
            'description'   => Sanitizer::textOrNull($request->input('description')),
            'prix_unitaire' => $prix === null ? null : (float) $prix,
        ]);

        return $this->message('Plat modifié');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();
        $this->plats->supprimer($id);

        return $this->message('Plat supprimé');
    }
}
