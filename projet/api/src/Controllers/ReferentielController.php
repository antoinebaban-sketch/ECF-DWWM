<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\ReferentielRepository;

/**
 * Listes de référence en lecture seule (filtres du catalogue, formulaires).
 * GET /regimes      → régimes alimentaires
 * GET /allergenes   → allergènes
 */
final class ReferentielController extends Controller
{
    private readonly ReferentielRepository $referentiel;

    public function __construct()
    {
        parent::__construct();
        $this->referentiel = new ReferentielRepository();
    }

    public function regimes(Request $request): JsonResponse
    {
        return $this->json($this->referentiel->regimes());
    }

    public function allergenes(Request $request): JsonResponse
    {
        return $this->json($this->referentiel->allergenes());
    }
}
