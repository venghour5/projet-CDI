<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'db.php';
require_once __DIR__ . '/src/auth_session.php';

$sessionUser = getAuthenticatedSessionUser($pdo);
if ($sessionUser !== null) {
    $role = (int)$sessionUser['role'];
    if ($role === 2) { header("Location: cdi.php"); exit(); }
    if ($role === 3) { header("Location: vehicule.php"); exit(); }
    if (in_array($role, [1, 4], true)) { header("Location: index.php"); exit(); }
    destroyCurrentSession();
}
$error = $_GET['error'] ?? '';
$styleVersion = (string)(@filemtime(__DIR__ . '/style.css') ?: '1');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Connexion — CDI Lycée</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode($styleVersion); ?>" />
</head>
<body>

    <main class="login-container">
        <form class="login-card" action="auth.php" method="post">

            <div class="login-card-header">
                <span class="logo-mark">CDI</span>
                <div class="login-card-header-text">
                    <span class="login-card-header-title">Espace Administration</span>
                    <span class="login-card-header-sub">CDI — Lycée</span>
                </div>
            </div>

            <div class="login-card-body">
                <a href="index.php" class="back-link">&larr; Retour à l'accueil</a>

                <div>
                    <h2>Connexion</h2>
                    <p class="login-subtitle">Veuillez saisir vos identifiants</p>
                </div>

                <?php if ($error === 'invalid'): ?>
                    <p class="form-feedback error">Identifiants incorrects.</p>
                <?php elseif ($error === 'db'): ?>
                    <p class="form-feedback error">Erreur de base de données. Merci de réessayer.</p>
                <?php endif; ?>

                <div class="input-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" placeholder="Entrez votre identifiant" required autocomplete="username">
                </div>

                <div class="input-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-submit">Se connecter</button>
            </div>

        </form>
    </main>

</body>
</html>
