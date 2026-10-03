<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Données de référence du catalogue : thèmes, régimes, allergènes et
 * horaires d'ouverture (petites tables sans logique métier propre).
 */
final class ReferentielRepository extends Repository
{
    // ─── Thèmes ────────────────────────────────────────────────────────────────

    public function themes(): array
    {
        return $this->fetchAll('SELECT * FROM theme ORDER BY libelle');
    }

    public function creerTheme(string $libelle): int
    {
        return $this->insert('INSERT INTO theme (libelle) VALUES (?)', [$libelle]);
    }

    public function modifierTheme(int $id, string $libelle): void
    {
        $this->execute('UPDATE theme SET libelle = ? WHERE theme_id = ?', [$libelle, $id]);
    }

    public function supprimerTheme(int $id): void
    {
        $this->execute('DELETE FROM theme WHERE theme_id = ?', [$id]);
    }

    // ─── Régimes / allergènes (lecture seule) ──────────────────────────────────

    public function regimes(): array
    {
        return $this->fetchAll('SELECT * FROM regime ORDER BY libelle');
    }

    public function allergenes(): array
    {
        return $this->fetchAll('SELECT * FROM allergene ORDER BY libelle');
    }

    // ─── Horaires ──────────────────────────────────────────────────────────────

    public function horaires(): array
    {
        return $this->fetchAll('SELECT * FROM horaire ORDER BY horaire_id');
    }

    /** Un horaire à null garde sa valeur actuelle. */
    public function modifierHoraire(int $id, ?string $ouverture, ?string $fermeture): void
    {
        $this->execute(
            'UPDATE horaire SET heure_ouverture = COALESCE(?, heure_ouverture), heure_fermeture = COALESCE(?, heure_fermeture) WHERE horaire_id = ?',
            [$ouverture, $fermeture, $id]
        );
    }
}
