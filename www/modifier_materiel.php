<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$message = "";
$entreprise_id = $_SESSION['entreprise_id'];
$id_machine = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. CHARGEMENT DES DONNÉES ACTUELLES
$stmt = $pdo->prepare("SELECT * FROM materiels WHERE id = ? AND entreprise_id = ?");
$stmt->execute([$id_machine, $entreprise_id]);
$m = $stmt->fetch();

if (!$m) { die("Matériel introuvable ou accès refusé."); }

// 2. TRAITEMENT DE LA MISE À JOUR
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "UPDATE materiels SET 
                nom = ?, modele = ?, maintenance_frequence = ?,
                huile_moteur_type = ?, huile_moteur_qte = ?, filtre_moteur_ref = ?,
                huile_trans_type = ?, huile_trans_qte = ?, filtre_trans_ref1 = ?, filtre_trans_ref2 = ?,
                filtre_air_moteur_ref1 = ?, filtre_air_moteur_ref2 = ?,
                filtre_air_cabine_ref1 = ?, filtre_air_cabine_ref2 = ?,
                filtre_carb_ref1 = ?, filtre_carb_ref2 = ?, filtre_carb_ref3 = ?
                WHERE id = ? AND entreprise_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['nom'], $_POST['modele'], $_POST['frequence'],
            $_POST['huile_moteur_type'], $_POST['huile_moteur_qte'], $_POST['filtre_moteur_ref'],
            $_POST['huile_trans_type'], $_POST['huile_trans_qte'], $_POST['filtre_trans_ref1'], $_POST['filtre_trans_ref2'],
            $_POST['filtre_air_moteur_ref1'], $_POST['filtre_air_moteur_ref2'],
            $_POST['filtre_air_cabine_ref1'], $_POST['filtre_air_cabine_ref2'],
            $_POST['filtre_carb_ref1'], $_POST['filtre_carb_ref2'], $_POST['filtre_carb_ref3'],
            $id_machine, $entreprise_id
        ]);

        $message = "<div class='alert success'>✅ Modifications enregistrées !</div>";
        // On recharge les données pour l'affichage
        header("refresh:1;url=fiche_materiel.php?id=$id_machine");
    } catch (Exception $e) {
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier <?= htmlspecialchars($m['nom']) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 700px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h3 { background: #edf2f7; padding: 8px; font-size: 14px; border-radius: 5px; margin-top: 20px; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; font-size: 13px; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .row-tri { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .btn-save { background: #007bff; color: white; border: none; padding: 15px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 20px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>

<div class="container">
    <a href="fiche_materiel.php?id=<?= $id_machine ?>" style="text-decoration:none; color:#666;">⬅ Annuler</a>
    <h1>Modifier la fiche technique</h1>
    
    <?= $message ?>

    <form method="POST">
        <div class="row">
            <div>
                <label>Nom de la machine</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($m['nom']) ?>" required>
            </div>
            <div>
                <label>Modèle</label>
                <input type="text" name="modele" value="<?= htmlspecialchars($m['modele']) ?>">
            </div>
        </div>

        <label>Fréquence entretien (heures)</label>
        <input type="number" name="frequence" value="<?= htmlspecialchars($m['maintenance_frequence']) ?>">

        <h3>🛢️ Huiles & Moteur</h3>
        <div class="row-tri">
            <div>
                <label>Type Huile Moteur</label>
                <input type="text" name="huile_moteur_type" value="<?= htmlspecialchars($m['huile_moteur_type'] ?? '') ?>">
            </div>
            <div>
                <label>Qté Moteur</label>
                <input type="text" name="huile_moteur_qte" value="<?= htmlspecialchars($m['huile_moteur_qte'] ?? '') ?>">
            </div>
            <div>
                <label>Filtre Huile</label>
                <input type="text" name="filtre_moteur_ref" value="<?= htmlspecialchars($m['filtre_moteur_ref'] ?? '') ?>">
            </div>
        </div>

        <h3>⚙️ Transmission</h3>
        <div class="row-tri">
            <div>
                <label>Huile Trans.</label>
                <input type="text" name="huile_trans_type" value="<?= htmlspecialchars($m['huile_trans_type'] ?? '') ?>">
            </div>
            <div>
                <label>Qté Trans.</label>
                <input type="text" name="huile_trans_qte" value="<?= htmlspecialchars($m['huile_trans_qte'] ?? '') ?>">
            </div>
            <div>
                <label>Filtre Trans 1</label>
                <input type="text" name="filtre_trans_ref1" value="<?= htmlspecialchars($m['filtre_trans_ref1'] ?? '') ?>">
            </div>
        </div>
        <label>Filtre Trans 2 (Option)</label>
        <input type="text" name="filtre_trans_ref2" value="<?= htmlspecialchars($m['filtre_trans_ref2'] ?? '') ?>">

        <h3>🌪️ Filtration Air & Cabine</h3>
        <div class="row">
            <div>
                <label>Air Moteur 1</label>
                <input type="text" name="filtre_air_moteur_ref1" value="<?= htmlspecialchars($m['filtre_air_moteur_ref1'] ?? '') ?>">
            </div>
            <div>
                <label>Air Moteur 2</label>
                <input type="text" name="filtre_air_moteur_ref2" value="<?= htmlspecialchars($m['filtre_air_moteur_ref2'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div>
                <label>Air Cabine 1</label>
                <input type="text" name="filtre_air_cabine_ref1" value="<?= htmlspecialchars($m['filtre_air_cabine_ref1'] ?? '') ?>">
            </div>
            <div>
                <label>Air Cabine 2</label>
                <input type="text" name="filtre_air_cabine_ref2" value="<?= htmlspecialchars($m['filtre_air_cabine_ref2'] ?? '') ?>">
            </div>
        </div>

        <h3>⛽ Filtration Carburant</h3>
        <div class="row-tri">
            <div><label>Filtre GNR 1</label><input type="text" name="filtre_carb_ref1" value="<?= htmlspecialchars($m['filtre_carb_ref1'] ?? '') ?>"></div>
            <div><label>Filtre GNR 2</label><input type="text" name="filtre_carb_ref2" value="<?= htmlspecialchars($m['filtre_carb_ref2'] ?? '') ?>"></div>
            <div><label>Filtre GNR 3</label><input type="text" name="filtre_carb_ref3" value="<?= htmlspecialchars($m['filtre_carb_ref3'] ?? '') ?>"></div>
        </div>

        <button type="submit" class="btn-save">💾 Enregistrer les modifications</button>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>