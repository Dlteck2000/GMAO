<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['entreprise_id'])) exit;

$ent_id = $_SESSION['entreprise_id'];

// Nom du fichier avec la date du jour
$filename = "export_maintenance_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// Entête du CSV (UTF-8 BOM pour Excel)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, ['Date', 'Machine', 'Modèle', 'Compteur (h)', 'Technicien', 'Description']);

$query = "SELECT i.date_intervention, m.nom, m.modele, i.relevé_compteur, u.nom as tech, i.description 
          FROM interventions i 
          JOIN materiels m ON i.materiel_id = m.id 
          JOIN utilisateurs u ON i.technicien_id = u.id
          WHERE m.entreprise_id = ? 
          ORDER BY i.date_intervention DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$ent_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}
fclose($output);
exit;