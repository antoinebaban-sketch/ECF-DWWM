<?php
/**
 * /api/utilisateurs/*
 * GET    /utilisateurs/moi                  → profil du client connecté
 * PUT    /utilisateurs/moi                  → modifier son profil
 * DELETE /utilisateurs/moi                  → supprimer le compte (pseudonymisation RGPD Art.17)
 * GET    /utilisateurs/moi/export           → export des données personnelles (RGPD Art.20)
 * GET    /utilisateurs/moi/preferences      → régimes et allergènes enregistrés
 * PUT    /utilisateurs/moi/preferences      → mettre à jour les préférences alimentaires
 */
function handle(string $method, array $parts, array $body): void
{
    $seg = $parts[1] ?? null;
    $sub = $parts[2] ?? null;

    match(true) {
        $method === 'GET'    && $seg === 'moi' && $sub === 'export'      => exporterDonnees(),
        $method === 'GET'    && $seg === 'moi' && $sub === 'preferences' => getPreferences(),
        $method === 'PUT'    && $seg === 'moi' && $sub === 'preferences' => mettreAJourPreferences($body),
        $method === 'GET'    && $seg === 'moi'                           => getMonProfil(),
        $method === 'PUT'    && $seg === 'moi'                           => modifierMonProfil($body),
        $method === 'DELETE' && $seg === 'moi'                           => supprimerCompte(),
        default => jsonError('Route utilisateurs inconnue', 404)
    };
}

function getMonProfil(): void
{
    $user = authRequired();
    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT u.utilisateur_id, u.email, u.prenom, u.nom, u.telephone, u.ville, u.pays, u.adresse,
               r.libelle AS role, u.date_creation
        FROM utilisateur u
        JOIN role r ON r.role_id = u.role_id
        WHERE u.utilisateur_id = ?
    ');
    $stmt->execute([$user['id']]);
    $profil = $stmt->fetch();
    if (!$profil) jsonError('Utilisateur introuvable', 404);
    jsonOk($profil);
}

function modifierMonProfil(array $body): void
{
    $user = authRequired();
    $pdo  = getPDO();

    // Changement de mot de passe optionnel
    if (!empty($body['password'])) {
        validatePassword($body['password']);

        $pdo->prepare('UPDATE utilisateur SET password = ? WHERE utilisateur_id = ?')
            ->execute([hashPassword($body['password']), $user['id']]);
    }

    // Changement d'email : vérifier unicité
    if (!empty($body['email']) && $body['email'] !== $user['email']) {
        $exists = $pdo->prepare('SELECT 1 FROM utilisateur WHERE email = ? AND utilisateur_id != ?');
        $exists->execute([$body['email'], $user['id']]);
        if ($exists->fetch()) jsonError('Cette adresse email est déjà utilisée.', 409);
    }

    $pdo->prepare("
        UPDATE utilisateur SET
            prenom    = COALESCE(NULLIF(?, ''), prenom),
            nom       = COALESCE(NULLIF(?, ''), nom),
            email     = COALESCE(NULLIF(?, ''), email),
            telephone = COALESCE(NULLIF(?, ''), telephone),
            ville     = COALESCE(NULLIF(?, ''), ville),
            pays      = COALESCE(NULLIF(?, ''), pays),
            adresse   = COALESCE(NULLIF(?, ''), adresse)
        WHERE utilisateur_id = ?
    ")->execute([
        sanitize($body['prenom']    ?? ''),
        sanitize($body['nom']       ?? ''),
        sanitizeEmail($body['email'] ?? ''),
        sanitize($body['telephone'] ?? ''),
        sanitize($body['ville']     ?? ''),
        sanitize($body['pays']      ?? ''),
        sanitize($body['adresse']   ?? ''),
        $user['id'],
    ]);

    // Garder la session à jour avec les nouvelles infos affichées (navbar, etc.)
    if (!empty($body['prenom'])) $_SESSION['user']['prenom'] = sanitize($body['prenom']);
    if (!empty($body['nom']))    $_SESSION['user']['nom']    = sanitize($body['nom']);
    if (!empty($body['email']))  $_SESSION['user']['email']  = sanitizeEmail($body['email']);

    jsonOk(['message' => 'Profil mis à jour']);
}

// ─── Préférences alimentaires ─────────────────────────────────────────────────

