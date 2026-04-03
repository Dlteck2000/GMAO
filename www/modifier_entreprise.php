<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Accès interdit"); }

$id_ent = intval($_GET['id']);
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nouveau_nom = $_POST['nom_entreprise'];
    $stmt = $pdo->prepare("UPDATE entreprises SET nom = ? WHERE id = ?");
    $stmt->execute([$nouveau_nom, $id_ent]);
    $message = "<div style='color:green'>Nom mis à jour !</div>";
    header("refresh:1;url=super_admin.php");
}

$stmt = $pdo->prepare("SELECT * FROM entreprises WHERE id = ?");
$stmt->execute([$id_ent]);
$ent = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renommer Entreprise</title>
    <style>
        body { font-family: sans-serif; background: #1a202c; color: white; display: flex; justify-content: center; padding-top: 50px; }
        .card { background: #2d3748; padding: 20px; border-radius: 8px; width: 300px; }
        input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #3182ce; color: white; border: none; cursor: pointer; }
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
    </style>
</head>
<body>
    <div class="card">
        <h3>Modifier le nom</h3>
        <?= $message ?>
        <form method="POST">
            <input type="text" name="nom_entreprise" value="<?= htmlspecialchars($ent['nom']) ?>" required>
            <button type="submit">Enregistrer</button>
        </form>
        <br>
        <a href="super_admin.php" style="color: #a0aec0;">Annuler</a>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>