<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['entreprise_id'])) {
    header('Location: login.php');
    exit;
}

$entreprise_id = $_SESSION['entreprise_id'];
$id_machine = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";

// --- 1. ACTION : SUPPRESSION ---
if (isset($_GET['delete_id']) && isset($_GET['confirmer_suppr'])) {
    $id_a_suppr = intval($_GET['delete_id']);
    $stmt = $pdo->prepare("DELETE FROM gps_data WHERE id = ? AND entreprise_id = ?");
    $stmt->execute([$id_a_suppr, $entreprise_id]);
    header("Location: gps_manuel.php?id=$id_machine&msg=deleted"); 
    exit;
}

// --- 2. ACTION : ENREGISTREMENT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_gps'])) {
    $type = $_POST['type'];
    $nom = $_POST['nom_gps'] ?: ($type . " " . date('d/m H:i'));
    
    if ($type === 'POINT') {
        $data = ['latA' => $_POST['latA'], 'lngA' => $_POST['lngA']];
    } else {
        $data = [
            'latA' => $_POST['latA'], 'lngA' => $_POST['lngA'], 
            'latB' => $_POST['latB'], 'lngB' => $_POST['lngB']
        ];
    }

    $stmt = $pdo->prepare("INSERT INTO gps_data (entreprise_id, materiel_id, nom, type_objet, donnees_gps) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$entreprise_id, $id_machine, $nom, $type, json_encode($data)]);
    header("Location: gps_manuel.php?id=$id_machine&msg=saved"); 
    exit;
}

