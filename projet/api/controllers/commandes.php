<?php
/**
 * /api/commandes/*
 * GET  /commandes/mes-commandes           → commandes du client connecté
 * GET  /commandes                         → toutes commandes (employé/admin)
 * GET  /commandes/{id}                    → détail
 * GET  /commandes/{id}/historique         → timeline statuts
 * POST /commandes                         → passer une commande
 * PUT  /commandes/{id}/statut             → changer statut (employé/admin)
 * PUT  /commandes/{id}/annuler            → annuler (client, si pas encore acceptée)
 * POST /factures                          → demande de facture
 */

const STATUTS = ['en_attente','acceptée','en_preparation','en_cours_livraison','livrée','retour_materiel','terminée','annulée'];
const STATUT_LABELS = [
    'en_attente'        => 'En attente',
    'acceptée'          => 'Acceptée',
    'en_preparation'    => 'En préparation',
    'en_cours_livraison'=> 'En cours de livraison',
    'livrée'            => 'Livrée',
    'retour_materiel'   => 'En attente retour matériel',
    'terminée'          => 'Terminée',
    'annulée'           => 'Annulée',
];

function handle(string $method, array $parts, array $body): void
{
    $seg = $parts[1] ?? null;
    $id  = (is_numeric($seg)) ? (int)$seg : null;
    $sub = $id ? ($parts[2] ?? null) : null;

    // /factures est routé ici
    if ($parts[0] === 'factures' && $method === 'POST') { demanderFacture($body); return; }

    match(true) {
        $method === 'GET'  && $seg === 'mes-commandes'       => mesCommandes(),
        $method === 'GET'  && $id === null                   => toutesCommandes(),
        $method === 'GET'  && $id !== null && !$sub          => getCommande($id),
        $method === 'GET'  && $sub === 'historique'          => getHistorique($id),
        $method === 'POST' && $id === null                   => creerCommande($body),
        $method === 'PUT'  && $sub === 'statut'              => changerStatut($id, $body),
        $method === 'PUT'  && $sub === 'annuler'             => annulerCommande($id, $body),
        $method === 'PUT'  && $id !== null && !$sub          => modifierCommande($id, $body),
        default => jsonError('Route commandes inconnue', 404)
    };
}

// ─── Mes commandes ────────────────────────────────────────────────────────────

function mesCommandes(): void
{
    $user = authRequired();
    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT c.commande_id, c.nombre_personne, c.date_commande, c.date_prestation,
               c.prix_commande, c.statut_commande, c.adresse_livraison,
               c.motif_annulation, c.mode_contact,
               m.titre AS menu_titre, m.prix_par_personne,
               t.libelle AS theme
        FROM commande c
        LEFT JOIN menu m ON m.menu_id = c.menu_id
        LEFT JOIN theme t ON t.theme_id = c.theme_id
        WHERE c.client_id = ?
        ORDER BY c.date_commande DESC
    ');
    $stmt->execute([$user['id']]);
    $commandes = $stmt->fetchAll();

    foreach ($commandes as &$cmd) {
        $cmd['statut_label'] = STATUT_LABELS[$cmd['statut_commande']] ?? $cmd['statut_commande'];
    }
    jsonOk($commandes);
}

// ─── Toutes les commandes (employé/admin) ─────────────────────────────────────

