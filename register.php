<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'db.php';
require_once __DIR__ . '/src/auth_session.php';

$sessionUser = requireAuthenticatedSessionUser($pdo, [1], 'index.php');
$role = (int)$sessionUser['role'];

$error = $_GET['error'] ?? '';
$created = isset($_GET['created']) && $_GET['created'] === '1';
$styleVersion = (string)(@filemtime(__DIR__ . '/style.css') ?: '1');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Création de compte</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode($styleVersion); ?>" />
</head>
<body>

<main class="login-container">
    <form class="login-card" action="create_account.php" method="post">
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
                <h2>Création de compte</h2>
                <p class="login-subtitle">Création réservée au super admin</p>
            </div>

            <?php if ($created): ?>
                <p class="form-feedback success">Compte créé avec succès.</p>
            <?php endif; ?>

            <?php if ($error === 'missing'): ?>
                <p class="form-feedback error">Tous les champs sont obligatoires.</p>
            <?php elseif ($error === 'mismatch'): ?>
                <p class="form-feedback error">Les mots de passe ne correspondent pas.</p>
            <?php elseif ($error === 'exists'): ?>
                <p class="form-feedback error">Ce nom d'utilisateur existe déjà.</p>
            <?php elseif ($error === 'server'): ?>
                <p class="form-feedback error">Erreur serveur, réessayez plus tard.</p>
            <?php elseif ($error === 'forbidden'): ?>
                <p class="form-feedback error">Accès refusé.</p>
            <?php endif; ?>

            <div class="input-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" placeholder="Entrez votre identifiant" required>
            </div>

            <div class="input-group">
                <label for="role">Rôle du compte</label>
                <select id="role" name="role" required>
                    <option value="3">Professeur</option>
                    <option value="2">Admin CDI</option>
                    <option value="4">Admin Réservation</option>
                    <option value="1">Super Admin</option>
                </select>
            </div>

            <div class="input-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Entrez un mot de passe" required>
            </div>

            <div class="input-group">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="Confirmez le mot de passe" required>
            </div>

            <button type="submit" class="btn-submit">Créer le compte</button>
        </div>
    </form>
</main>

</body>
</html>
