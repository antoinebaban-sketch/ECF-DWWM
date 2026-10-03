<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\StatutAvis;
use App\Http\HttpException;

/**
 * Entité Avis : un client note (1 à 5) une prestation terminée. Tout nouvel
 * avis démarre "en_attente" et n'est publié qu'après modération.
 */
final class Avis
{
    public const NOTE_MIN = 1;
    public const NOTE_MAX = 5;

    public function __construct(
        public readonly int $clientId,
        public readonly ?int $commandeId,
        public readonly int $note,
        public readonly string $description,
        public readonly StatutAvis $statut = StatutAvis::EnAttente,
    ) {
        if ($note < self::NOTE_MIN || $note > self::NOTE_MAX) {
            throw new HttpException('La note doit être entre ' . self::NOTE_MIN . ' et ' . self::NOTE_MAX);
        }
    }
}
