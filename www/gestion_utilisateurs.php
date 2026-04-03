<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id']) || $_SESSION['role'] !== 'admin') {
    die("Accès réservé aux administrateurs.");
}

$ent_id = $_SESSION['entreprise_id'];
$mon_id = $_SESSION['user_id'];
$message = "";

// --- Action : Suppression d'un utilisateur ---
if (isset($_GET['suppr_user'])) {
    $id_a_suppr = intval($_GET['suppr_user']);
    if ($id_a_suppr !== $mon_id) {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ? AND entreprise_id = ?");
        $stmt->execute([$id_a_suppr, $ent_id]);
        $message = "<div class='alert success'>Utilisateur supprimé.</div>";
    } else {
        $message = "<div class='alert error'>Vous ne pouvez pas vous supprimer vous-même !</div>";
    }
}

// Récupération de l'équipe
$membres = $pdo->prepare("SELECT * FROM utilisateurs WHERE entreprise_id = ?");
$membres->execute([$ent_id]);
$equipe = $membres->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Équipe</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 30px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); max-width: 800px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #eee; color: #666; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .btn-suppr { color: #dc3545; text-decoration: none; font-weight: bold; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
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
	table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; } /* Cache le header proprement */
            
            tr { border: 1px solid #ccc; border-radius: 10px; margin-bottom: 20px; padding: 10px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
            
            td { border: none; border-bottom: 1px solid #eee; position: relative; padding-left: 45% !important; text-align: left; min-height: 30px; }
            td:last-child { border-bottom: 0; }
            
            /* Ajout des étiquettes avant chaque donnée */
            td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: 40%;
                font-weight: bold;
                color: #666;
                font-size: 12px;
                text-transform: uppercase;
            }
}
    </style>
</head>
<body>
    <div class="card">
        <a href="index.php" style="text-decoration:none;">⬅ Retour</a>
        <h1>Gestion de l'équipe</h1>
        <?= $message ?>
        
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($equipe as $u): ?>
                <tr>
                    <td data-label="nom"><?= htmlspecialchars($u['nom']) ?></td>
                    <td data-label="email"><?= htmlspecialchars($u['email']) ?></td>
                    <td data-label="rôle"><strong><?= $u['role'] ?></strong></td>
                    <td data-label="">
                        <?php if($u['id'] !== $mon_id): ?>
                            <a href="?suppr_user=<?= $u['id'] ?>" class="btn-suppr" onclick="return confirm('Supprimer cet utilisateur ?')">Supprimer</a>
                        <?php else: ?>
                            <span style="color:#ccc;">(Moi)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>