<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id']) || !isset($_GET['id'])) {
    header('Location: interventions_externes.php');
    exit;
}

$id = $_GET['id'];
$entreprise_id = $_SESSION['entreprise_id'];

// Sécurité : On vérifie l'ID et l'entreprise_id pour éviter qu'un utilisateur supprime les données d'un autre
$stmt = $pdo->prepare("DELETE FROM interventions_externes WHERE id = ? AND entreprise_id = ?");
$stmt->execute([$id, $entreprise_id]);

header('Location: interventions_externes.php?msg=deleted');
exit;