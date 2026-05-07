<?php
session_start();

if (isset($_SESSION['id_user'])) {
    $role = isset($_SESSION['role']) ? (int)$_SESSION['role'] : -1;
    if ($role === 1) {
        header("Location: cdi.php");
        exit();
    }

    if ($role === 0) {
        header("Location: vehicule.php");
        exit();
    }
}

$error = $_GET['error'] ?? '';
$created = isset($_GET['created']) && $_GET['created'] === '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Création de compte</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css" />
</head>
<body>

    <header class="site-header">
        <nav class="navbar">
            <div class="logo-lycee">
                <a href="index.php">
                    <span class="logo-mark">CDI</span>
                    CDI <span class="logo-separator">-</span> Lycee
                </a>
            </div>

            <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="login.php">Connexion</a></li>
                <li><a href="register.php" class="active">Créer un compte</a></li>
            </ul>
        </nav>
    </header>

    <main class="login-container">
        <form class="login-card" action="create_account.php" method="post">
            <a href="index.php" class="back-link">&larr; Retour à l'accueil</a>

            <h2>Création de compte</h2>
            <p class="login-subtitle">Remplissez les champs pour créer votre compte</p>

            <?php if ($created): ?>
                <p class="form-feedback success">Compte créé avec succès. Vous pouvez vous connecter.</p>
            <?php endif; ?>

            <?php if ($error === 'missing'): ?>
                <p class="form-feedback error">Tous les champs sont obligatoires.</p>
            <?php elseif ($error === 'mismatch'): ?>
                <p class="form-feedback error">Les mots de passe ne correspondent pas.</p>
            <?php elseif ($error === 'exists'): ?>
                <p class="form-feedback error">Ce nom d'utilisateur existe déjà.</p>
            <?php elseif ($error === 'server'): ?>
                <p class="form-feedback error">Erreur serveur, réessayez plus tard.</p>
            <?php endif; ?>

            <div class="input-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" placeholder="Choisissez un identifiant" required>
            </div>

            <div class="input-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Entrez un mot de passe" required>
            </div>

            <div class="input-group">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirmez le mot de passe" required>
            </div>

            <button type="submit" class="btn-submit">Créer mon compte</button>
            <p class="account-action">Déjà un compte ? <a href="login.php">Se connecter</a></p>
        </form>
    </main>

</body>
</html>
