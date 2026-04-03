<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$ent_id = $_SESSION['entreprise_id'];

// 1. Nombre total de machines
$stmt = $pdo->prepare("SELECT COUNT(*) FROM materiels WHERE entreprise_id = ?");
$stmt->execute([$ent_id]);
$total_machines = $stmt->fetchColumn();

// 2. Machines en alerte
$stmt = $pdo->prepare("SELECT COUNT(*) FROM materiels WHERE entreprise_id = ? AND (compteur_heures_actuel - maintenance_dernier_compteur) >= maintenance_frequence");
$stmt->execute([$ent_id]);
$en_alerte = $stmt->fetchColumn();

// 3. Total des heures cumulées du parc
$stmt = $pdo->prepare("SELECT SUM(compteur_heures_actuel) FROM materiels WHERE entreprise_id = ?");
$stmt->execute([$ent_id]);
$total_heures = $stmt->fetchColumn() ?: 0;

// 4. Top 3 des machines les plus utilisées (plus gros compteurs)
$stmt = $pdo->prepare("SELECT nom, compteur_heures_actuel FROM materiels WHERE entreprise_id = ? ORDER BY compteur_heures_actuel DESC LIMIT 3");
$stmt->execute([$ent_id]);
$top_machines = $stmt->fetchAll();

// 5. Nombre d'interventions ce mois-ci
$stmt = $pdo->prepare("SELECT COUNT(*) FROM interventions i JOIN materiels m ON i.materiel_id = m.id WHERE m.entreprise_id = ? AND MONTH(i.date_intervention) = MONTH(CURRENT_DATE())");
$stmt->execute([$ent_id]);
$int_mois = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques Parc</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-card h3 { margin: 0; color: #666; font-size: 14px; text-transform: uppercase; }
        .stat-card p { margin: 10px 0 0; font-size: 32px; font-weight: bold; color: #007bff; }
        .alerte { color: #dc3545 !important; }
        .container { max-width: 1000px; margin: auto; }
        .chart-box { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
        .btn-back { text-decoration: none; color: #007bff; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Retour au Parc</a>
    <h1 style="margin-top: 20px;">Tableau de Bord Global</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Machines totales</h3>
            <p><?= $total_machines ?></p>
        </div>
        <div class="stat-card">
            <h3>Maintenance Due</h3>
            <p class="<?= ($en_alerte > 0) ? 'alerte' : '' ?>"><?= $en_alerte ?></p>
        </div>
        <div class="stat-card">
            <h3>Heures Parc</h3>
            <p><?= number_format($total_heures, 0, '.', ' ') ?></p>
        </div>
        <div class="stat-card">
            <h3>Interventions / mois</h3>
            <p><?= $int_mois ?></p>
        </div>
    </div>

    <div class="chart-box">
        <h3>🏆 Top 3 Utilisation (Heures)</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <?php foreach($top_machines as $tm): ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 15px 0;"><?= htmlspecialchars($tm['nom']) ?></td>
                <td style="text-align: right; font-weight: bold;"><?= number_format($tm['compteur_heures_actuel'], 0, '.', ' ') ?> h</td>
            </tr>
            <?php endforeach; ?>
        </table>
		<a href="export_csv.php" class="btn">📥 Exporter vers Excel</a>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>