function getPreferences(): void
{
    $user = authRequired();
    $pdo  = getPDO();

    $regimes = $pdo->prepare('
        SELECT r.regime_id, r.libelle
        FROM utilisateur_regime ur
        JOIN regime r ON r.regime_id = ur.regime_id
        WHERE ur.utilisateur_id = ?
        ORDER BY r.regime_id
    ');
    $regimes->execute([$user['id']]);

    $allergenes = $pdo->prepare('
        SELECT a.allergene_id, a.libelle
        FROM utilisateur_allergene ua
        JOIN allergene a ON a.allergene_id = ua.allergene_id
        WHERE ua.utilisateur_id = ?
        ORDER BY a.allergene_id
    ');
    $allergenes->execute([$user['id']]);

    jsonOk([
        'regimes'   => $regimes->fetchAll(),
        'allergenes' => $allergenes->fetchAll(),
    ]);
}

function mettreAJourPreferences(array $body): void
{
    $user      = authRequired();
    $pdo       = getPDO();
    $regimes   = array_map('intval', (array)($body['regimes']   ?? []));
    $allergenes = array_map('intval', (array)($body['allergenes'] ?? []));

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM utilisateur_regime    WHERE utilisateur_id = ?')->execute([$user['id']]);
        $pdo->prepare('DELETE FROM utilisateur_allergene WHERE utilisateur_id = ?')->execute([$user['id']]);

        if ($regimes) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO utilisateur_regime (utilisateur_id, regime_id) VALUES (?, ?)');
            foreach ($regimes as $rid) {
                if ($rid > 0) $stmt->execute([$user['id'], $rid]);
            }
        }
        if ($allergenes) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO utilisateur_allergene (utilisateur_id, allergene_id) VALUES (?, ?)');
            foreach ($allergenes as $aid) {
                if ($aid > 0) $stmt->execute([$user['id'], $aid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    jsonOk(['message' => 'Préférences enregistrées']);
}

// ─── Suppression de compte — pseudonymisation (RGPD Art. 17) ─────────────────

function supprimerCompte(): void
{
    $user = authRequired();
    $pdo  = getPDO();

    // Vérifier qu'on ne supprime pas un admin/employé via cette route
    $stmt = $pdo->prepare('SELECT r.libelle FROM utilisateur u JOIN role r ON r.role_id = u.role_id WHERE u.utilisateur_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if ($row && in_array($row['libelle'], STAFF_ROLES, true)) {
        jsonError('Les comptes employés et administrateurs ne peuvent pas être supprimés via cette route.', 403);
    }

    // Pseudonymisation : on efface les données personnelles mais on conserve
    // l'historique des commandes (obligation comptable — Art. L.123-22 C.com.)
    $pseudo = 'Compte supprimé';
    $fakeEmail = 'supprime_' . $user['id'] . '_' . time() . '@anonyme.local';

    $pdo->prepare('
        UPDATE utilisateur SET
            email      = ?,
            password   = \'\',
            prenom     = ?,
            nom        = ?,
            telephone  = \'\',
            adresse    = \'\',
            ville      = \'\',
            statut_compte = 0,
            reset_token = NULL,
            reset_token_expires = NULL
        WHERE utilisateur_id = ?
    ')->execute([$fakeEmail, $pseudo, $pseudo, $user['id']]);

    $_SESSION = [];
    session_destroy();

    jsonOk(['message' => 'Votre compte a été supprimé. Vos données personnelles ont été effacées.']);
}

// ─── Export des données personnelles (RGPD Art. 20 — portabilité) ────────────

function exporterDonnees(): void
{
    $user = authRequired();
    $pdo  = getPDO();

    $profil = $pdo->prepare('
        SELECT u.utilisateur_id, u.email, u.prenom, u.nom, u.telephone, u.ville, u.pays, u.adresse, u.date_creation, r.libelle AS role
        FROM utilisateur u JOIN role r ON r.role_id = u.role_id
        WHERE u.utilisateur_id = ?
    ');
    $profil->execute([$user['id']]);

    $commandes = $pdo->prepare('
        SELECT c.commande_id, c.date_commande, c.date_prestation, c.nombre_personne,
               c.prix_commande, c.statut_commande, c.adresse_livraison, m.titre AS menu
        FROM commande c LEFT JOIN menu m ON m.menu_id = c.menu_id
        WHERE c.client_id = ? ORDER BY c.date_commande DESC
    ');
    $commandes->execute([$user['id']]);

    $avis = $pdo->prepare('SELECT avis_id, note, description, statut_validation, date_avis FROM avis WHERE client_id = ?');
    $avis->execute([$user['id']]);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="mes-donnees-viteetgourmand.json"');
    echo json_encode([
        'export_date' => date('c'),
        'profil'      => $profil->fetch(),
        'commandes'   => $commandes->fetchAll(),
        'avis'        => $avis->fetchAll(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
