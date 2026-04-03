<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$entreprise_id = $_SESSION['entreprise_id'];
$id_int = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";

// 1. Récupération des données de l'intervention avec vérification de sécurité
$stmt = $pdo->prepare("
    SELECT i.*, m.nom as machine_nom, m.modele as machine_modele 
    FROM interventions i 
    JOIN materiels m ON i.materiel_id = m.id 
    WHERE i.id = ? AND m.entreprise_id = ?
");
$stmt->execute([$id_int, $entreprise_id]);
$int = $stmt->fetch();

if (!$int) {
    die("Intervention introuvable ou accès refusé.");
}

// 2. Traitement de la modification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date_intervention'];
    $releve = intval($_POST['releve_compteur']);
    $description = $_POST['description'];
    
    try {
        $up = $pdo->prepare("UPDATE interventions SET date_intervention = ?, relevé_compteur = ?, description = ? WHERE id = ?");
        $up->execute([$date, $releve, $description, $id_int]);
        
        $message = "<div class='alert success'>✅ Modification enregistrée ! Redirection...</div>";
        header("refresh:0.5;url=fiche_materiel.php?id=" . $int['materiel_id']);
    } catch (Exception $e) {
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'intervention - GMAO</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; }
        .container { width: 100%; max-width: 600px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-back { text-decoration: none; color: #666; font-weight: 600; margin-bottom: 20px; display: inline-block; }
        h1 { margin: 0 0 10px 0; font-size: 22px; color: #333; text-align: center; }
        .machine-badge { text-align: center; color: #007bff; font-weight: bold; margin-bottom: 25px; display: block; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        input, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; margin-bottom: 20px; font-size: 15px; }
        textarea { height: 120px; }
        button { width: 100%; padding: 14px; background: #007bff; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
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
    <a href="fiche_materiel.php?id=<?= $int['materiel_id'] ?>" class="btn-back">⬅ Annuler et retourner</a>

    <div class="card">
        <h1>Modifier l'intervention</h1>
        <span class="machine-badge"><?= htmlspecialchars($int['machine_nom']) ?> (<?= htmlspecialchars($int['machine_modele']) ?>)</span>
        
        <?= $message ?>

        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label>Date</label>
                    <input type="date" name="date_intervention" value="<?= $int['date_intervention'] ?>" required>
                </div>
                <div>
                    <label>Compteur (h)</label>
                    <input type="number" name="releve_compteur" value="<?= $int['relevé_compteur'] ?>" required>
                </div>
            </div>

            <label>Description des travaux</label>
            <textarea name="description" required><?= htmlspecialchars($int['description']) ?></textarea>

            <button type="submit">Mettre à jour le rapport</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>