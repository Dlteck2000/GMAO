<?php 
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) {
    header('Location: login.php');
    exit;
}

$entreprise_id = $_SESSION['entreprise_id'];
$nom_utilisateur = $_SESSION['nom'];
$nom_entreprise = $_SESSION['entreprise_nom'] ?? "Mon Parc";

$sql = "SELECT m.*, n.fichier_path 
        FROM materiels m 
        LEFT JOIN notices n ON m.id = n.materiel_id 
        WHERE m.entreprise_id = ?
        GROUP BY m.id
        ORDER BY m.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$entreprise_id]);
$liste_materiel = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMAO - Parc Matériel</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 10px; color: #333; }
        .container { max-width: 1100px; margin: auto; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        /* HEADER COMPACT */
        header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
            gap: 10px;
        }
        
        .brand-zone h1 { font-size: 18px; margin: 0; color: #2d3748; white-space: nowrap; }
        .user-badge { font-size: 11px; color: #718096; background: #edf2f7; padding: 2px 8px; border-radius: 10px; }

        /* NAV ET BOUTONS ALIGNÉS */
        .nav-container { display: flex; align-items: center; gap: 8px; }
        
        nav { display: flex; gap: 4px; }
        nav a { 
            text-decoration: none; 
            padding: 6px 10px; 
            border-radius: 6px; 
            font-size: 13px; 
            background: #f8f9fa; 
            color: #4a5568; 
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            transition: 0.2s;
        }
        nav a:hover { background: #edf2f7; }
        nav a.logout { color: #e53e3e; border-color: #feb2b2; background: #fff5f5; }

        .btn-add { 
            background: #28a745; 
            color: white; 
            text-decoration: none; 
            padding: 7px 12px; 
            border-radius: 6px; 
            font-weight: bold; 
            font-size: 13px; 
            white-space: nowrap;
        }

        /* TABLE DESIGN */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fa; color: #666; text-align: left; padding: 12px; border-bottom: 2px solid #dee2e6; font-size: 11px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #eee; }

        /* MAINTENANCE BARS */
        .progress-container { background: #eee; border-radius: 10px; height: 8px; overflow: hidden; margin-top: 5px; width: 100px; }
        .progress-bar { height: 100%; transition: width 0.3s; }
        .badge { padding: 3px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-success { background: #d4edda; color: #155724; }

        .btn-action { padding: 6px 10px; text-decoration: none; border-radius: 5px; font-size: 14px; display: inline-block; }
        .btn-intervenir { background: #007bff; color: white; }
        .btn-pdf { background: #dc3545; color: white; margin-left: 5px; }

        /* --- RESPONSIVE MOBILE --- */
        @media (max-width: 850px) {
            header { flex-direction: column; align-items: flex-start; }
            .nav-container { width: 100%; flex-direction: column; align-items: stretch; }
            nav { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; }
            nav a { text-align: center; padding: 10px 5px; }
            .btn-add { text-align: center; width: 100%; box-sizing: border-box; }

            /* Table en cartes pour mobile */
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 15px; padding: 10px; background: #fff; }
            td { border: none; border-bottom: 1px solid #f7fafc; position: relative; padding-left: 45% !important; text-align: left; min-height: 30px; font-size: 13px; }
            td:last-child { border-bottom: 0; }
            td::before {
                content: attr(data-label);
                position: absolute; left: 10px; width: 40%; font-weight: bold; color: #718096; font-size: 11px; text-transform: uppercase;
            }
            .progress-container { width: 100% !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="brand-zone">
            <h1>🔧 <?= htmlspecialchars($nom_entreprise) ?></h1>
            <div class="user-badge">👤 <?= htmlspecialchars($nom_utilisateur) ?></div>
        </div>

        <div class="nav-container">
            <nav>
                <a href="index.php" title="Accueil">🏠</a>
                <a href="gps_manuel.php">GPS</a>
                <a href="gestion_stock.php">Stocks</a>
                <a href="interventions_externes.php">Interv.</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="super_admin.php" title="Admin">🛡️</a>
					<a href="sauvegarde.php" title="Sauvegarde">💾</a>
                <?php endif; ?>
                <a href="logout.php" class="logout" title="Déconnexion">🚪</a>
            </nav>
            <a href="ajouter_materiel.php" class="btn-add">➕ Matériel</a>
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th>Équipement</th>
                <th>Compteur</th>
                <th>Maintenance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($liste_materiel)): ?>
            <tr>
                <td colspan="4" style="text-align: center; padding: 40px; color: #a0aec0;">
                    <p>Aucun équipement enregistré.</p>
                    <a href="ajouter_materiel.php" class="btn-intervenir" style="padding: 10px 20px; text-decoration: none; border-radius: 8px;">Créer mon premier matériel</a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($liste_materiel as $m): 
                $heures_actuelles = intval($m['compteur_heures_actuel']);
                $dernier_entretien = intval($m['maintenance_dernier_compteur']);
                $frequence = (!empty($m['maintenance_frequence'])) ? intval($m['maintenance_frequence']) : 500;
                $diff = $heures_actuelles - $dernier_entretien;
                $ratio = ($diff / $frequence) * 100;
                $alerte = ($diff >= $frequence);
            ?>
                <tr>
                    <td data-label="Équipement">
                        <a href="fiche_materiel.php?id=<?= $m['id'] ?>" style="color: #3182ce; font-weight: bold; text-decoration:none;">
                            <?= htmlspecialchars($m['nom']) ?>
                        </a><br>
                        <small style="color: #718096;"><?= htmlspecialchars($m['modele']) ?></small>
                    </td>
                    <td data-label="Compteur">
                        <strong><?= number_format($heures_actuelles, 0, ',', ' ') ?></strong> h
                    </td>
                    <td data-label="Maintenance">
                        <?php if ($alerte): ?>
                            <span class="badge badge-danger">⚠️ RÉVISION</span>
                        <?php else: ?>
                            <span class="badge badge-success">OK</span>
                        <?php endif; ?>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?= min(100, $ratio) ?>%; background: <?= $alerte ? '#e53e3e' : '#38a169' ?>;"></div>
                        </div>
                        <small style="font-size: 10px; color: #718096;"><?= $diff ?> / <?= $frequence ?>h</small>
                    </td>
                    <td data-label="Actions">
                        <a href="ajouter_intervention.php?id=<?= $m['id'] ?>" class="btn-action btn-intervenir" title="Intervenir">🛠</a>
                        <?php if (!empty($m['fichier_path'])): ?>
                            <a href="uploads/notices/<?= htmlspecialchars($m['fichier_path']) ?>" class="btn-action btn-pdf" target="_blank" title="Notice PDF">📄</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if(file_exists('footer.php')) include 'footer.php'; ?>
</body>
</html>