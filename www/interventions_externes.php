<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$entreprise_id = $_SESSION['entreprise_id'];

// --- 1. RÉCUPÉRATION DES LISTES POUR LES FILTRES ---
// Liste des machines
$stmt_mats = $pdo->prepare("SELECT id, nom, marque FROM materiels WHERE entreprise_id = ? ORDER BY nom ASC");
$stmt_mats->execute([$entreprise_id]);
$liste_materiels = $stmt_mats->fetchAll();

// Liste unique des prestataires (pour le filtre)
$stmt_pres = $pdo->prepare("SELECT DISTINCT intervenant_nom FROM interventions_externes WHERE entreprise_id = ? ORDER BY intervenant_nom ASC");
$stmt_pres->execute([$entreprise_id]);
$liste_prestataires = $stmt_pres->fetchAll();

// --- 2. RÉCUPÉRATION DES VALEURS DE FILTRE (GET) ---
$f_machine = $_GET['f_mat'] ?? '';
$f_prestataire = $_GET['f_pre'] ?? '';

// --- 3. ENREGISTREMENT D'UNE NOUVELLE INTERVENTION (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_inter'])) {
    $stmt = $pdo->prepare("INSERT INTO interventions_externes (entreprise_id, intervenant_nom, date_intervention, motif, materiel_concerne, ref_bon, commentaire) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $entreprise_id, 
        $_POST['intervenant_nom'], 
        $_POST['date_intervention'], 
        $_POST['motif'], 
        $_POST['materiel_concerne'], 
        $_POST['ref_bon'], 
        $_POST['commentaire']
    ]);
    header("Location: interventions_externes.php?msg=ok");
    exit;
}

// --- 4. CONSTRUCTION DE LA REQUÊTE AVEC FILTRES DYNAMIQUES ---
$sql = "SELECT * FROM interventions_externes WHERE entreprise_id = ?";
$params = [$entreprise_id];

if (!empty($f_machine)) {
    $sql .= " AND materiel_concerne = ?";
    $params[] = $f_machine;
}
if (!empty($f_prestataire)) {
    $sql .= " AND intervenant_nom = ?";
    $params[] = $f_prestataire;
}

$sql .= " ORDER BY date_intervention DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$interventions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Interventions Externes</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        /* Formulaire */
        .card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #64748b; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; }

        /* Barre de filtres */
        .filter-zone { background: #e2e8f0; padding: 15px; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .filter-zone div { flex: 1; min-width: 200px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; background: white; }
        th { background: #f1f5f9; text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #475569; border-bottom: 2px solid #cbd5e0; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .btn { padding: 10px 15px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; text-align: center;}
        .btn-blue { background: #3182ce; color: white; }
        .btn-red { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
		.btn-delete { 
			color: #e53e3e; 
			text-decoration: none; 
			font-size: 18px; 
			margin-left: 10px; 
			display: inline-block;
			transition: transform 0.2s;
		}
		.btn-delete:hover { transform: scale(1.2); color: #c53030; }
    </style>
</head>
<body>

<div class="container">
    <header style="display:flex; justify-content: space-between; margin-bottom: 20px;">
        <a href="index.php" style="text-decoration:none; color:#3182ce; font-weight:bold;">🏠 Retour Accueil</a>
        <h2 style="margin:0;">🛠 Registre Interventions Externes</h2>
		<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div style="background:#fff5f5; color:#c53030; padding:10px; border-radius:8px; margin-bottom:20px; border:1px solid #feb2b2; font-weight:bold;">
        🗑️ L'intervention a été supprimée.
    </div>
<?php endif; ?>
    </header>

    <div class="card">
        <form method="POST">
            <div class="grid">
<div>
    <label>Intervenant / Garage</label>
    <input list="prestataires_list" name="intervenant_nom" placeholder="Choisir ou taper..." required>
    <datalist id="prestataires_list">
		<option value="Verhaege">Verhaege</option>
		<option value="David">David</option>
        <?php foreach($liste_prestataires as $p): ?>
            <option value="<?= htmlspecialchars($p['intervenant_nom']) ?>">
        <?php endforeach; ?>
    </datalist>
</div>
                <div>
                    <label>Date</label>
                    <input type="date" name="date_intervention" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label>Matériel</label>
                    <select name="materiel_concerne" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach($liste_materiels as $m): ?>
                            <option value="<?= htmlspecialchars($m['nom']) ?>"><?= htmlspecialchars($m['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Réf. Bon / Facture</label>
                    <input type="text" name="ref_bon">
                </div>
                <div style="grid-column: span 2;">
                    <label>Travaux réalisés</label>
                    <input type="text" name="motif" required placeholder="ex: Vidange complète + filtres">
                </div>
                <div style="grid-column: span 2;">
                    <label>Notes (optionnel)</label>
                    <textarea name="commentaire" rows="1"></textarea>
                </div>
            </div>
            <button type="submit" name="save_inter" class="btn btn-blue" style="width:100%; margin-top:15px;">💾 Enregistrer</button>
        </form>
    </div>

    <div class="filter-zone">
        <form method="GET" style="display: contents;">
            <div>
                <label>Filtrer par Matériel</label>
                <select name="f_mat">
                    <option value="">Tous les matériels</option>
                    <?php foreach($liste_materiels as $m): ?>
                        <option value="<?= htmlspecialchars($m['nom']) ?>" <?= $f_machine == $m['nom'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Filtrer par Prestataire</label>
                <select name="f_pre">
                    <option value="">Tous les prestataires</option>
                    <?php foreach($liste_prestataires as $p): ?>
                        <option value="<?= htmlspecialchars($p['intervenant_nom']) ?>" <?= $f_prestataire == $p['intervenant_nom'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['intervenant_nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-blue">🔍 Appliquer</button>
            <?php if($f_machine || $f_prestataire): ?>
                <a href="interventions_externes.php" class="btn btn-red">❌ Effacer</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
		<tr>
			<th>Date</th>
			<th>Matériel</th>
			<th>Intervenant</th>
			<th>Motif</th>
			<th>Réf</th>
			<th style="width:50px;"></th>
		</tr>
        </thead>
        <tbody>
            <?php foreach($interventions as $i): ?>
			<tr>
				<td><strong><?= date('d/m/Y', strtotime($i['date_intervention'])) ?></strong></td>
				<td style="color:#2b6cb0; font-weight:bold;"><?= htmlspecialchars($i['materiel_concerne']) ?></td>
				<td><?= htmlspecialchars($i['intervenant_nom']) ?></td>
				<td><?= htmlspecialchars($i['motif']) ?></td>
				<td><small style="background:#edf2f7; padding:3px; border-radius:4px;"><?= htmlspecialchars($i['ref_bon']) ?></small></td>

<td style="text-align: center;">
    <a href="supprimer_intervention_ext.php?id=<?= $i['id'] ?>" 
       class="btn-delete" 
       title="Supprimer définitivement"
       style="color: #e53e3e; text-decoration: none; font-size: 1.2em;">
       🗑️
    </a>

				</td>
			</tr>
            <?php endforeach; ?>
            <?php if(empty($interventions)): ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">Aucune donnée correspondante.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>