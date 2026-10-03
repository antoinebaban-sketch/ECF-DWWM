<?php
/**
 * Table de routage de l'API — un endpoint = une méthode de contrôleur.
 * {id} n'accepte que des entiers (voir App\Http\Router).
 */

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\AvisController;
use App\Controllers\CommandeController;
use App\Controllers\ContactController;
use App\Controllers\HoraireController;
use App\Controllers\MenuController;
use App\Controllers\PlatController;
use App\Controllers\ReferentielController;
use App\Controllers\ThemeController;
use App\Controllers\UtilisateurController;
use App\Http\Router;

return (new Router())
    // ─── Authentification ───────────────────────────────────────────────────
    ->resource('auth', 'Route auth inconnue')
    ->post('auth/login',           [AuthController::class, 'login'])
    ->post('auth/register',        [AuthController::class, 'register'])
    ->post('auth/logout',          [AuthController::class, 'logout'])
    ->get('auth/me',               [AuthController::class, 'me'])
    ->post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->post('auth/reset-password',  [AuthController::class, 'resetPassword'])

    // ─── Catalogue ──────────────────────────────────────────────────────────
    ->resource('menus', 'Route menus inconnue')
    ->get('menus',            [MenuController::class, 'index'])
    ->get('menus/populaires', [MenuController::class, 'populaires'])
    ->get('menus/{id}',       [MenuController::class, 'show'])
    ->post('menus',           [MenuController::class, 'store'])
    ->put('menus/{id}',       [MenuController::class, 'update'])
    ->delete('menus/{id}',    [MenuController::class, 'destroy'])

    ->resource('plats', 'Route plats inconnue')
    ->get('plats',         [PlatController::class, 'index'])
    ->get('plats/{id}',    [PlatController::class, 'show'])
    ->post('plats',        [PlatController::class, 'store'])
    ->put('plats/{id}',    [PlatController::class, 'update'])
    ->delete('plats/{id}', [PlatController::class, 'destroy'])

    ->resource('themes', 'Route themes inconnue')
    ->get('themes',         [ThemeController::class, 'index'])
    ->post('themes',        [ThemeController::class, 'store'])
    ->put('themes/{id}',    [ThemeController::class, 'update'])
    ->delete('themes/{id}', [ThemeController::class, 'destroy'])

    ->resource('regimes', 'Route régimes inconnue')
    ->get('regimes', [ReferentielController::class, 'regimes'])

    ->resource('allergenes', 'Route allergènes inconnue')
    ->get('allergenes', [ReferentielController::class, 'allergenes'])

    ->resource('horaires', 'Route horaires inconnue')
    ->get('horaires',      [HoraireController::class, 'index'])
    ->put('horaires/{id}', [HoraireController::class, 'update'])

    // ─── Commandes ──────────────────────────────────────────────────────────
    ->resource('commandes', 'Route commandes inconnue')
    ->get('commandes/mes-commandes',   [CommandeController::class, 'mesCommandes'])
    ->get('commandes',                 [CommandeController::class, 'index'])
    ->get('commandes/{id}',            [CommandeController::class, 'show'])
    ->get('commandes/{id}/historique', [CommandeController::class, 'historique'])
    ->post('commandes',                [CommandeController::class, 'store'])
    ->put('commandes/{id}/statut',     [CommandeController::class, 'changerStatut'])
    ->put('commandes/{id}/annuler',    [CommandeController::class, 'annuler'])
    ->put('commandes/{id}',            [CommandeController::class, 'update'])

    ->resource('factures', 'Route commandes inconnue')
    ->post('factures', [CommandeController::class, 'demanderFacture'])

    // ─── Avis ───────────────────────────────────────────────────────────────
    ->resource('avis', 'Route avis inconnue')
    ->get('avis/mes-avis',          [AvisController::class, 'mesAvis'])
    ->get('avis',                   [AvisController::class, 'index'])
    ->get('avis/{id}',              [AvisController::class, 'show'])
    ->post('avis',                  [AvisController::class, 'store'])
    ->put('avis/{id}/validation',   [AvisController::class, 'valider'])

    // ─── Espace client ──────────────────────────────────────────────────────
    ->resource('utilisateurs', 'Route utilisateurs inconnue')
    ->get('utilisateurs/moi',             [UtilisateurController::class, 'profil'])
    ->put('utilisateurs/moi',             [UtilisateurController::class, 'modifierProfil'])
    ->delete('utilisateurs/moi',          [UtilisateurController::class, 'supprimerCompte'])
    ->get('utilisateurs/moi/export',      [UtilisateurController::class, 'exporter'])
    ->get('utilisateurs/moi/preferences', [UtilisateurController::class, 'preferences'])
    ->put('utilisateurs/moi/preferences', [UtilisateurController::class, 'modifierPreferences'])

    // ─── Espace administrateur ──────────────────────────────────────────────
    ->resource('admin', 'Route admin inconnue')
    ->get('admin/utilisateurs',               [AdminController::class, 'utilisateurs'])
    ->post('admin/employes',                  [AdminController::class, 'creerEmploye'])
    ->put('admin/employes/{id}/desactiver',   [AdminController::class, 'desactiver'])
    ->put('admin/employes/{id}/activer',      [AdminController::class, 'activer'])
    ->get('admin/stats',                      [AdminController::class, 'stats'])
    ->get('admin/avis',                       [AdminController::class, 'avis'])

    // ─── Formulaires publics ────────────────────────────────────────────────
    ->resource('contact', 'Méthode non autorisée', 405)
    ->post('contact', [ContactController::class, 'contact'])
    ->resource('devis', 'Méthode non autorisée', 405)
    ->post('devis', [ContactController::class, 'devis']);
