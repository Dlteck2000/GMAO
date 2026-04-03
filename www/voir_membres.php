<?php
session_start();
require_once 'db.php';

// Sécurité : Seul un admin peut accéder à cette page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    die("Accès interdit : privilèges insuffisants."); 
}

$id_ent_url = isset($_GET['id_ent']) ? intval($_GET['id_ent']) : 0;
$message = "";

// --- 1. RÉCUPÉRATION DE TOUTES LES ENTREPRISES ---
$st_all_ent = $pdo->query("SELECT id, nom FROM entreprises ORDER BY nom ASC");
$toutes_entreprises = $st_all_ent->fetchAll();

// --- 2. ACTION : SUPPRIMER UN MEMBRE (Via GET avec sécurité checkbox) ---
if (isset($_GET['suppr_id']) && isset($_GET['confirmer_suppr'])) {
    $id_a_virer = intval($_GET['suppr_id']);
    
    try {
        if ($id_a_virer != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ? AND entreprise_id = ?");
            $stmt->execute([$id_a_virer, $id_ent_url]);
            $message = "<div class='alert success'>✅ Utilisateur supprimé avec succès.</div>";
        } else {
            $message = "<div class='alert error'>❌ Vous ne pouvez pas supprimer votre propre compte.</div>";
        }
    } catch (Exception $e) {
        $message = "<div class='alert error'>❌ Erreur SQL : " . $e->getMessage() . "</div>";
    }
}

// --- 3. ACTION : CRÉER UN MEMBRE (Via POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajouter_membre'])) {
    $nom_membre = $_POST['nom'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); 
    $role = $_POST['role'];
    $id_ent_selectionnee = intval($_POST['entreprise_id']);

    try {
        $pdo->beginTransaction();
        
        $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) { 
            throw new Exception("Cet email est déjà utilisé."); 
        }

        $stmt = $pdo->prepare("INSERT INTO utilisateurs (entreprise_id, nom, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_ent_selectionnee, $nom_membre, $email, $password, $role]);
        
        $pdo->commit();
        $message = "<div class='alert success'>✅ Membre ajouté avec succès !</div>";
        
        // Redirection si l'entreprise a changé
        if($id_ent_selectionnee != $id_ent_url) {
            header("Location: voir_membres.php?id_ent=" . $id_ent_selectionnee);
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert error'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}

// --- 4. DONNÉES D'AFFICHAGE ---
$st_e = $pdo->prepare("SELECT nom FROM entreprises WHERE id = ?");
$st_e->execute([$id_ent_url]);
$nom_ent_actuelle = $st_e->fetchColumn() ?: "Inconnue";

$st_u = $pdo->prepare("SELECT * FROM utilisateurs WHERE entreprise_id = ? ORDER BY role, nom");
$st_u->execute([$id_ent_url]);
$membres = $st_u->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Membres - <?= htmlspecialchars($nom_ent_actuelle) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a202c; color: white; padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        .card { background: #2d3748; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1.2fr 120px 100px; gap: 10px; align-items: end; }
        input, select { background: #1a202c; border: 1px solid #4a5568; color: white; padding: 10px; border-radius: 5px; width: 100%; box-sizing: border-box; }
        label { display: block; font-size: 11px; color: #a0aec0; margin-bottom: 5px; text-transform: uppercase; }
        
        .btn-add { background: #3182ce; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-add:hover { background: #2b6cb0; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; border-bottom: 2px solid #4a5568; padding: 12px; color: #a0aec0; }
        td { padding: 12px; border-bottom: 1px solid #4a5568; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #4a5568; }
        .badge-admin { background: #805ad5; }
        
        /* Style Bouton Suppression Directe */
        .suppr-container { display: flex; align-items: center; gap: 8px; justify-content: flex-end; }
        .btn-suppr { 
            background: transparent; border: 1px solid #fc8181; color: #fc8181; 
            padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; transition: 0.2s;
        }
        .btn-suppr:hover { background: #fc8181; color: white; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: #2f855a; color: white; }
        .error { background: #c53030; color: white; }

        @media (max-width: 900px) { .form-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <a href="super_admin.php" style="color: #63b3ed; text-decoration: none;">⬅ Retour Super Admin</a>
    
    <h2>Membres : <span style="color:#63b3ed;"><?= htmlspecialchars($nom_ent_actuelle) ?></span></h2>

    <?= $message ?>

    <div class="card">
        <form method="POST" class="form-grid">
            <div><label>Nom</label><input type="text" name="nom" required></div>
            <div><label>Email</label><input type="email" name="email" required></div>
            <div><label>Pass</label><input type="password" name="password" required></div>
            <div>
                <label>Entreprise</label>
                <select name="entreprise_id">
                    <?php foreach($toutes_entreprises as $ent): ?>
                        <option value="<?= $ent['id'] ?>" <?= ($ent['id'] == $id_ent_url) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ent['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Rôle</label>
                <select name="role">
                    <option value="technicien">Technicien</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="ajouter_membre" class="btn-add">Ajouter</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th style="text-align:right;">Action de sécurité</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($membres as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge <?= ($u['role'] == 'admin') ? 'badge-admin' : '' ?>"><?= strtoupper($u['role']) ?></span></td>
                    <td style="text-align:right;">
                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <form method="GET" class="suppr-container">
                                <input type="hidden" name="id_ent" value="<?= $id_ent_url ?>">
                                <input type="hidden" name="suppr_id" value="<?= $u['id'] ?>">
                                
                                <label style="font-size: 10px; color: #fc8181; text-transform: none; display: flex; align-items: center; gap: 4px; cursor: pointer; margin-bottom:0;">
                                    <input type="checkbox" name="confirmer_suppr" required style="width: 14px; height: 14px;"> 
                                    Confirmer
                                </label>
                                
                                <button type="submit" class="btn-suppr">Supprimer</button>
                            </form>
                        <?php else: ?>
                            <small style="color:#718096; font-style: italic;">(Moi)</small>
                        <?php endif; ?>
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