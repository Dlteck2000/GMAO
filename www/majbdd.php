<?php
require_once 'db.php';

// try {
    // $sql = "CREATE TABLE IF NOT EXISTS stock_mouvements (
        // id INTEGER PRIMARY KEY AUTOINCREMENT,
        // entreprise_id INTEGER,
        // reference TEXT,
        // marque TEXT,
        // fournisseur TEXT,
        // type_mouvement TEXT, 
        // quantite INTEGER,
        // usage_type TEXT,     
        // date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
        // commentaire TEXT
    // )";

    // $pdo->exec($sql);
    // echo "<h2 style='color:green; font-family:sans-serif;'>✅ Table 'stock_mouvements' créée avec succès !</h2>";
    // echo "<p><a href='index.php'>Retour à l'accueil</a></p>";

// } catch (PDOException $e) {
    // die("Erreur lors de la création de la table : " . $e->getMessage());
// }

// try {
    // $sql1 = "CREATE TABLE IF NOT EXISTS interventions_externes (
    // id INTEGER PRIMARY KEY AUTOINCREMENT,
    // entreprise_id INTEGER,
    // intervenant_nom TEXT,      -- Nom du garage / prestataire
    // date_intervention DATE,
    // motif TEXT,                -- Vidange, Panne hydraulique, etc.
    // materiel_concerne TEXT,    -- Tracteur Fendt 724, Moissonneuse, etc.
    // ref_bon TEXT,              -- Numéro de bon de travail ou facture
    // commentaire TEXT,
    // date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
// )";

    // $pdo->exec($sql1);
    // echo "<h2 style='color:green; font-family:sans-serif;'>✅ Table 'interventions_externes' créée avec succès !</h2>";
    // echo "<p><a href='index.php'>Retour à l'accueil</a></p>";

// } catch (PDOException $e) {
    // die("Erreur lors de la création de la table : " . $e->getMessage());
// }

try {
    $sql1 = "ALTER TABLE stock_mouvements ADD COLUMN materiel_id INTEGER";

    $pdo->exec($sql1);
    echo "<h2 style='color:green; font-family:sans-serif;'>✅ Table 'stock_mouvements' maj avec succès !</h2>";
    echo "<p><a href='index.php'>Retour à l'accueil</a></p>";

} catch (PDOException $e) {
    die("Erreur lors de la création de la table : " . $e->getMessage());
}
?>