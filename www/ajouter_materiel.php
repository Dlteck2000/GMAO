<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) {
    header('Location: login.php');
    exit;
}

$message = "";
$entreprise_id = $_SESSION['entreprise_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des nouveaux champs
    $nom = $_POST['nom'];
    $modele = $_POST['modele'];
    $compteur = $_POST['compteur'];
    $frequence = $_POST['frequence'];
    
    $h_mot_type = $_POST['huile_moteur_type'] ?? '';
    $h_mot_qte  = $_POST['huile_moteur_qte'] ?? '';
    $f_mot_ref  = $_POST['filtre_moteur_ref'] ?? '';
    $h_tra_type = $_POST['huile_trans_type'] ?? '';
    $h_tra_qte  = $_POST['huile_trans_qte'] ?? '';
    $f_tra_ref1 = $_POST['filtre_trans_ref1'] ?? '';
    $f_tra_ref2 = $_POST['filtre_trans_ref2'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. Insertion du matériel avec les nouveaux champs
		$sql = "INSERT INTO materiels (
			nom, modele, compteur_heures_actuel, maintenance_frequence, entreprise_id,
			huile_moteur_type, huile_moteur_qte, filtre_moteur_ref,
			huile_trans_type, huile_trans_qte, filtre_trans_ref1, filtre_trans_ref2,
			filtre_air_moteur_ref1, filtre_air_moteur_ref2,
			filtre_air_cabine_ref1, filtre_air_cabine_ref2,
			filtre_carb_ref1, filtre_carb_ref2, filtre_carb_ref3
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
		$stmt->execute([
			$_POST['nom'], $_POST['modele'], $_POST['compteur'], $_POST['frequence'], $entreprise_id,
			$_POST['huile_moteur_type'], $_POST['huile_moteur_qte'], $_POST['filtre_moteur_ref'],
			$_POST['huile_trans_type'], $_POST['huile_trans_qte'], $_POST['filtre_trans_ref1'], $_POST['filtre_trans_ref2'],
			$_POST['filtre_air_moteur_ref1'], $_POST['filtre_air_moteur_ref2'],
			$_POST['filtre_air_cabine_ref1'], $_POST['filtre_air_cabine_ref2'],
			$_POST['filtre_carb_ref1'], $_POST['filtre_carb_ref2'], $_POST['filtre_carb_ref3']
		]);
        
        $materiel_id = $pdo->lastInsertId();

        // 2. Gestion du PDF
        if (!empty($_FILES['notice']['name'])) {
            $dossier_notices = "uploads/notices/";
            if (!is_dir($dossier_notices)) mkdir($dossier_notices, 0777, true);
            
            $ext = pathinfo($_FILES['notice']['name'], PATHINFO_EXTENSION);
            $nom_fichier_unique = "notice_" . time() . "_" . $materiel_id . "." . $ext;
            
            if (move_uploaded_file($_FILES['notice']['tmp_name'], $dossier_notices . $nom_fichier_unique)) {
                $stmt_n = $pdo->prepare("INSERT INTO notices (materiel_id, titre, fichier_path) VALUES (?, ?, ?)");
                $stmt_n->execute([$materiel_id, "Notice " . $nom, $nom_fichier_unique]);
            }
        }

        $pdo->commit();
        $message = "<div class='alert success'>✅ Matériel ajouté avec ses infos techniques !</div>";
        header("refresh:1;url=index.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter du Matériel - GMAO</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .container { width: 100%; max-width: 600px; margin: auto; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; text-align: center; margin-bottom: 20px; color: #1a202c; }
        h3 { font-size: 14px; text-transform: uppercase; color: #718096; border-bottom: 1px solid #edf2f7; padding-bottom: 5px; margin: 20px 0 15px 0; }
        
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #4a5568; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; margin-bottom: 15px; font-size: 14px; }
        
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .row-tri { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        
        button { width: 100%; padding: 14px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-back { text-decoration: none; color: #666; font-weight: 600; display: block; margin-bottom: 15px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Retour</a>

    <div class="card">
        <h1>Nouvel Équipement</h1>
        <?= $message ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <h3>Informations Générales</h3>
            <label>Nom de la machine</label>
            <input type="text" name="nom" required placeholder="ex: John Deere 6155R">
            
            <label>Modèle / Série</label>
            <input type="text" name="modele" placeholder="ex: Série 6R - 2024">
            
            <div class="row">
                <div>
                    <label>Compteur initial (h)</label>
                    <input type="number" name="compteur" value="0">
                </div>
                <div>
                    <label>Entretien tous les (h)</label>
                    <input type="number" name="frequence" value="500">
                </div>
            </div>

            <h3>🛢️ Entretien Moteur</h3>
            <div class="row-tri">
                <div>
                    <label>Type Huile</label>
                    <input type="text" name="huile_moteur_type" placeholder="10W40">
                </div>
                <div>
                    <label>Quantité</label>
                    <input type="text" name="huile_moteur_qte" placeholder="15L">
                </div>
                <div>
                    <label>Réf. Filtre</label>
                    <input type="text" name="filtre_moteur_ref" placeholder="RE504836">
                </div>
            </div>

            <h3>⚙️ Transmission & Hydraulique</h3>
            <div class="row">
                <div>
                    <label>Type Huile</label>
                    <input type="text" name="huile_trans_type" placeholder="Hy-Gard">
                </div>
                <div>
                    <label>Quantité</label>
                    <input type="text" name="huile_trans_qte" placeholder="55L">
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Filtre Trans. 1</label>
                    <input type="text" name="filtre_trans_ref1" placeholder="AL203060">
                </div>
                <div>
                    <label>Filtre Trans. 2 (Option)</label>
                    <input type="text" name="filtre_trans_ref2" placeholder="AL203061">
                </div>
            </div>
			
			<style>
				.filter-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
				.filter-grid-tri { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 15px; }
			</style>

			<h3>🌪️ Filtration Air Moteur</h3>
			<div class="filter-grid">
				<div>
					<label>Filtre Air Principal</label>
					<input type="text" name="filtre_air_moteur_ref1" placeholder="Réf. Primaire">
				</div>
				<div>
					<label>Filtre Air Sécurité</label>
					<input type="text" name="filtre_air_moteur_ref2" placeholder="Réf. Sécurité">
				</div>
			</div>

			<h3>🍃 Filtration Cabine (Clim)</h3>
			<div class="filter-grid">
				<div>
					<label>Filtre Cabine n°1</label>
					<input type="text" name="filtre_air_cabine_ref1" placeholder="Poussière / Charbon">
				</div>
				<div>
					<label>Filtre Cabine n°2</label>
					<input type="text" name="filtre_air_cabine_ref2" placeholder="Recyclage">
				</div>
			</div>

			<h3>⛽ Filtration Carburant (GNR)</h3>
			<div class="filter-grid-tri">
				<div>
					<label>Pré-filtre (Décanteur)</label>
					<input type="text" name="filtre_carb_ref1" placeholder="Réf 1">
				</div>
				<div>
					<label>Filtre Principal</label>
					<input type="text" name="filtre_carb_ref2" placeholder="Réf 2">
				</div>
				<div>
					<label>Filtre Sécurité/Final</label>
					<input type="text" name="filtre_carb_ref3" placeholder="Réf 3">
				</div>
			</div>

            <h3>📁 Document</h3>
            <label>Notice Technique (PDF)</label>
            <input type="file" name="notice" accept=".pdf">
            
            <button type="submit">Enregistrer le matériel</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>