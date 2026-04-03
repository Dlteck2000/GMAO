<?php
// 1. Activer l'affichage de TOUTES les erreurs pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

$erreur = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // On vérifie que les champs ne sont pas vides
    if (empty($email) || empty($password)) {
        $erreur = "Veuillez remplir tous les champs.";
    } else {
        // Requête préparée
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // L'utilisateur existe, on vérifie le hash
            if (password_verify($password, $user['password_hash'])) {
                // SUCCESS : On remplit la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['entreprise_id'] = $user['entreprise_id'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['role'] = $user['role'];

                // Redirection forcée
                header("Location: index.php");
                exit();
            } else {
                $erreur = "Mot de passe incorrect.";
            }
        } else {
            $erreur = "Aucun compte trouvé pour cet email ($email).";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Maintenance</title>
    <style>
        /* Rappel du style global */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
        }

        .login-card { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 400px; 
        }

        .login-card h1 { 
            margin-top: 0; 
            font-size: 24px; 
            color: #333; 
            text-align: center; 
            margin-bottom: 30px;
        }

        .login-card h1 span { color: #007bff; }

        .form-group { margin-bottom: 20px; }

        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #555; 
            font-size: 14px;
        }

        input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input:focus { 
            border-color: #007bff; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1); 
        }

        .btn-login { 
            width: 100%; 
            padding: 12px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover { background: #0056b3; }

        .error-msg { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 10px; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-size: 14px; 
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        .footer-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #888;
        }

        .footer-link a {
            color: #007bff;
            text-decoration: none;
        }
		/* Style par défaut pour les mobiles */
        @media (max-width: 768px) {
            header { flex-direction: column; align-items: flex-start; }
nav { 
        width: 100%; 
        display: grid; 
        grid-template-columns: 1fr 1fr; /* Deux colonnes égales */
        gap: 10px; 
        margin-top: 15px;
    }

    nav a { 
        display: block;
        padding: 15px 10px !important; /* On augmente la zone de clic */
        font-size: 14px !important;    /* On force une taille lisible */
        font-weight: bold;
        text-align: center;
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin: 0 !important; /* On supprime les marges forcées en ligne */
    }

    /* Le bouton déconnexion prend toute la largeur en bas pour être bien visible */
    nav a[href="logout.php"] {
        grid-column: span 2; 
        background: #fff5f5;
        color: #e53e3e !important;
        border-color: #feb2b2;
    }

    /* On remplace l'icône seule par du texte pour la clarté */
    nav a[href="logout.php"]::after {
        content: " Déconnexion";
    }

            /* Transformation du tableau en cartes */
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
            
            .progress-container { width: 100% !important; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1>🔧 GMAO <span>Pro</span></h1>
    
    <?php if ($erreur): ?>
        <div class="error-msg"><?= $erreur ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" name="email" id="email" placeholder="nom@entreprise.fr" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-login">Se connecter</button>
    </form>

    <div class="footer-link">
        Besoin d'un accès ? <a href="mailto:manu@couvercelle.eu">Contactez l'admin</a>
    </div>
</div>

</body>
</html>