function toutesCommandes(): void
{
    roleRequired('employe', 'administrateur');
    $pdo  = getPDO();

    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['statut'])) {
        $where[]  = 'c.statut_commande = ?';
        $params[] = $_GET['statut'];
    }
    if (!empty($_GET['client_id'])) {
        $where[]  = 'c.client_id = ?';
        $params[] = (int)$_GET['client_id'];
    }
    if (!empty($_GET['q'])) {
        $where[]  = '(u.prenom LIKE ? OR u.nom LIKE ? OR u.email LIKE ?)';
        $q = '%' . $_GET['q'] . '%';
        $params[] = $q; $params[] = $q; $params[] = $q;
    }

    $sql = 'SELECT c.commande_id, c.nombre_personne, c.date_commande, c.date_prestation,
                   c.prix_commande, c.statut_commande, c.adresse_livraison, c.motif_annulation,
                   m.titre AS menu_titre,
                   u.prenom, u.nom, u.email, u.telephone,
                   h.date_retour_materiel
            FROM commande c
            LEFT JOIN menu m ON m.menu_id = c.menu_id
            LEFT JOIN utilisateur u ON u.utilisateur_id = c.client_id
            LEFT JOIN (
                SELECT commande_id, MAX(date_changement_statut) AS date_retour_materiel
                FROM historique_commande
                WHERE statut = \'retour_materiel\'
                GROUP BY commande_id
            ) h ON h.commande_id = c.commande_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY c.date_commande DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll();

    foreach ($commandes as &$cmd) {
        $cmd['statut_label'] = STATUT_LABELS[$cmd['statut_commande']] ?? $cmd['statut_commande'];

        // Alerte : matériel non rendu 10 jours après le passage en statut "retour_materiel"
        $cmd['retour_materiel_en_retard'] = false;
        if ($cmd['statut_commande'] === 'retour_materiel' && $cmd['date_retour_materiel']) {
            $jours = (int)floor((time() - strtotime($cmd['date_retour_materiel'])) / 86400);
            $cmd['jours_depuis_retour']      = $jours;
            $cmd['retour_materiel_en_retard'] = $jours > 10;
        }
    }
    jsonOk($commandes);
}

// ─── Détail d'une commande ────────────────────────────────────────────────────

