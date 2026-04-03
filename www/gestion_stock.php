<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$entreprise_id = $_SESSION['entreprise_id'];

// --- 1. RÉCUPÉRATION DES LISTES POUR LES FILTRES ---
$stmt_mats = $pdo->prepare("SELECT id, nom FROM materiels WHERE entreprise_id = ? ORDER BY nom ASC");
$stmt_mats->execute([$entreprise_id]);
$liste_materiels = $stmt_mats->fetchAll();

$stmt_fourn = $pdo->prepare("SELECT DISTINCT fournisseur FROM stock_mouvements WHERE entreprise_id = ? AND fournisseur != '' ORDER BY fournisseur ASC");
$stmt_fourn->execute([$entreprise_id]);
$liste_fournisseurs = $stmt_fourn->fetchAll();

// --- 2. RÉCUPÉRATION DES VALEURS DE RECHERCHE (GET) ---
$search_ref = $_GET['s_ref'] ?? '';
$search_fourn = $_GET['s_fourn'] ?? '';
$search_mat = $_GET['s_mat'] ?? '';

// --- 3. ENREGISTREMENT DU MOUVEMENT (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enregistrer_stock'])) {
    $stmt = $pdo->prepare("INSERT INTO stock_mouvements (entreprise_id, reference, marque, fournisseur, type_mouvement, quantite, usage_type, materiel_id, commentaire) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $entreprise_id, 
        $_POST['reference'], 
        $_POST['marque'], 
        $_POST['fournisseur_input'], 
        $_POST['type_mouvement'], 
        intval($_POST['quantite']), 
        $_POST['usage_type'], 
        !empty($_POST['materiel_id']) ? $_POST['materiel_id'] : null, 
        $_POST['commentaire']
    ]);
    header("Location: gestion_stock.php?success=1");
    exit;
}

// --- 4. CONSTRUCTION DE LA REQUÊTE D'HISTORIQUE FILTRÉE ---
$sql = "SELECT sm.*, m.nom as nom_materiel 
        FROM stock_mouvements sm 
        LEFT JOIN materiels m ON sm.materiel_id = m.id 
        WHERE sm.entreprise_id = ?";
$params = [$entreprise_id];

if (!empty($search_ref)) {
    $sql .= " AND sm.reference LIKE ?";
    $params[] = "%$search_ref%";
}
if (!empty($search_fourn)) {
    $sql .= " AND sm.fournisseur = ?";
    $params[] = $search_fourn;
}
if (!empty($search_mat)) {
    $sql .= " AND sm.materiel_id = ?";
    $params[] = $search_mat;
}

$sql .= " ORDER BY sm.date_mouvement DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

