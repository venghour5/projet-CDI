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

    session_unset();
    session_destroy();
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Connexion espace CDI</title>
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
            </ul>
        </nav>
    </header>

    <main class="login-container">
        <form class="login-card" action="auth.php" method="post">
            <a href="index.php" class="back-link">&larr; Retour à l'accueil</a>

            <h2>Connexion à l'espace administration</h2>
            <p class="login-subtitle">Veuillez saisir vos identifiants</p>

            <?php if ($error === 'invalid'): ?>
                <p style="color:red; text-align:center;">Identifiants incorrects.</p>
            <?php elseif ($error === 'db'): ?>
                <p style="color:red; text-align:center;">Erreur de base de donnees. Merci de vous reconnecter.</p>
            <?php endif; ?>

            <div class="input-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" placeholder="Entrez votre identifiant" required>
            </div>

            <div class="input-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Entrez votre mot de passe" required>
            </div>

            <button type="submit" class="btn-submit">Connexion</button>
            <p class="account-action">Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
        </form>
    </main>

</body>
</html>

