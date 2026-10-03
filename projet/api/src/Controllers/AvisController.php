<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enum\StatutAvis;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Models\Avis;
use App\Repositories\AvisRepository;
use App\Support\Sanitizer;

/**
 * /api/avis/*
 * GET  /avis                      → avis validés (public)
 * GET  /avis/mes-avis             → avis du client connecté
 * GET  /avis/{id}                 → détail (employé/admin)
 * POST /avis                      → déposer un avis (client connecté)
 * PUT  /avis/{id}/validation      → valider / refuser (employé/admin)
 */
final class AvisController extends Controller
{
    private const LIMITE_MAX = 50;

    private readonly AvisRepository $avis;

    public function __construct()
    {
        parent::__construct();
        $this->avis = new AvisRepository();
    }

    public function index(Request $request): JsonResponse
    {
        $noteMin = (int) $request->query('note_min', 0);
        $limite  = min((int) $request->query('limit', 10), self::LIMITE_MAX);

        return $this->json($this->avis->publies($noteMin ?: null, $limite));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();

        return $this->json($this->avis->findDetail($id) ?? throw HttpException::notFound('Avis introuvable'));
    }

    public function mesAvis(Request $request): JsonResponse
    {
        $user = $this->auth->requireUser();

        return $this->json($this->avis->findByClient((int) $user['id']));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $request->requireFields('note');

        $commandeId = $request->input('commande_id') ? (int) $request->input('commande_id') : null;

        // La note est validée par le modèle (1 à 5) avant toute requête
        $avis = new Avis(
            clientId: (int) $user['id'],
            commandeId: $commandeId,
            note: (int) $request->input('note'),
            description: Sanitizer::text($request->input('description', '')),
        );

        if ($commandeId !== null) {
            if (!$this->avis->commandeTermineeDuClient($commandeId, $avis->clientId)) {
                throw new HttpException('Commande introuvable ou non terminée');
            }
            if ($this->avis->existePourCommande($commandeId, $avis->clientId)) {
                throw HttpException::conflict('Vous avez déjà déposé un avis pour cette commande');
            }
        }

        $this->avis->enregistrer($avis);

        return $this->message('Avis enregistré. Il sera publié après validation.', 201);
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $this->auth->requireStaff();
        $request->requireFields('statut');

        $statut = StatutAvis::decision((string) $request->input('statut'))
            ?? throw new HttpException('Statut invalide : "validé" ou "refusé" attendu');

        $this->avis->changerStatut($id, $statut);

        return $this->message('Avis ' . $statut->value);
    }
}
