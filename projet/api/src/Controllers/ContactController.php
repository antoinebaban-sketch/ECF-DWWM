<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Mail\Notifier;
use App\Support\Sanitizer;

/**
 * Formulaires publics (aucune écriture en base, uniquement des emails).
 * POST /contact   → formulaire de contact
 * POST /devis     → demande de devis de location de matériel
 */
final class ContactController extends Controller
{
    private readonly Notifier $notifier;

    public function __construct()
    {
        parent::__construct();
        $this->notifier = new Notifier();
    }

    public function contact(Request $request): JsonResponse
    {
        $request->requireFields('titre', 'email');
        $this->exigerEmailValide($request);

        $this->notifier->contact(
            $request->input('email'),
            Sanitizer::text($request->input('titre', '')),
            Sanitizer::text($request->input('description', ''))
        );

        return $this->message('Message envoyé. Vous allez recevoir un accusé de réception.');
    }

    public function devis(Request $request): JsonResponse
    {
        $request->requireFields('nom', 'email', 'date_evenement', 'nb_personnes');
        $this->exigerEmailValide($request);

        // Matériel souhaité : sélection structurée (catalogue) ou texte libre
        $lignes    = [];
        $selection = $request->input('selection');
        if (is_array($selection) && $selection) {
            foreach ($selection as $item) {
                $lignes[] = ($item['titre'] ?? '') . ' × ' . (int) ($item['quantite'] ?? 0);
            }
        } elseif ($request->input('materiel')) {
            $lignes[] = Sanitizer::text($request->input('materiel'));
        }

        $this->notifier->devis([
            'nom'            => Sanitizer::text($request->input('nom', '')),
            'email'          => $request->input('email'),
            'telephone'      => $request->input('telephone'),
            'date_evenement' => $request->input('date_evenement'),
            'nb_personnes'   => $request->input('nb_personnes'),
            'message'        => Sanitizer::text($request->input('message', '')),
        ], $lignes);

        return $this->message('Demande de devis envoyée. Vous serez contacté sous 48h.');
    }

    private function exigerEmailValide(Request $request): void
    {
        if (!Sanitizer::isEmail($request->input('email'))) {
            throw new HttpException('Email invalide');
        }
    }
}
