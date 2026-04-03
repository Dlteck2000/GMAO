<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$entreprise_id = $_SESSION['entreprise_id'];
$id_int = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_int > 0) {
    // Vérification de sécurité : l'intervention appartient-elle à une machine de CETTE entreprise ?
    $check = $pdo->prepare("
        SELECT i.id, i.materiel_id, i.facture_pdf 
        FROM interventions i 
        JOIN materiels m ON i.materiel_id = m.id 
        WHERE i.id = ? AND m.entreprise_id = ?
    ");
    $check->execute([$id_int, $entreprise_id]);
    $intervention = $check->fetch();

    if ($intervention) {
        // 1. Supprimer le fichier physique de la facture si il existe
        if ($intervention['facture_pdf']) {
            $chemin = "uploads/factures/" . $intervention['facture_pdf'];
            if (file_exists($chemin)) {
                unlink($chemin);
            }
        }

        // 2. Supprimer la ligne en base de données
        $delete = $pdo->prepare("DELETE FROM interventions WHERE id = ?");
        $delete->execute([$id_int]);

        // Redirection vers la fiche du matériel concerné
        header("Location: fiche_materiel.php?id=" . $intervention['materiel_id'] . "&msg=supprime");
        exit;
    }
}

// Si erreur ou accès refusé
header("Location: index.php");
exit;