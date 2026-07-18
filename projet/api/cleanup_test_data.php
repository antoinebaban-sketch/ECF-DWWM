<?php
/**
 * Script ponctuel — Supprime la commande de test #6 (motif_annulation = "test")
 * et ses dépendances (historique, livraison éventuelle).
 * Accès : /api/cleanup_test_data.php?secret=vg-cleanup-2026
 * SUPPRIMER ce fichier après utilisation !
 */

$secret = $_GET['secret'] ?? '';
if ($secret !== 'vg-cleanup-2026') {
    http_response_code(403);
    die('Accès refusé.');
}

require_once __DIR__ . '/config.php';
$pdo = getPDO();
$log = [];

try {
    $check = $pdo->prepare("SELECT commande_id, motif_annulation FROM commande WHERE commande_id = 6");
    $check->execute();
    $row = $check->fetch();

    if (!$row) {
        $log[] = 'Commande #6 introuvable (déjà supprimée ?)';
    } elseif ($row['motif_annulation'] !== 'test') {
        $log[] = '❌ Commande #6 trouvée mais motif_annulation != "test" (' . $row['motif_annulation'] . ') — abandon par sécurité.';
    } else {
        $pdo->prepare("DELETE FROM historique_commande WHERE commande_id = 6")->execute();
        $log[] = 'historique_commande nettoyé';
        $pdo->prepare("DELETE FROM livraison WHERE commande_id = 6")->execute();
        $log[] = 'livraison nettoyée';
        $pdo->prepare("DELETE FROM avis WHERE commande_id = 6")->execute();
        $log[] = 'avis nettoyés';
        $pdo->prepare("DELETE FROM commande WHERE commande_id = 6")->execute();
        $log[] = '✅ Commande de test #6 supprimée';
    }
} catch (PDOException $e) {
    $log[] = '❌ Erreur : ' . $e->getMessage();
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $log);
