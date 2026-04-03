<?php
session_start();
require_once 'db.php';

// Sécurité : Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['entreprise_id'])) {
    header('Location: login.php');
    exit;
}

// --- ÉTAPE 1 : AUTO-INSTALLATION DE LA TABLE ---
// Ce bloc crée la table si elle n'existe pas encore dans la base SQLite
$sql_table = "CREATE TABLE IF NOT EXISTS aog_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    materiel_id INTEGER NOT NULL,
    titre_config TEXT,
    version_aog TEXT,
    donnees_json TEXT,
    date_import DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (materiel_id) REFERENCES materiels(id)
)";
$pdo->exec($sql_table);


// --- ÉTAPE 2 : TRAITEMENT DE L'IMPORT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['xml_file'])) {
    $id_machine = intval($_POST['id_machine']);
    $version_aog = $_POST['aog_version'];
    $titre_config = isset($_POST['titre_config']) ? $_POST['titre_config'] : 'Sans titre';
    $file = $_FILES['xml_file'];

    // Vérification basique du fichier
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: fiche_materiel.php?id=$id_machine&error=upload");
        exit;
    }

    // Chargement du XML
    $xml = @simplexml_load_file($file['tmp_name']);
    if (!$xml) {
        header("Location: fiche_materiel.php?id=$id_machine&error=xml_invalide");
        exit;
    }

    // Liste de tes 9 paramètres cibles
    $filtre = [
        'purePursuitIntegralGainAB',
        'setVehicle_goalPointLookAheadHold',
        'setAS_wasOffset',
        'setAS_countsPerDegree',
        'setAS_ackerman',
        'setVehicle_maxSteerAngle',
        'setAS_Kp',
        'setAS_highSteerPWM',
        'setAS_minSteerPWM'
    ];

    $extracted_data = [];

    // Extraction Key-Value
    // On cible le chemin : userSettings > AgOpenGPS.Properties.Settings > setting
    if (isset($xml->userSettings->{'AgOpenGPS.Properties.Settings'}->setting)) {
        foreach ($xml->userSettings->{'AgOpenGPS.Properties.Settings'}->setting as $s) {
            $name = (string)$s['name'];
            if (in_array($name, $filtre)) {
                $extracted_data[$name] = (string)$s->value;
            }
        }
    }

    // Sauvegarde si on a trouvé au moins une valeur
    if (!empty($extracted_data)) {
        $json = json_encode($extracted_data);
        
        $stmt = $pdo->prepare("INSERT INTO aog_configs (materiel_id, titre_config, version_aog, donnees_json) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_machine, $titre_config, $version_aog, $json]);

        header("Location: fiche_materiel.php?id=$id_machine&success=aog_ok");
    } else {
        header("Location: fiche_materiel.php?id=$id_machine&error=no_data");
    }
    exit;
}