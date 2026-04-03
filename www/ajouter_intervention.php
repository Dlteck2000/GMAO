<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }
$id_selectionne = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";
$entreprise_id = $_SESSION['entreprise_id'];
$user_id = $_SESSION['user_id'];

// On récupère la liste des machines de l'entreprise pour le menu déroulant
$stmt_m = $pdo->prepare("SELECT id, nom, modele FROM materiels WHERE entreprise_id = ? ORDER BY nom");
$stmt_m->execute([$entreprise_id]);
$materiels = $stmt_m->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $materiel_id = $_POST['materiel_id'];
    $date_int = $_POST['date_intervention'];
    $releve = intval($_POST['releve_compteur']);
    $description = $_POST['description'];
    $est_maintenance = isset($_POST['est_maintenance']) ? 1 : 0;
    
    $nom_facture = null; // Par défaut, pas de facture

    // Si un fichier est fourni, on le traite
	if (!empty($_FILES['facture']['name'])) {
		$dossier = "uploads/factures/";
		if (!file_exists($dossier)) mkdir($dossier, 0775, true);
		
		// On récupère l'extension d'origine (.pdf, .jpg...)
		$ext = pathinfo($_FILES['facture']['name'], PATHINFO_EXTENSION);
		
		// On crée un nom unique : "facture_" + temps actuel + "_" + ID machine
		// Exemple : facture_1710705600_12.pdf
		$nom_facture = "facture_" . time() . "_" . $materiel_id . "." . $ext;
		
		move_uploaded_file($_FILES['facture']['tmp_name'], $dossier . $nom_facture);
	}

    try {
        $pdo->beginTransaction();

        // Insertion : $nom_facture sera soit le nom du fichier, soit NULL
        $sql1 = "INSERT INTO interventions (materiel_id, technicien_id, date_intervention, relevé_compteur, description, facture_pdf) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql1)->execute([$materiel_id, $user_id, $date_int, $releve, $description, $nom_facture]);

        // Mise à jour compteur (identique)
        $sql2 = "UPDATE materiels SET compteur_heures_actuel = ?" . ($est_maintenance ? ", maintenance_dernier_compteur = ?" : "") . " WHERE id = ?";
        $params = $est_maintenance ? [$releve, $releve, $materiel_id] : [$releve, $materiel_id];
        $pdo->prepare($sql2)->execute($params);

        $pdo->commit();
        $message = "<div class='alert success'>✅ Enregistré ! Redirection...</div>";
        header("refresh:0.5;url=index.php");
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
    <title>Nouvelle Intervention - GMAO</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; }
        .container { width: 100%; max-width: 600px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-back { text-decoration: none; color: #666; font-weight: 600; margin-bottom: 20px; display: inline-block; }
        h1 { margin: 0 0 25px 0; font-size: 22px; color: #333; text-align: center; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; margin-bottom: 20px; font-size: 15px; }
        textarea { height: 100px; resize: vertical; }
        
        .maintenance-check { 
            background: #e7f3ff; padding: 15px; border-radius: 8px; border: 1px solid #b3d7ff;
            display: flex; align-items: center; gap: 10px; margin-bottom: 20px; cursor: pointer;
        }
        .maintenance-check input { width: auto; margin-bottom: 0; transform: scale(1.2); }
        
        button { width: 100%; padding: 14px; background: #007bff; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #0056b3; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: 600; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
		/* Style par défaut pour les mobiles */
@media (max-width: 768px) {
    nav {
        flex-direction: column; /* On empile les liens */
        padding: 10px !important;
    }

    nav div {
        display: flex;
        flex-direction: column;
        width: 100%;
        gap: 10px;
    }

    nav a {
        background: #f0f2f5;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin: 0 !important;
    }
}
@media (max-width: 600px) {
    /* On cache l'en-tête du tableau */
    table thead { display: none; }

    table, table tbody, table tr, table td {
        display: block;
        width: 100%;
    }

    table tr {
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: white;
        padding: 10px;
    }

    table td {
        text-align: right;
        padding-left: 50%;
        position: relative;
        border-bottom: 1px solid #eee;
    }

    /* On ajoute le nom de la colonne avant la valeur */
    table td::before {
        content: attr(data-label); /* Utilise l'attribut data-label du TD */
        position: absolute;
        left: 10px;
        width: 45%;
        font-weight: bold;
        text-align: left;
    }
}
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ Retour au parc</a>

    <div class="card">
        <h1>Rapport d'Intervention</h1>
        
        <?= $message ?>

        <form action="" method="POST" enctype="multipart/form-data">
<?php
// On récupère les infos de la machine spécifique pour l'affichage
$machine_nom = "Machine inconnue";
if ($id_selectionne > 0) {
    $st_m = $pdo->prepare("SELECT nom, modele FROM materiels WHERE id = ? AND entreprise_id = ?");
    $st_m->execute([$id_selectionne, $entreprise_id]);
    $m_info = $st_m->fetch();
    if ($m_info) {
        $machine_nom = htmlspecialchars($m_info['nom']) . " " . htmlspecialchars($m_info['modele']);
    } else {
        die("Erreur : Matériel introuvable.");
    }
}
?>

<label>Matériel concerné</label>
<div style="background: #e9ecef; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-weight: bold; color: #333; margin-bottom: 20px;">
    <?= $machine_nom ?>
</div>

<input type="hidden" name="materiel_id" value="<?= $id_selectionne ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label>Date</label>
                    <input type="date" name="date_intervention" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
				<?php
				// Chercher le dernier relevé de cette machine précise pour aider l'utilisateur
				$dernier_releve = 0;
				if ($id_selectionne > 0) {
					$st = $pdo->prepare("SELECT compteur_heures_actuel FROM materiels WHERE id = ?");
					$st->execute([$id_selectionne]);
					$dernier_releve = $st->fetchColumn();
				}
				?>

				<label>Heures au compteur (Dernier connu : <?= $dernier_releve ?>h)</label>
				<input type="number" name="releve_compteur" value="<?= $dernier_releve ?>" required>
                </div>
            </div>

            <label>Description des travaux</label>
            <textarea name="description" placeholder="Détaillez l'intervention (vidange, changement de filtre, réparation...)" required></textarea>

            <label class="maintenance-check">
                <input type="checkbox" name="est_maintenance">
                <span>C'est une <strong>Révision Périodique</strong> (Remet à zéro le cycle de maintenance)</span>
            </label>

            <label>Joindre la facture (PDF ou Image)</label>
            <input type="file" name="facture" accept=".pdf,image/*">

            <button type="submit">Enregistrer l'intervention</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>