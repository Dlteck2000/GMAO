<?php
session_start();
require_once 'db.php';

// Sécurité : Seul un admin peut supprimer (ou un compte super-admin spécifique)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Action non autorisée.");
}

if (isset($_GET['id_ent'])) {
    $id_ent = intval($_GET['id_ent']);

    try {
        $pdo->beginTransaction();

        // --- 1. Supprimer les fichiers physiques des NOTICES ---
        $stmt1 = $pdo->prepare("SELECT n.fichier_path FROM notices n JOIN materiels m ON n.materiel_id = m.id WHERE m.entreprise_id = ?");
        $stmt1->execute([$id_ent]);
        while ($f = $stmt1->fetch()) {
            $path = "uploads/notices/" . $f['fichier_path'];
            if (file_exists($path)) unlink($path);
        }

        // --- 2. Supprimer les fichiers physiques des FACTURES ---
        $stmt2 = $pdo->prepare("SELECT i.facture_pdf FROM interventions i JOIN materiels m ON i.materiel_id = m.id WHERE m.entreprise_id = ?");
        $stmt2->execute([$id_ent]);
        while ($f = $stmt2->fetch()) {
            if ($f['facture_pdf']) {
                $path = "uploads/factures/" . $f['facture_pdf'];
                if (file_exists($path)) unlink($path);
            }
        }

        // --- 3. Suppression de l'entreprise ---
        // Grâce au ON DELETE CASCADE configuré plus tôt, cela supprime :
        // Les utilisateurs, les matériels, les interventions, les notices et l'entreprise elle-même.
        $stmt3 = $pdo->prepare("DELETE FROM entreprises WHERE id = ?");
        $stmt3->execute([$id_ent]);

        $pdo->commit();
        echo "L'entreprise et toutes ses données (fichiers inclus) ont été supprimées.";
        header("refresh:3;url=index.php");

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur fatale : " . $e->getMessage());
    }
} else {
    echo "ID entreprise manquant.";
}