<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Accès réservé à l'administrateur.");
}

$message = "";
$status = "";

// --- 1. CONFIGURATION ---
$base_path = dirname(__FILE__) . DIRECTORY_SEPARATOR;
$db_name = 'database_gmao.sqlite'; 
$source = $base_path . $db_name;
$backup_dir = $base_path . 'backups' . DIRECTORY_SEPARATOR;

if (!is_dir($backup_dir)) { mkdir($backup_dir, 0777, true); }

// --- 2. ACTION : SAUVEGARDE ---
if (isset($_POST['btn_sauvegarder'])) {
    if (file_exists($source)) {
        $filename = 'backup_' . date('Ymd_His') . '.sqlite';
        $dest = $backup_dir . $filename;
        $f_src = @fopen($source, 'rb');
        $f_dst = @fopen($dest, 'wb');
        if ($f_src && $f_dst) {
            while (!feof($f_src)) { fwrite($f_dst, fread($f_src, 8192)); }
            fclose($f_src); fclose($f_dst);
            $message = "✅ Sauvegarde créée : $filename";
            $status = "success";
        }
    }
}

// --- 3. ACTION : FUSION INTELLIGENTE ---
if (isset($_POST['btn_restaurer'])) {
    $file_to_restore = $_POST['fichier_restauration'];
    $path_to_restore = $backup_dir . $file_to_restore;

    if (!empty($file_to_restore) && file_exists($path_to_restore)) {
        try {
            $db_back = new PDO("sqlite:" . $path_to_restore);
            $tables = $db_back->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            $lignes = 0;

            foreach ($tables as $table) {
                $create_sql = $db_back->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
                $pdo->exec($create_sql); // Crée la table si absente

                $rows = $db_back->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $cols = implode(',', array_keys($row));
                    $places = implode(',', array_fill(0, count($row), '?'));
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO $table ($cols) VALUES ($places)");
                    if ($stmt->execute(array_values($row)) && $stmt->rowCount() > 0) $lignes++;
                }
            }
            $message = "✅ Fusion terminée : $lignes nouvelles entrées ajoutées.";
            $status = "success";
        } catch (Exception $e) { $message = "❌ Erreur : " . $e->getMessage(); $status = "error"; }
    }
}