// --- 3. RÉCUPÉRATION ---
$stmt = $pdo->prepare("SELECT * FROM gps_data WHERE entreprise_id = ? AND materiel_id = ? ORDER BY id DESC");
$stmt->execute([$entreprise_id, $id_machine]);
$historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(isset($_GET['msg'])) {
    if($_GET['msg'] == 'deleted') $message = "<div style='color:#e53e3e; font-weight:bold; margin-bottom:10px;'>🗑️ Tracé supprimé.</div>";
    if($_GET['msg'] == 'saved') $message = "<div style='color:#38a169; font-weight:bold; margin-bottom:10px;'>✅ Tracé enregistré.</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>GPS - <?= $_SESSION['entreprise_nom'] ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 15px; margin: 0; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .form-group { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        label { grid-column: span 2; font-weight: bold; margin-bottom: 5px; font-size: 13px; }
        input, select { padding: 12px; border: 1px solid #ccc; border-radius: 8px; width: 100%; box-sizing: border-box; }
        .btn { padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #007bff; color: white; width: 100%; }
        .btn-danger-ui { background: transparent; border: 1px solid #dc3545; color: #dc3545; padding: 5px 10px; border-radius: 5px; font-size: 12px; cursor: pointer; }
        .btn-danger-ui:hover { background: #dc3545; color: white; }
        #map { height: 400px; border-radius: 10px; margin: 20px 0; border: 1px solid #ddd; }
        .card-gps { background: #f8f9fa; padding: 12px; border-radius: 8px; margin-top: 10px; border-left: 5px solid #007bff; display: flex; justify-content: space-between; align-items: center; }
        .coords-list { font-family: monospace; font-size: 12px; color: #4a5568; background: #edf2f7; padding: 4px 8px; border-radius: 4px; display: inline-block; margin-top: 5px; }
        .btn-focus { text-decoration: none; font-size: 18px; margin-left: 10px; vertical-align: middle; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" style="text-decoration:none; color:#007bff;">⬅ Retour Fiche Matériel</a>
    <h2 style="margin-top:15px;">📍 Gestion des tracés GPS</h2>

    <?= $message ?>

    <form method="POST">
        <label>Nom du tracé</label>
        <input type="text" name="nom_gps" placeholder="Ex: Bordure Nord, Ligne Semis..." style="margin-bottom:15px;">

        <div class="form-group">
            <label>Type de repère</label>
            <select name="type" id="typeSelect" onchange="toggleInputs()">
                <option value="POINT">📍 Point Unique</option>
                <option value="LIGNE">📏 Ligne AB</option>
            </select>
        </div>

        <div class="form-group">
            <label>Coordonnées Point A</label>
            <input type="number" step="0.0000000001" name="latA" placeholder="Lat A" required>
            <input type="number" step="0.0000000001" name="lngA" placeholder="Lng A" required>
        </div>

        <div id="groupB" class="form-group" style="display:none;">
            <label>Coordonnées Point B</label>
            <input type="number" step="0.0000000001" name="latB" placeholder="Lat B">
            <input type="number" step="0.0000000001" name="lngB" placeholder="Lng B">
        </div>

        <button type="submit" name="save_gps" class="btn btn-primary">💾 Enregistrer le tracé</button>
    </form>

    <div id="map"></div>

    <h3>📜 Historique des relevés</h3>
    <?php foreach($historique as $h): 
        $coords = json_decode($h['donnees_gps'], true); 
    ?>
        <div class="card-gps">
            <div>
                <strong><?= htmlspecialchars($h['nom']) ?></strong>
                <a href="#" onclick="focusElement(<?= $h['id'] ?>); return false;" class="btn-focus" title="Centrer sur la carte">🔍</a>
                <br>
                <span style="font-size:11px; background:#e2e8f0; padding:2px 5px; border-radius:4px;"><?= $h['type_objet'] ?></span><br>
                
                <div class="coords-list">
                    <?php if($h['type_objet'] === 'POINT'): ?>
                        A: <?= $coords['latA'] ?>, <?= $coords['lngA'] ?>
                    <?php else: ?>
                        A: <?= $coords['latA'] ?>, <?= $coords['lngA'] ?> | B: <?= $coords['latB'] ?>, <?= $coords['lngB'] ?>
                    <?php endif; ?>
                </div><br>
                
                <small style="color:#718096;">📅 <?= date('d/m/Y H:i', strtotime($h['date_creation'])) ?></small>
            </div>

            <form method="GET" class="suppr-box" style="display:flex; align-items:center; gap:8px;">
                <input type="hidden" name="id" value="<?= $id_machine ?>">
                <input type="hidden" name="delete_id" value="<?= $h['id'] ?>">
                <label style="font-size: 10px; color: #dc3545; cursor: pointer;"><input type="checkbox" name="confirmer_suppr" required> Confirmer</label>
                <button type="submit" class="btn-danger-ui">Supprimer</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    var map = L.map('map').setView([49.5, 3.5], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var donneesHistorique = <?= json_encode($historique) ?>;
    var bounds = [];
    var mapLayers = {}; // Dictionnaire global pour stocker les tracés

    function toggleInputs() {
        const type = document.getElementById('typeSelect').value;
        document.getElementById('groupB').style.display = (type === 'LIGNE') ? 'grid' : 'none';
        document.getElementsByName('latB')[0].required = (type === 'LIGNE');
        document.getElementsByName('lngB')[0].required = (type === 'LIGNE');
    }

    // Fonction pour centrer la carte sur un élément précis
    function focusElement(id) {
        var layer = mapLayers[id];
        if (layer) {
            if (layer.getBounds) { 
                // C'est une ligne
                map.fitBounds(layer.getBounds(), {padding: [50, 50]});
            } else { 
                // C'est un point
                map.setView(layer.getLatLng(), 15);
            }
            layer.openPopup();
        }
    }

    donneesHistorique.forEach(function(h) {
        try {
            var coords = JSON.parse(h.donnees_gps);
            var nom = h.nom.replace(/'/g, "\\'"); 

            if (h.type_objet === 'POINT') {
                var lat = coords.latA;
                var lng = coords.lngA;
                var content = "<b>📍 " + nom + "</b><br>Lat: " + lat + "<br>Lng: " + lng;
                
                // On stocke le marqueur dans mapLayers
                var marker = L.marker([lat, lng]).addTo(map).bindPopup(content);
                mapLayers[h.id] = marker;
                
                // Zone de clic invisible élargie
                L.circle([lat, lng], {
                    radius: 12,
                    stroke: false,
                    fillColor: 'transparent',
                    fillOpacity: 0,
                    interactive: true
                }).addTo(map).on('click', function() { marker.openPopup(); });

                bounds.push([lat, lng]);

            } else {
                var path = [[coords.latA, coords.lngA], [coords.latB, coords.lngB]];
                var content = "<b>📏 Ligne AB: " + nom + "</b><br>A: " + coords.latA + ", " + coords.lngA + "<br>B: " + coords.latB + ", " + coords.lngB;
                
                // Ligne visible
                var polyline = L.polyline(path, {color: 'red', weight: 4}).addTo(map).bindPopup(content);
                // On stocke la polyline dans mapLayers pour le focus
                mapLayers[h.id] = polyline;

                // Ligne invisible large pour faciliter le clic
                L.polyline(path, {
                    color: 'transparent',
                    weight: 35, 
                    interactive: true
                }).addTo(map).on('click', function() { polyline.openPopup(); });

                bounds.push(path[0], path[1]);
            }
        } catch (e) { 
            console.error("Erreur sur l'élément GPS ID " + h.id, e); 
        }
    });

    if (bounds.length > 0) map.fitBounds(bounds, {padding: [40, 40]});
</script>
</body>
</html>