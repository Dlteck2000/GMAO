<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$entreprise_id = $_SESSION['entreprise_id'];
$id_machine = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- TRAITEMENT DES FORMULAIRES ---

// A. Ajout d'une pièce détachée
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajouter_piece'])) {
    $nom_p = $_POST['nom_piece'];
    $ref_p = $_POST['reference_piece'];
    $stmt = $pdo->prepare("INSERT INTO pieces_detachees (materiel_id, nom_piece, reference) VALUES (?, ?, ?)");
    $stmt->execute([$id_machine, $nom_p, $ref_p]);
    header("Location: fiche_materiel.php?id=$id_machine&success=piece"); exit;
}

// B. Upload d'une facture
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_facture'])) {
    if (!empty($_FILES['facture_file']['name'])) {
        $ext = pathinfo($_FILES['facture_file']['name'], PATHINFO_EXTENSION);
        $nom_final = "facture_" . time() . "_" . $id_machine . "." . $ext;
        if (move_uploaded_file($_FILES['facture_file']['tmp_name'], "uploads/factures/" . $nom_final)) {
            $stmt = $pdo->prepare("INSERT INTO factures (materiel_id, nom_fichier) VALUES (?, ?)");
            $stmt->execute([$id_machine, $nom_final]);
            header("Location: fiche_materiel.php?id=$id_machine&success=facture"); exit;
        }
    }
}

// C. suppression
// C. suppression complète
if (isset($_POST['supprimer_materiel'])) {
    try {
        $pdo->beginTransaction();

        // 1. On supprime d'abord les données liées (pour éviter le blocage SQLite)
        $pdo->prepare("DELETE FROM interventions WHERE materiel_id = ?")->execute([$id_machine]);
        $pdo->prepare("DELETE FROM factures WHERE materiel_id = ?")->execute([$id_machine]);
        $pdo->prepare("DELETE FROM pieces_detachees WHERE materiel_id = ?")->execute([$id_machine]);
        $pdo->prepare("DELETE FROM gps_data WHERE materiel_id = ?")->execute([$id_machine]);
        $pdo->prepare("DELETE FROM notices WHERE materiel_id = ?")->execute([$id_machine]);

        // 2. On supprime enfin le matériel
        $stmt = $pdo->prepare("DELETE FROM materiels WHERE id = ? AND entreprise_id = ?");
        $stmt->execute([$id_machine, $entreprise_id]);

        $pdo->commit();
        
        // Redirection vers l'index avec un message
        header("Location: index.php?msg=supprime");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la suppression : " . $e->getMessage());
    }
}

// D. Suppression d'une intervention seule
if (isset($_GET['suppr_inter']) && isset($_GET['confirm_inter'])) {
    $id_inter = intval($_GET['suppr_inter']);
    // On vérifie que l'intervention appartient bien à ce matériel pour la sécurité
    $stmt = $pdo->prepare("DELETE FROM interventions WHERE id = ? AND materiel_id = ?");
    $stmt->execute([$id_inter, $id_machine]);
    header("Location: fiche_materiel.php?id=$id_machine&msg=inter_supprimee");
    exit;
}

// --- RÉCUPÉRATION DES DONNÉES ---
$stmt = $pdo->prepare("SELECT m.*, n.fichier_path FROM materiels m LEFT JOIN notices n ON m.id = n.materiel_id WHERE m.id = ? AND m.entreprise_id = ?");
$stmt->execute([$id_machine, $entreprise_id]);
$machine = $stmt->fetch();
if (!$machine) { die("Accès refusé."); }

$interventions = $pdo->prepare("SELECT i.*, u.nom as nom_technicien FROM interventions i JOIN utilisateurs u ON i.technicien_id = u.id WHERE i.materiel_id = ? ORDER BY i.date_intervention DESC");
$interventions->execute([$id_machine]);

$pieces = $pdo->prepare("SELECT * FROM pieces_detachees WHERE materiel_id = ?");
$pieces->execute([$id_machine]);