// --- 4. ACTION : NETTOYAGE (SUPPRESSION DES BACKUPS) ---
if (isset($_POST['btn_nettoyer'])) {
    $files = glob($backup_dir . "*.sqlite");
    $count = 0;
    foreach ($files as $f) {
        if (is_file($f)) { unlink($f); $count++; }
    }
    $message = "🗑️ Nettoyage fini : $count fichiers supprimés.";
    $status = "success";
}
// --- 5. ACTION : REMISE À ZÉRO AVEC SAUVEGARDE AUTOMATIQUE ---
if (isset($_POST['btn_confirm_reset'])) {
    try {
        // A. SÉCURITÉ : SAUVEGARDE AUTOMATIQUE AVANT EFFACEMENT
        $filename_auto = 'AUTO_BACKUP_AVANT_RESET_' . date('Ymd_His') . '.sqlite';
        $dest_auto = $backup_dir . $filename_auto;
        
        $f_src = @fopen($source, 'rb');
        $f_dst = @fopen($dest_auto, 'wb');
        if ($f_src && $f_dst) {
            while (!feof($f_src)) { fwrite($f_dst, fread($f_src, 8192)); }
            fclose($f_src); fclose($f_dst);
        }

        // B. VIDAGE DES TABLES
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Liste des tables à NE PAS VIDER (à adapter selon ta structure)
            if ($table !== 'utilisateurs' && $table !== 'entreprises') {
                $pdo->exec("DELETE FROM $table");
                // Reset des compteurs d'ID
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
            }
        }
        
        $message = "🔥 Base vidée ! Une sauvegarde de secours a été créée par sécurité : $filename_auto";
        $status = "success";
    } catch (Exception $e) {
        $message = "❌ Erreur lors du reset : " . $e->getMessage();
        $status = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Base</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
        .card { max-width: 600px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .section { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .msg { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 5px; }
        .btn-blue { background: #3182ce; color: white; }
        .btn-green { background: #38a169; color: white; }
        .btn-red { background: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; }
        select { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #cbd5e0; }
    </style>
</head>
<body>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>⚙️ Maintenance</h2>
        <a href="index.php" style="text-decoration:none; color:#3182ce; font-weight:bold;">🏠 Accueil</a>
    </div>

    <?php if ($message): ?>
        <div class="msg <?= $status ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="section">
        <h3 style="margin-top:0;">1. Sauvegarde</h3>
        <form method="POST">
            <button type="submit" name="btn_sauvegarder" class="btn btn-blue">📂 Créer une sauvegarde</button>
        </form>
    </div>

    <div class="section">
        <h3 style="margin-top:0;">2. Restauration / Fusion</h3>
        <form method="POST">
            <select name="fichier_restauration" required>
                <option value="">-- Sélectionner un fichier --</option>
                <?php 
                $files = glob($backup_dir . "*.sqlite");
                if ($files) { rsort($files); foreach ($files as $f) { $n = basename($f); echo "<option value='$n'>$n</option>"; } }
                ?>
            </select>
            <button type="submit" name="btn_restaurer" class="btn btn-green">🔄 Fusionner les données</button>
        </form>
    </div>

    <div class="section" style="background:#fff5f5;">
<div class="section" style="background:#fff5f5; border-color: #feb2b2;">
    <h3 style="margin-top:0; color:#c53030;">3. Nettoyage du dossier Backups</h3>
    
    <?php if(!isset($_GET['ask_delete'])): ?>
        <p style="font-size:12px; color:#666;">Supprime définitivement tous les fichiers du dossier backups.</p>
        <a href="sauvegarde.php?ask_delete=1" class="btn btn-red" style="text-decoration:none;">🗑️ Vider le dossier backups</a>
    <?php else: ?>
        <div style="background:#fff; padding:10px; border-radius:5px; border:1px solid #e53e3e; text-align:center;">
            <p style="color:#c53030; font-weight:bold; margin-top:0;">⚠️ Êtes-vous vraiment sûr ?</p>
            <form method="POST" style="display: flex; gap: 10px;">
                <button type="submit" name="btn_nettoyer" class="btn" style="background:#e53e3e; color:white; flex:2;">OUI, TOUT SUPPRIMER</button>
                <a href="sauvegarde.php" class="btn" style="background:#edf2f7; color:#4a5568; text-decoration:none; flex:1; line-height:35px;">ANNULER</a>
            </form>
        </div>
    <?php endif; ?>
</div>
    </div>
<div class="section" style="background:#fff5f5; border: 2px solid #feb2b2;">
    <h3 style="margin-top:0; color:#c53030;">☢️ Zone de Danger : Remise à zéro</h3>
    <p style="font-size:12px; color:#666;">Ceci supprimera TOUS les matériels, interventions et stocks. Vos comptes accès resteront actifs.</p>

    <?php if(!isset($_GET['ask_reset'])): ?>
        <a href="sauvegarde.php?ask_reset=1" class="btn btn-red" style="text-decoration:none; text-align:center;">🔥 Vider toute la base</a>
    <?php else: ?>
        <div style="background:white; padding:15px; border-radius:8px; border:1px solid #e53e3e; text-align:center;">
            <p style="color:#e53e3e; font-weight:bold; margin-top:0;">⚠️ CONFIRMATION FINALE</p>
            <p style="font-size:11px;">Êtes-vous certain de vouloir tout effacer ? Cette action est irréversible.</p>
            <form method="POST" style="display: flex; gap: 10px; margin-top:10px;">
                <button type="submit" name="btn_confirm_reset" class="btn" style="background:#e53e3e; color:white; flex:2;">OUI, TOUT EFFACER</button>
                <a href="sauvegarde.php" class="btn" style="background:#edf2f7; color:#4a5568; text-decoration:none; flex:1; line-height:35px; font-size:14px;">ANNULER</a>
            </form>
        </div>
    <?php endif; ?>
</div>
</div>

</body>
</html>