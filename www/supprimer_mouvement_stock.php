<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id']) || !isset($_GET['id'])) {
    header('Location: gestion_stock.php');
    exit;
}

$id = $_GET['id'];
$entreprise_id = $_SESSION['entreprise_id'];

// Sécurité : on vérifie l'ID et l'appartenance à l'entreprise
$stmt = $pdo->prepare("DELETE FROM stock_mouvements WHERE id = ? AND entreprise_id = ?");
$stmt->execute([$id, $entreprise_id]);

// Retour à la page avec un message de succès
header('Location: gestion_stock.php?msg=deleted');
exit;