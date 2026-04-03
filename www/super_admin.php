<?php
session_start();
require_once 'db.php';

// Sécurité : Seul le super admin peut accéder
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    die("Accès interdit : privilèges insuffisants."); 
}

$message = "";

// --- ACTION : CRÉER UNE ENTREPRISE ---
if (isset($_POST['creer_entreprise'])) {
    $nom = trim($_POST['nouveau_nom']);
    if (!empty($nom)) {
        $stmt = $pdo->prepare("INSERT INTO entreprises (nom) VALUES (?)");
        $stmt->execute([$nom]);
        $message = "<div class='alert success'>✅ Entreprise '$nom' créée.</div>";
    }
}

// --- ACTION : SUPPRIMER UNE ENTREPRISE (Nouveau système sans JS) ---
if (isset($_GET['suppr_ent_id']) && isset($_GET['confirmer_suppr_ent'])) {
    $id_ent = intval($_GET['suppr_ent_id']);
    
    try {
        $pdo->beginTransaction();

        // 1. On nettoie tout ce qui est lié à cette entreprise avant de la supprimer
        // (Note: Si tu as configuré ON DELETE CASCADE en SQL, la suppression de l'entreprise suffit, 
        // sinon ces lignes sont indispensables)
        $pdo->prepare("DELETE FROM interventions WHERE materiel_id IN (SELECT id FROM materiels WHERE entreprise_id = ?)")->execute([$id_ent]);
        $pdo->prepare("DELETE FROM factures WHERE materiel_id IN (SELECT id FROM materiels WHERE entreprise_id = ?)")->execute([$id_ent]);
        $pdo->prepare("DELETE FROM notices WHERE materiel_id IN (SELECT id FROM materiels WHERE entreprise_id = ?)")->execute([$id_ent]);
        $pdo->prepare("DELETE FROM materiels WHERE entreprise_id = ?")->execute([$id_ent]);
        $pdo->prepare("DELETE FROM utilisateurs WHERE entreprise_id = ?")->execute([$id_ent]);
        
        // 2. On supprime l'entreprise elle-même
        $pdo->prepare("DELETE FROM entreprises WHERE id = ?")->execute([$id_ent]);

        $pdo->commit();
        $message = "<div class='alert success'>🗑️ Entreprise et toutes ses données supprimées.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert error'>❌ Erreur lors de la suppression : " . $e->getMessage() . "</div>";
    }
}

// --- CHARGEMENT DES DONNÉES ---
$query = "SELECT e.id, e.nom, 
          (SELECT COUNT(*) FROM materiels WHERE entreprise_id = e.id) as nb_materiels,
          (SELECT COUNT(*) FROM utilisateurs WHERE entreprise_id = e.id) as nb_users
          FROM entreprises e ORDER BY e.nom ASC";
$entreprises = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Console Système - Super Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a202c; color: white; padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        .card { background: #2d3748; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; color: #a0aec0; padding: 12px; border-bottom: 2px solid #4a5568; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #4a5568; font-size: 14px; }
        
        .btn-creer { background: #38a169; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        input[type="text"] { background: #1a202c; border: 1px solid #4a5568; color: white; padding: 10px; border-radius: 5px; width: 300px; }
        
        .link-edit { color: #63b3ed; text-decoration: none; font-weight: bold; }
        .btn-membres { background: #4a5568; color: #e2e8f0; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; display: inline-block; }
        
        /* Style Suppression */
        .suppr-box { display: flex; align-items: center; gap: 8px; }
        .btn-danger { 
            background: none; border: 1px solid #fc8181; color: #fc8181; 
            padding: 5px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; 
        }
        .btn-danger:hover { background: #fc8181; color: white; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .success { background: #2f855a; color: white; }
        .error { background: #c53030; color: white; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" style="color: #63b3ed; text-decoration: none; font-weight: bold;">⬅ Retour à l'accueil</a>
    <h1 style="margin: 20px 0;">Console Système</h1>

    <?= $message ?>

    <div class="card">
        <h3 style="margin-top:0;">➕ Créer une nouvelle entreprise client</h3>
        <form method="POST" style="display: flex; gap: 10px;">
            <input type="text" name="nouveau_nom" placeholder="Nom de l'entreprise (ex: AgriServices 33)..." required>
            <button type="submit" name="creer_entreprise" class="btn-creer">Ajouter au système</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">📂 Liste des clients GMAO</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Équipe</th>
                    <th>Parc</th>
                    <th style="text-align:right;">Zone de Danger</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($entreprises as $ent): ?>
                <tr>
                    <td style="color: #718096;">#<?= $ent['id'] ?></td>
                    <td>
                        <a href="modifier_entreprise.php?id=<?= $ent['id'] ?>" class="link-edit">
                            ✏️ <?= htmlspecialchars($ent['nom']) ?>
                        </a>
                    </td>
                    <td>
                        <a href="voir_membres.php?id_ent=<?= $ent['id'] ?>" class="btn-membres">
                            👥 <?= $ent['nb_users'] ?> membres
                        </a>
                    </td>
                    <td style="color: #a0aec0;">🚜 <?= $ent['nb_materiels'] ?> machines</td>
                    <td style="text-align:right;">
                        <form method="GET" class="suppr-box" style="justify-content: flex-end;">
                            <input type="hidden" name="suppr_ent_id" value="<?= $ent['id'] ?>">
                            
                            <label style="font-size: 10px; color: #fc8181; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                                <input type="checkbox" name="confirmer_suppr_ent" required> Confirmer
                            </label>
                            
                            <button type="submit" class="btn-danger">🗑️ Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>