$factures = $pdo->prepare("SELECT * FROM factures WHERE materiel_id = ? ORDER BY date_upload DESC");
$factures->execute([$id_machine]);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($machine['nom']) ?></title>
    <style>
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 10px; color: #333; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        .header-fiche { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .btn-retour { text-decoration: none; color: #007bff; font-weight: bold; font-size: 14px; display: block; margin-bottom: 10px; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .section { background: #fafafa; border: 1px solid #eaeaea; padding: 15px; border-radius: 10px; }
        h3 { margin-top: 0; color: #4a5568; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 8px; border-bottom: 1px solid #eee; text-align: left; }
        
        .btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.2s; }
        .btn-blue { background: #007bff; color: white; width: 100%; margin-top: 5px; }
        .btn-green { background: #28a745; color: white; margin-top: 5px; }
        
        input[type="text"], input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; margin-bottom: 8px; }

        /* --- RESPONSIVE MOBILE --- */
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; } /* On empile les deux colonnes du haut */
            
            /* Tableaux de l'historique en mode "Cards" */
            .table-mobile, .table-mobile thead, .table-mobile tbody, .table-mobile th, .table-mobile td, .table-mobile tr { display: block; }
            .table-mobile thead { display: none; }
            .table-mobile tr { border: 1px solid #ddd; margin-bottom: 15px; border-radius: 8px; background: white; padding: 10px; }
            .table-mobile td { border: none; border-bottom: 1px solid #f0f0f0; position: relative; padding-left: 45% !important; text-align: left; min-height: 35px; }
            .table-mobile td::before {
                content: attr(data-label);
                position: absolute; left: 10px; width: 40%; font-weight: bold; color: #777; font-size: 11px; text-transform: uppercase;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-retour">⬅ Retour</a>
    <h1><?= htmlspecialchars($machine['nom'] ?? 'Matériel inconnu') ?> (<?= htmlspecialchars($machine['modele'] ?? '-') ?>)</h1>
	<a href="modifier_materiel.php?id=<?= $id_machine ?>" style="background: #e2e8f0; padding: 8px 15px; border-radius: 5px; text-decoration: none; color: #4a5568; font-size: 14px;">
    ✏️ Modifier la fiche
</a>

    <div class="grid">
        <div class="section">
            <h3>📦 Pièces & Références</h3>
			<a href="modifier_pieces.php?id_mat=<?= $id_machine ?>" class="btn btn-blue" style="font-size: 12px; text-decoration: none; padding: 5px 10px;">
            ✏️ Gérer les pièces
			</a>
            <table>
                <?php while($p = $pieces->fetch()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['nom_piece'] ?? '') ?></strong></td>
                        <td><code><?= htmlspecialchars($p['reference'] ?? '') ?></code></td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <form method="POST" style="margin-top:10px; display: flex; gap: 5px;">
                <input type="text" name="nom_piece" placeholder="Filtre..." required>
                <input type="text" name="reference_piece" placeholder="Référence" required>
                <button type="submit" name="ajouter_piece" class="btn btn-blue" style="width: 50px;">+</button>
            </form>
        </div>

        <div class="section">
            <h3>🧾 Factures & Docs</h3>
            <ul style="list-style: none; padding: 0;">
                <?php while($f = $factures->fetch()): ?>
                    <li style="margin-bottom:8px;">
                        <a href="uploads/factures/<?= htmlspecialchars($f['nom_fichier']) ?>" target="_blank" style="text-decoration:none;">
                            📄 Facture du <?= date('d/m/Y', strtotime($f['date_upload'] ?? 'now')) ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
            <form method="POST" enctype="multipart/form-data" style="border-top: 1px solid #ddd; padding-top:10px;">
                <input type="file" name="facture_file" accept=".pdf,image/*" required>
                <button type="submit" name="upload_facture" class="btn btn-green">Uploader</button>
            </form>
        </div>
		</div>
		<div class="section" style="margin-top:20px; border-left: 5px solid #28a745;">
			<h3 style="display: flex; align-items: center; gap: 10px;">
				🔧 Caractéristiques Techniques d'Entretien
			</h3>
			
			<div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
				
				<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
					<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px; text-transform: uppercase;">🔥 Partie Moteur</h4>
					<table style="width: 100%; font-size: 13px;">
						<tr>
							<td style="color: #718096;">Huile :</td>
							<td><strong><?= htmlspecialchars($machine['huile_moteur_type'] ?? '-') ?></strong></td>
						</tr>
						<tr>
							<td style="color: #718096;">Capacité :</td>
							<td><strong><?= htmlspecialchars($machine['huile_moteur_qte'] ?? '-') ?></strong></td>
						</tr>
						<tr>
							<td style="color: #718096;">Filtre :</td>
							<td><span style="background: #edf2f7; padding: 2px 6px; border-radius: 4px; font-family: monospace;"><?= htmlspecialchars($machine['filtre_moteur_ref'] ?? '-') ?></span></td>
						</tr>
					</table>
				</div>

				<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
					<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px; text-transform: uppercase;">⚙️ Transmission / Hydro</h4>
					<table style="width: 100%; font-size: 13px;">
						<tr>
							<td style="color: #718096;">Huile :</td>
							<td><strong><?= htmlspecialchars($machine['huile_trans_type'] ?? '-') ?></strong></td>
						</tr>
						<tr>
							<td style="color: #718096;">Capacité :</td>
							<td><strong><?= htmlspecialchars($machine['huile_trans_qte'] ?? '-') ?></strong></td>
						</tr>
						<tr>
							<td style="color: #718096;">Filtres :</td>
							<td>
								<div style="display:flex; flex-direction:column; gap:4px;">
									<span style="background: #edf2f7; padding: 2px 6px; border-radius: 4px; font-family: monospace; width: fit-content;"><?= htmlspecialchars($machine['filtre_trans_ref1'] ?? '-') ?></span>
									<?php if(!empty($machine['filtre_trans_ref2'])): ?>
										<span style="background: #edf2f7; padding: 2px 6px; border-radius: 4px; font-family: monospace; width: fit-content;"><?= htmlspecialchars($machine['filtre_trans_ref2']) ?></span>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					</table>
				</div>
				

			</div>
		</div>

		<div class="section" style="margin-top:20px; border-left: 5px solid #007bff;">
			<h4 style="margin-bottom:15px;">🔍 Autres Filtres & Consommables</h4>
			<div class="grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
				
				<div style="font-size: 13px;">
					<strong>🌪️ Air Moteur</strong><br>
					1: <?= htmlspecialchars($machine['filtre_air_moteur_ref1'] ?? '-') ?><br>
					2: <?= htmlspecialchars($machine['filtre_air_moteur_ref2'] ?? '-') ?>
				</div>

				<div style="font-size: 13px;">
					<strong>🍃 Cabine</strong><br>
					1: <?= htmlspecialchars($machine['filtre_air_cabine_ref1'] ?? '-') ?><br>
					2: <?= htmlspecialchars($machine['filtre_air_cabine_ref2'] ?? '-') ?>
				</div>

				<div style="font-size: 13px;">
					<strong>⛽ Carburant</strong><br>
					1: <?= htmlspecialchars($machine['filtre_carb_ref1'] ?? '-') ?><br>
					2: <?= htmlspecialchars($machine['filtre_carb_ref2'] ?? '-') ?><br>
					3: <?= htmlspecialchars($machine['filtre_carb_ref3'] ?? '-') ?>
				</div>

			</div>
		</div>
    <div class="section" style="margin-top:20px;">
        <h3>📜 Historique des interventions</h3>
        <table class="table-mobile">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Compteur</th>
                    <th>Technicien</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($i = $interventions->fetch()): ?>
                <tr>
                    <td data-label="Date"><?= date('d/m/Y', strtotime($i['date_intervention'] ?? 'now')) ?></td>
                    <td data-label="Compteur"><?= htmlspecialchars($i['relevé_compteur'] ?? '0') ?> h</td>
                    <td data-label="Technicien"><?= htmlspecialchars($i['nom_technicien'] ?? 'Inconnu') ?></td>
                    <td data-label="Description">
                        <?= nl2br(htmlspecialchars($i['description'] ?? '')) ?>
                        <?php if (!empty($i['facture_pdf'])): ?>
                            <br><a href="uploads/factures/<?= htmlspecialchars($i['facture_pdf']) ?>" target="_blank" style="font-size:11px; color:#6f42c1; font-weight: bold;">📄 Voir Facture</a>
                        <?php endif; ?>
                    </td>
<td style="text-align:right; white-space:nowrap;">
    <a href="modifier_intervention.php?id=<?= $i['id'] ?>" 
       style="text-decoration:none; margin-right:10px; padding: 5px 10px; border: 1px solid #cbd5e0; border-radius: 6px; background: #fff; color: #4a5568; font-size: 14px;" 
       title="Modifier">✏️ Modifier</a>

    <form method="GET" style="display: inline-flex; align-items: center; gap: 8px; border: 1px solid #feb2b2; background: #fff5f5; padding: 4px 10px; border-radius: 6px; cursor: pointer; transition: 0.2s;">
        
        <input type="hidden" name="id" value="<?= $id_machine ?>">
        <input type="hidden" name="suppr_inter" value="<?= $i['id'] ?>">
        
        <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
            <input type="checkbox" name="confirm_inter" required style="width:14px; height:14px; cursor: pointer;">
        </label>
        
        <button type="submit" style="background:none; border:none; cursor:pointer; padding:0; color: #c53030; font-weight: bold; font-size: 12px; font-family: inherit;">
            🗑️ Supprimer
        </button>
    </form>
</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<div style="margin-top: 50px; border-top: 1px solid #ddd; padding-top: 20px;">
<form method="POST" style="background: #fff5f5; padding: 15px; border: 1px solid #feb2b2; border-radius: 8px;">
    <label style="display: flex; align-items: center; gap: 10px; color: #c53030; font-size: 14px; cursor: pointer;">
        <input type="checkbox" name="confirm_delete" required> 
        Je confirme vouloir supprimer tout l'historique de cette machine.
    </label>
    <br>
    <button type="submit" name="supprimer_materiel" style="background:#dc3545; color:white; border:none; padding:10px; border-radius:6px; cursor:pointer; width:100%;">
        Confirmer la suppression
    </button>
</form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>