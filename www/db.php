<?php
try {
    // Le fichier de base de données sera créé dans le même dossier
    $db_path = __DIR__ . '/database_gmao.sqlite';
    
    $pdo = new PDO("sqlite:" . $db_path);
    
    // Configuration pour la sécurité et les performances
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Activer les clés étrangères (désactivées par défaut sur SQLite)
    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch (PDOException $e) {
    die("Erreur de connexion à SQLite : " . $e->getMessage());
}
?>