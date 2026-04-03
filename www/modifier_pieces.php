<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) { header('Location: login.php'); exit; }

$id_machine = isset($_GET['id_mat']) ? intval($_GET['id_mat']) : 0;
$message = "";
$redirect = false; // Variable pour déclencher la redirection JS

// 1. SUPPRIMER UNE PIÈCE
if (isset($_GET['suppr'])) {
    $id_p = intval($_GET['suppr']);
    $stmt = $pdo->prepare("DELETE FROM pieces_detachees WHERE id = ? AND materiel_id = ?");
    $stmt->execute([$id_p, $id_machine]);
    $message = "<div class='alert success'>✅ Pièce supprimée.</div>";
}

// 2. AJOUTER UNE PIÈCE
if (isset($_POST['ajouter_piece'])) {
    $nom = trim($_POST['nom_piece']);
    $ref = trim($_POST['reference_piece']);
    if (!empty($nom) && !empty($ref)) {
        $stmt = $pdo->prepare("INSERT INTO pieces_detachees (materiel_id, nom_piece, reference) VALUES (?, ?, ?)");
        $stmt->execute([$id_machine, $nom, $ref]);
        $message = "<div class='alert success'>✅ Pièce ajoutée ! Redirection...</div>";
        $redirect = true; // On active la redirection
    }
}

// 3. MODIFIER UNE PIÈCE (Mise à jour via la disquette)
if (isset($_POST['update_piece'])) {
    $id_p = intval($_POST['piece_id']);
    $nom = trim($_POST['nom_piece']);
    $ref = trim($_POST['reference_piece']);
    
    $stmt = $pdo->prepare("UPDATE pieces_detachees SET nom_piece = ?, reference = ? WHERE id = ? AND materiel_id = ?");
    $stmt->execute([$nom, $ref, $id_p, $id_machine]);
    
    $message = "<div class='alert success'>💾 Modification enregistrée ! Redirection...</div>";
    $redirect = true; // On active la redirection
}

// Récupération des données pour l'affichage
$mat = $pdo->prepare("SELECT nom FROM materiels WHERE id = ?");
$mat->execute([$id_machine]);
$nom_machine = $mat->fetchColumn();

$pieces = $pdo->prepare("SELECT * FROM pieces_detachees WHERE materiel_id = ? ORDER BY nom_piece");
$pieces->execute([$id_machine]);
$liste = $pieces->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gérer les pièces - <?= htmlspecialchars($nom_machine) ?></title>
    <?php if ($redirect): ?>
        <meta http-equiv="refresh" content="1;url=fiche_materiel.php?id=<?= $id_machine ?>">
    <?php endif; ?>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; color: #1a202c; }
        .container { max-width: 800px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { font-size: 22px; color: #2d3748; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td, th { padding: 12px; border-bottom: 1px solid #edf2f7; }
        input[type="text"] { padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; width: 95%; font-size: 14px; }
        .btn { padding: 10px 15px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-blue { background: #3182ce; color: white; }
        .btn-green { background: #38a169; color: white; }
        .btn-red { background: #e53e3e; color: white; margin-left: 5px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: bold; }
        .success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="fiche_materiel.php?id=<?= $id_machine ?>" style="text-decoration:none; color:#4a5568; font-weight: bold;">⬅ Annuler / Retour</a>
    </div>

    <h1>Gestion des pièces : <?= htmlspecialchars($nom_machine) ?></h1>
    
    <?= $message ?>

    <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #e2e8f0;">
        <h3 style="margin-top:0; font-size: 16px;">➕ Ajouter une référence</h3>
        <form method="POST" style="display: flex; gap: 10px;">
            <input type="text" name="nom_piece" placeholder="Désignation (ex: Courroie)" required>
            <input type="text" name="reference_piece" placeholder="Référence" required>
            <button type="submit" name="ajouter_piece" class="btn btn-green">Ajouter</button>
        </form>
    </div>

    <h3>Références enregistrées</h3>
    <table>
        <thead>
            <tr style="text-align: left; color: #718096; font-size: 13px; text-transform: uppercase;">
                <th>Désignation</th>
                <th>Référence</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($liste as $p): ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="piece_id" value="<?= $p['id'] ?>">
                    <td><input type="text" name="nom_piece" value="<?= htmlspecialchars($p['nom_piece']) ?>"></td>
                    <td><input type="text" name="reference_piece" value="<?= htmlspecialchars($p['reference']) ?>"></td>
                    <td style="text-align: right; white-space: nowrap;">
                        <button type="submit" name="update_piece" class="btn btn-blue" title="Sauvegarder">💾</button>
                        <a href="modifier_pieces.php?id_mat=<?= $id_machine ?>&suppr=<?= $p['id'] ?>" 
                           class="btn btn-red" 
                           title="Supprimer">🗑️</a>
                    </td>
                </form>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($liste)): ?>
                <tr><td colspan="3" style="text-align:center; color:#a0aec0; padding:40px;">Aucune pièce spécifique pour ce matériel.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
</body>
</html>