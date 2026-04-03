<?php
require_once 'db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entreprise_nom = $_POST['entreprise'];
    $nom_admin = $_POST['nom'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Sécurité max

    try {
        $pdo->beginTransaction();

        // 1. Créer l'entreprise
        $stmt = $pdo->prepare("INSERT INTO entreprises (nom) VALUES (?)");
        $stmt->execute([$entreprise_nom]);
        $entreprise_id = $pdo->lastInsertId();

        // 2. Créer l'utilisateur lié
        $stmt = $pdo->prepare("INSERT INTO utilisateurs (entreprise_id, nom, email, password_hash, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$entreprise_id, $nom_admin, $email, $password]);

        $pdo->commit();
        $message = "<p style='color:green'>Compte créé ! <a href='login.php'>Connectez-vous ici</a></p>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<p style='color:red'>Erreur : " . $e->getMessage() . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Inscription</title></head>
<body>
    <h1>Créer un compte entreprise</h1>
    <?= $message ?>
    <form method="POST">
        <input type="text" name="entreprise" placeholder="Nom de votre entreprise" required><br><br>
        <input type="text" name="nom" placeholder="Votre nom" required><br><br>
        <input type="email" name="email" placeholder="Email professionnel" required><br><br>
        <input type="password" name="password" placeholder="Mot de passe" required><br><br>
        <button type="submit">Créer mon espace</button>
    </form>
	<?php include 'footer.php'; ?>
</body>
</html>