// --- 5. CALCUL DE L'INVENTAIRE (RESTE INCHANGÉ) ---
$stmt_inv = $pdo->prepare("
    SELECT 
        reference, 
        marque,
        SUM(CASE WHEN type_mouvement = 'ENTREE' THEN quantite ELSE 0 END) - 
        SUM(CASE WHEN type_mouvement = 'SORTIE' THEN quantite ELSE 0 END) as stock_actuel
    FROM stock_mouvements 
    WHERE entreprise_id = ? 
    GROUP BY reference, marque
    HAVING stock_actuel != 0
    ORDER BY reference ASC
");
$stmt_inv->execute([$entreprise_id]);
$inventaire = $stmt_inv->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Stock & Inventaire</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        /* Formulaire & Recherche */
        .card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .filter-bar { background: #edf2f7; padding: 15px; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 20px; border: 1px solid #cbd5e0; }
        
        label { display: block; font-size: 11px; font-weight: bold; margin-bottom: 4px; color: #64748b; text-transform: uppercase; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 13px; }
        
        .btn { padding: 10px 15px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; }
        .btn-green { background: #38a169; color: white; width: 100%; grid-column: 1 / -1; margin-top: 10px; }
        .btn-blue { background: #3182ce; color: white; }
        .btn-red { background: #e53e3e; color: white; text-decoration: none; }

        /* Inventaire */
        .inventory-scroll { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; }
        .stock-tag { background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; min-width: 150px; flex-shrink: 0; border-left: 4px solid #3182ce; }
        .stock-tag.low { border-left-color: #e53e3e; background: #fff5f5; }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f1f5f9; text-align: left; padding: 10px; border-bottom: 2px solid #cbd5e0; color: #475569; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-in { background: #c6f6d5; color: #22543d; }
        .badge-out { background: #fed7d7; color: #822727; }
		.btn-delete { 
    color: #e53e3e; 
    text-decoration: none; 
    font-size: 16px; 
    padding: 5px;
    border-radius: 4px;
    transition: background 0.2s;
}
.btn-delete:hover { background: #fff5f5; color: #c53030; }

.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
}
.alert-deleted { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
.alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    </style>
</head>
<body>

<div class="container">
<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">✅ Mouvement enregistré avec succès.</div>
<?php endif; ?>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-deleted">🗑️ Ligne de stock supprimée. L'inventaire a été mis à jour.</div>
<?php endif; ?>
    <header style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin:0;">📦 Gestion du Stock</h2>
        <a href="index.php" style="text-decoration:none; color:#3182ce; font-weight:bold;">🏠 Retour</a>
    </header>

    <div class="inventory-scroll">
        <?php foreach($inventaire as $item): ?>
            <div class="stock-tag <?= $item['stock_actuel'] <= 2 ? 'low' : '' ?>">
                <div style="font-size: 10px; color: #718096;"><?= htmlspecialchars($item['reference']) ?></div>
                <div style="font-weight: bold;"><?= htmlspecialchars($item['marque']) ?></div>
                <div style="font-size: 18px; font-weight: bold;"><?= $item['stock_actuel'] ?> <small>pce</small></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <label style="font-size: 14px; margin-bottom: 10px; color: #2d3748;">➕ Enregistrer un nouveau mouvement</label>
        <form method="POST">
            <div class="grid">
                <div>
                    <label>Référence</label>
                    <input type="text" name="reference" required>
                </div>
                <div>
                    <label>Marque</label>
                    <input type="text" name="marque" required>
                </div>
                <div>
                    <label>Fournisseur</label>
                    <input type="text" name="fournisseur_input" list="dl_fourn" placeholder="Nom du tiers...">
                    <datalist id="dl_fourn">
                        <?php foreach($liste_fournisseurs as $f): ?><option value="<?= htmlspecialchars($f['fournisseur']) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label>Mouvement</label>
                    <select name="type_mouvement">
                        <option value="ENTREE">ENTRÉE (+)</option>
                        <option value="SORTIE">SORTIE (-)</option>
                    </select>
                </div>
                <div>
                    <label>Quantité</label>
                    <input type="number" name="quantite" value="1" min="1">
                </div>
                <div>
                    <label>Matériel Lié</label>
                    <select name="materiel_id">
                        <option value="">-- Aucun --</option>
                        <?php foreach($liste_materiels as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Usage</label>
                    <select name="usage_type">
                        <option value="ENTRETIEN">Entretien</option>
                        <option value="ACHAT">Stock</option>
                        <option value="SAV">SAV</option>
                    </select>
                </div>
                <div style="grid-column: span 2;">
                    <label>Commentaire</label>
                    <input type="text" name="commentaire">
                </div>
            </div>
            <button type="submit" name="enregistrer_stock" class="btn btn-green">💾 Valider le mouvement</button>
        </form>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display: contents;">
            <div style="flex: 2;">
                <label>Rechercher une référence</label>
                <input type="text" name="s_ref" value="<?= htmlspecialchars($search_ref) ?>" placeholder="ex: Filtre à huile...">
            </div>
            <div style="flex: 1;">
                <label>Par Fournisseur</label>
                <select name="s_fourn">
                    <option value="">Tous</option>
                    <?php foreach($liste_fournisseurs as $f): ?>
                        <option value="<?= htmlspecialchars($f['fournisseur']) ?>" <?= $search_fourn == $f['fournisseur'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['fournisseur']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1;">
                <label>Par Matériel</label>
                <select name="s_mat">
                    <option value="">Tous</option>
                    <?php foreach($liste_materiels as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $search_mat == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-blue">🔍 Filtrer</button>
            <?php if($search_ref || $search_fourn || $search_mat): ?>
                <a href="gestion_stock.php" class="btn btn-red">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Référence / Marque</th>
                <th>Fournisseur</th>
                <th>Mouvement</th>
                <th>Matériel</th>
                <th>Détails</th>
				<th>Act</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($mouvements as $m): ?>
            <tr>
                <td><?= date('d/m/y H:i', strtotime($m['date_mouvement'])) ?></td>
                <td>
                    <strong><?= htmlspecialchars($m['reference']) ?></strong><br>
                    <small style="color: #718096;"><?= htmlspecialchars($m['marque']) ?></small>
                </td>
                <td><?= htmlspecialchars($m['fournisseur'] ?: '-') ?></td>
                <td>
                    <span class="badge <?= $m['type_mouvement'] == 'ENTREE' ? 'badge-in' : 'badge-out' ?>">
                        <?= $m['type_mouvement'] ?> (<?= $m['quantite'] ?>)
                    </span>
                </td>
                <td style="color:#3182ce; font-weight:bold;">
                    <?= $m['nom_materiel'] ? '🚜 '.htmlspecialchars($m['nom_materiel']) : '-' ?>
                </td>
                <td>
                    <span style="font-size: 11px; font-weight: bold;"><?= $m['usage_type'] ?></span><br>
                    <span style="color: #718096; font-style: italic;"><?= htmlspecialchars($m['commentaire']) ?></span>
                </td>

				<td><a href="supprimer_mouvement_stock.php?id=<?= $m['id'] ?>" 
           class="btn-delete" 
           title="Supprimer cette ligne">
           🗑️
        </a></td>


            </tr>
			
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>