function getCommande(int $id): void
{
    $user = authRequired();
    $pdo  = getPDO();

    $stmt = $pdo->prepare('
        SELECT c.*, m.titre AS menu_titre, m.prix_par_personne,
               u.prenom, u.nom, u.email, u.telephone,
               t.libelle AS theme
        FROM commande c
        LEFT JOIN menu m ON m.menu_id = c.menu_id
        LEFT JOIN utilisateur u ON u.utilisateur_id = c.client_id
        LEFT JOIN theme t ON t.theme_id = c.theme_id
        WHERE c.commande_id = ?
    ');
    $stmt->execute([$id]);
    $cmd = $stmt->fetch();
    if (!$cmd) jsonError('Commande introuvable', 404);

    // Un client ne voit que ses propres commandes
    if ($user['role'] === 'client' && $cmd['client_id'] != $user['id']) {
        jsonError('Accès interdit', 403);
    }

    $cmd['statut_label'] = STATUT_LABELS[$cmd['statut_commande']] ?? $cmd['statut_commande'];
    jsonOk($cmd);
}

// ─── Historique des statuts ───────────────────────────────────────────────────

function getHistorique(int $id): void
{
    $user = authRequired();
    $pdo  = getPDO();

    if ($user['role'] === 'client') {
        $own = $pdo->prepare('SELECT commande_id FROM commande WHERE commande_id = ? AND client_id = ?');
        $own->execute([$id, $user['id']]);
        if (!$own->fetch()) jsonError('Accès interdit', 403);
    }

    $stmt = $pdo->prepare('SELECT statut, date_changement_statut FROM historique_commande WHERE commande_id = ? ORDER BY date_changement_statut ASC');
    $stmt->execute([$id]);
    $hist = $stmt->fetchAll();
    foreach ($hist as &$h) {
        $h['statut_label'] = STATUT_LABELS[$h['statut']] ?? $h['statut'];
    }
    jsonOk($hist);
}

// ─── Passer une commande ──────────────────────────────────────────────────────

function creerCommande(array $body): void
{
    $user = authRequired();
    require_fields($body, 'menu_id', 'nombre_personne', 'date_prestation', 'adresse_livraison');

    $pdo = getPDO();
    $pdo->beginTransaction();

    // Vérifier stock de façon atomique (FOR UPDATE verrouille la ligne)
    $menuStmt = $pdo->prepare('SELECT * FROM menu WHERE menu_id = ? FOR UPDATE');
    $menuStmt->execute([(int)$body['menu_id']]);
    $menu = $menuStmt->fetch();
    if (!$menu) { $pdo->rollBack(); jsonError('Menu introuvable', 404); }
    if ($menu['quantite_restante'] <= 0) { $pdo->rollBack(); jsonError('Ce menu n\'est plus disponible'); }

    $nbPersonnes = (int)$body['nombre_personne'];
    if ($nbPersonnes < $menu['nombre_personne_mini']) {
        jsonError('Ce menu nécessite au minimum ' . $menu['nombre_personne_mini'] . ' personnes');
    }

    // Calcul du prix avec réduction éventuelle
    $prix = (float)$menu['prix_par_personne'];
    $surplus = $nbPersonnes - $menu['nombre_personne_mini'];
    if ($surplus >= 5) $prix = $prix * 0.9; // 10% de réduction

    // Calcul des frais de livraison
    $distKm       = (float)($body['distance_km'] ?? 0);
    $fraisLivraison = $distKm > 0 ? round(5 + 0.59 * $distKm, 2) : 0;

    $prixTotal = round($prix * $nbPersonnes + $fraisLivraison, 2);

    // Insérer la commande
    $ins = $pdo->prepare('
        INSERT INTO commande
            (client_id, menu_id, nombre_personne, date_commande, date_prestation,
             prix_commande, statut_commande, adresse_livraison, theme_id)
        VALUES (?, ?, ?, CURDATE(), ?, ?, \'en_attente\', ?, ?)
    ');
    $ins->execute([
        $user['id'],
        (int)$body['menu_id'],
        $nbPersonnes,
        $body['date_prestation'],
        $prixTotal,
        sanitize($body['adresse_livraison']),
        !empty($body['theme_id']) ? (int)$body['theme_id'] : null,
    ]);
    $commandeId = (int)$pdo->lastInsertId();

    // Historique
    $pdo->prepare('INSERT INTO historique_commande (commande_id, statut) VALUES (?, \'en_attente\')')
        ->execute([$commandeId]);

    // Livraison si distance fournie
    if ($distKm > 0) {
        $pdo->prepare('INSERT INTO livraison (commande_id, date_livraison, distance_km, frais_livraison) VALUES (?, ?, ?, ?)')
            ->execute([$commandeId, $body['date_prestation'], $distKm, $fraisLivraison]);
    }

    // Décrémenter le stock
    $pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id = ?')
        ->execute([(int)$body['menu_id']]);

    $pdo->commit();

    // Mail de confirmation
    $clientStmt = $pdo->prepare('SELECT prenom, nom, email FROM utilisateur WHERE utilisateur_id = ?');
    $clientStmt->execute([$user['id']]);
    $client = $clientStmt->fetch();

    $html = mailTemplate(
        'Confirmation de commande n°' . $commandeId,
        '<p>Bonjour <strong>' . htmlspecialchars($client['prenom']) . '</strong>,</p>
         <p>Votre commande a bien été enregistrée.</p>
         <table style="width:100%;border-collapse:collapse;margin:16px 0">
           <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Menu</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . htmlspecialchars($menu['titre']) . '</td></tr>
           <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Personnes</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . $nbPersonnes . '</td></tr>
           <tr><td style="padding:8px;border-bottom:1px solid #ede3d0"><strong>Date</strong></td><td style="padding:8px;border-bottom:1px solid #ede3d0">' . htmlspecialchars($body['date_prestation']) . '</td></tr>
           <tr><td style="padding:8px"><strong>Total</strong></td><td style="padding:8px"><strong>' . number_format($prixTotal, 2, ',', ' ') . ' €</strong></td></tr>
         </table>
         <p>Un de nos employés prendra contact avec vous pour confirmer les détails.</p>'
    );
    sendMail($client['email'], 'Confirmation commande n°' . $commandeId . ' — Vite & Gourmand', $html);

    // Un document Mongo par commande : sert au graphique "commandes par menu" côté admin
    mongoInsert('commandes', [
        'commande_id' => $commandeId,
        'menu_id'     => (int)$body['menu_id'],
        'menu_titre'  => $menu['titre'],
        'montant'     => $prixTotal,
        'date'        => date('Y-m-d'),
    ]);

    jsonOk([
        'commande_id'    => $commandeId,
        'prix_total'     => $prixTotal,
        'frais_livraison'=> $fraisLivraison,
        'message'        => 'Commande enregistrée. Vous allez recevoir un email de confirmation.',
    ], 201);
}

// ─── Changer le statut (employé/admin) ────────────────────────────────────────

function changerStatut(int $id, array $body): void
{
    $user = roleRequired('employe', 'administrateur');
    require_fields($body, 'statut');

    if (!in_array($body['statut'], STATUTS, true)) {
        jsonError('Statut invalide. Valeurs acceptées : ' . implode(', ', STATUTS));
    }

    // L'employé doit avoir contacté le client avant d'annuler ou de modifier une commande
    if ($body['statut'] === 'annulée') {
        require_fields($body, 'motif', 'mode_contact');
    }

    $pdo  = getPDO();
    $cmd = fetchOrFail($pdo, 'SELECT * FROM commande WHERE commande_id = ?', [$id], 'Commande introuvable');

    $motif       = isset($body['motif']) ? sanitize($body['motif']) : null;
    $modeContact = isset($body['mode_contact']) ? sanitize($body['mode_contact']) : null;
    $pdo->prepare('
        UPDATE commande SET
            statut_commande   = ?,
            employe_id        = ?,
            motif_annulation  = COALESCE(?, motif_annulation),
            mode_contact      = COALESCE(?, mode_contact)
        WHERE commande_id = ?
    ')->execute([$body['statut'], $user['id'], $motif, $modeContact, $id]);

    $pdo->prepare('INSERT INTO historique_commande (commande_id, statut) VALUES (?, ?)')
        ->execute([$id, $body['statut']]);

    // Mail "en attente retour matériel"
    if ($body['statut'] === 'retour_materiel') {
        $clientStmt = $pdo->prepare('SELECT email, prenom FROM utilisateur WHERE utilisateur_id = ?');
        $clientStmt->execute([$cmd['client_id']]);
        $client = $clientStmt->fetch();
        if ($client) {
            $html = mailTemplate(
                'Retour du matériel — Commande n°' . $id,
                '<p>Bonjour <strong>' . htmlspecialchars($client['prenom']) . '</strong>,</p>
                 <p>Votre prestation est terminée. Merci de retourner le matériel loué <strong>dans les 10 jours ouvrés</strong>.</p>
                 <p>Passé ce délai, une pénalité de <strong>600 €</strong> sera appliquée conformément à nos conditions générales.</p>
                 <p>Pour organiser le retour : <a href="mailto:contact@viteetgourmand.fr">contact@viteetgourmand.fr</a></p>'
            );
            sendMail($client['email'], 'Retour matériel requis — Vite & Gourmand', $html);
        }
    }

    // Mail "terminée" → invitation à déposer un avis
    if ($body['statut'] === 'terminée') {
        $clientStmt = $pdo->prepare('SELECT email, prenom FROM utilisateur WHERE utilisateur_id = ?');
        $clientStmt->execute([$cmd['client_id']]);
        $client = $clientStmt->fetch();
        if ($client) {
            $lien = APP_URL . '/MonCompte.html#avis-' . $id;
            $html = mailTemplate(
                'Donnez votre avis sur votre prestation',
                '<p>Bonjour <strong>' . htmlspecialchars($client['prenom']) . '</strong>,</p>
                 <p>Votre commande n°' . $id . ' est terminée. Nous espérons que vous avez passé un excellent moment !</p>
                 <p>Votre avis nous aide à nous améliorer. Laissez un commentaire en quelques clics :</p>
                 <p><a href="' . $lien . '" style="background:#5C1A1A;color:#C49A2D;padding:10px 20px;text-decoration:none;border-radius:4px">Donner mon avis</a></p>'
            );
            sendMail($client['email'], 'Votre avis compte — Vite & Gourmand', $html);
        }
    }

    jsonOk(['message' => 'Statut mis à jour']);
}

// ─── Annuler une commande (client) ────────────────────────────────────────────
// Libre tant que l'employé n'a pas encore accepté la commande.

function annulerCommande(int $id, array $body): void
{
    $user = authRequired();

    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM commande WHERE commande_id = ? AND client_id = ?');
    $stmt->execute([$id, $user['id']]);
    $cmd  = $stmt->fetch();
    if (!$cmd) jsonError('Commande introuvable', 404);

    if ($cmd['statut_commande'] !== 'en_attente') {
        jsonError('Cette commande ne peut plus être annulée (statut : ' . $cmd['statut_commande'] . ')');
    }

    $pdo->prepare('UPDATE commande SET statut_commande = \'annulée\' WHERE commande_id = ?')
        ->execute([$id]);

    $pdo->prepare('INSERT INTO historique_commande (commande_id, statut) VALUES (?, \'annulée\')')
        ->execute([$id]);

    // Remettre le stock
    if ($cmd['menu_id']) {
        $pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante + 1 WHERE menu_id = ?')
            ->execute([$cmd['menu_id']]);
    }

    jsonOk(['message' => 'Commande annulée']);
}

// ─── Modifier une commande (client) ────────────────────────────────────────────
// Tout est modifiable sauf le menu, tant que le statut est "en_attente".

function modifierCommande(int $id, array $body): void
{
    $user = authRequired();

    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT c.*, m.prix_par_personne, m.nombre_personne_mini FROM commande c JOIN menu m ON m.menu_id = c.menu_id WHERE c.commande_id = ? AND c.client_id = ?');
    $stmt->execute([$id, $user['id']]);
    $cmd  = $stmt->fetch();
    if (!$cmd) jsonError('Commande introuvable', 404);

    if ($cmd['statut_commande'] !== 'en_attente') {
        jsonError('Cette commande ne peut plus être modifiée (statut : ' . $cmd['statut_commande'] . ')');
    }

    $nbPersonnes = isset($body['nombre_personne']) ? (int)$body['nombre_personne'] : (int)$cmd['nombre_personne'];
    if ($nbPersonnes < $cmd['nombre_personne_mini']) {
        jsonError('Ce menu nécessite au minimum ' . $cmd['nombre_personne_mini'] . ' personnes');
    }

    // Recalcul du prix (même règle de remise qu'à la création)
    $prix    = (float)$cmd['prix_par_personne'];
    $surplus = $nbPersonnes - $cmd['nombre_personne_mini'];
    if ($surplus >= 5) $prix = $prix * 0.9;

    $distKm         = isset($body['distance_km']) ? (float)$body['distance_km'] : 0;
    $fraisLivraison = $distKm > 0 ? round(5 + 0.59 * $distKm, 2) : 0;
    $prixTotal      = round($prix * $nbPersonnes + $fraisLivraison, 2);

    $pdo->prepare('
        UPDATE commande SET
            nombre_personne    = ?,
            date_prestation    = COALESCE(?, date_prestation),
            adresse_livraison  = COALESCE(?, adresse_livraison),
            prix_commande      = ?
        WHERE commande_id = ?
    ')->execute([
        $nbPersonnes,
        $body['date_prestation']   ?? null,
        isset($body['adresse_livraison']) ? sanitize($body['adresse_livraison']) : null,
        $prixTotal,
        $id,
    ]);

    jsonOk(['message' => 'Commande mise à jour', 'prix_total' => $prixTotal]);
}

// ─── Demander une facture ─────────────────────────────────────────────────────

function demanderFacture(array $body): void
{
    $user = authRequired();
    require_fields($body, 'commande_id');

    $pdo  = getPDO();
    $stmt = $pdo->prepare('
        SELECT c.*, m.titre AS menu_titre, u.prenom, u.nom, u.email, u.adresse, u.ville
        FROM commande c
        LEFT JOIN menu m ON m.menu_id = c.menu_id
        LEFT JOIN utilisateur u ON u.utilisateur_id = c.client_id
        WHERE c.commande_id = ? AND c.client_id = ?
    ');
    $stmt->execute([(int)$body['commande_id'], $user['id']]);
    $cmd = $stmt->fetch();
    if (!$cmd) jsonError('Commande introuvable', 404);

    $html = mailTemplate(
        'Demande de facture — Commande n°' . $cmd['commande_id'],
        '<p>Bonjour,</p>
         <p><strong>' . htmlspecialchars($cmd['prenom'] . ' ' . $cmd['nom']) . '</strong> demande une facture pour la commande suivante :</p>
         <ul>
           <li>Commande n° : <strong>' . $cmd['commande_id'] . '</strong></li>
           <li>Menu : ' . htmlspecialchars($cmd['menu_titre'] ?? '') . '</li>
           <li>Date : ' . $cmd['date_prestation'] . '</li>
           <li>Montant : ' . number_format((float)$cmd['prix_commande'], 2, ',', ' ') . ' €</li>
           <li>Adresse : ' . htmlspecialchars($cmd['adresse'] . ', ' . $cmd['ville']) . '</li>
           <li>Email client : ' . htmlspecialchars($cmd['email']) . '</li>
         </ul>
         ' . (!empty($body['commentaire']) ? '<p>Commentaire : ' . htmlspecialchars($body['commentaire']) . '</p>' : '')
    );
    sendMail(MAIL_FROM, 'Demande de facture — Commande n°' . $cmd['commande_id'], $html);

    jsonOk(['message' => 'Votre demande de facture a été transmise.']);
}
