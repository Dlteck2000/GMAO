<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$id_config = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupération de la config et des infos du matériel pour le titre
$stmt = $pdo->prepare("
    SELECT c.*, m.nom as nom_materiel 
    FROM aog_configs c 
    JOIN materiels m ON c.materiel_id = m.id 
    WHERE c.id = ?
");
$stmt->execute([$id_config]);
$config = $stmt->fetch();

if (!$config) { die("Configuration introuvable."); }

// Décodage des données JSON
$donnees = json_decode($config['donnees_json'], true);

// Dictionnaire pour rendre les noms plus lisibles (Optionnel)
$labels = [
    'purePursuitIntegralGainAB'         => 'Gain Intégral Pure Pursuit (AB)',
    'setVehicle_goalPointLookAheadHold' => 'Look Ahead Hold (Point cible)',
    'setAS_wasOffset'                   => 'Offset WAS (Capteur angle)',
    'setAS_countsPerDegree'             => 'Counts par Degré',
    'setAS_ackerman'                    => 'Réglage Ackerman (%)',
    'setVehicle_maxSteerAngle'          => 'Angle de braquage max',
    'setAS_Kp'                          => 'Gain Proportionnel (Kp)',
    'setAS_highSteerPWM'                => 'PWM Braquage Max',
    'setAS_minSteerPWM'                 => 'PWM Braquage Min'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config AOG - <?= htmlspecialchars($config['nom_materiel']) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
        .card { max-width: 600px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { color: #6f42c1; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; }
        .meta { color: #666; font-size: 0.9em; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        th { background: #fafafa; color: #4a5568; font-size: 13px; text-transform: uppercase; }
        .valeur { font-family: monospace; font-weight: bold; color: #28a745; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; }
        .btn-back { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #007bff; font-weight: bold; }
    </style>
</head>
<body>

<div class="card">
    <a href="fiche_materiel.php?id=<?= $config['materiel_id'] ?>" class="btn-back">⬅ Retour à la fiche</a>
    
    <h2>Détails Configuration AgOpenGPS</h2>
    <div class="meta">
        Matériel : <strong><?= htmlspecialchars($config['nom_materiel']) ?></strong><br>
        Version : <strong><?= htmlspecialchars($config['version_aog']) ?></strong><br>
        Importé le : <strong><?= date('d/m/Y à H:i', strtotime($config['date_import'])) ?></strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Paramètre</th>
                <th>Valeur</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($donnees as $cle => $valeur): ?>
            <tr>
                <td>
                    <small style="color:#999; display:block; font-size:10px;"><?= htmlspecialchars($cle) ?></small>
                    <?= htmlspecialchars($labels[$cle] ?? $cle) ?>
                </td>
                <td><span class="valeur"><?= htmlspecialchars($valeur) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>