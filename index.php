<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'db.php';
require_once __DIR__ . '/src/auth_session.php';

$sessionUser = requireAuthenticatedSessionUser($pdo, [1, 2, 3, 4], 'login.php');
$role = (int)$sessionUser['role'];

if ($role === 2) {
    header('Location: cdi.php');
    exit();
}

if ($role === 3) {
    header('Location: vehicule.php');
    exit();
}

$canAccessCdi = in_array($role, [1, 2], true);
$canAccessReservation = in_array($role, [1, 3, 4], true);
$canManageAccounts = ($role === 1);
$isReservationAdmin = ($role === 4);
$styleVersion = (string)(@filemtime(__DIR__ . '/style.css') ?: '1');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion CDI</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode($styleVersion); ?>" />
</head>
<body>

<header class="site-header">
    <nav class="navbar">
        <div class="logo-lycee">
            <a href="index.php">
                <span class="logo-mark">CDI</span>
                CDI <span class="logo-separator">-</span> Lycée
            </a>
        </div>

        <ul class="nav-links">
            <?php if ($role === 1): ?>
                <li><a href="index.php" class="active">Accueil</a></li>
                <li><a href="cdi.php">Zone CDI</a></li>
                <li><a href="esp.php">Modules ESP</a></li>
                <li><a href="vehicule.php">V&eacute;hicule</a></li>
                <li><a href="radio.php">Salle radio</a></li>
                <li><a href="mobile.php">Classe mobile</a></li>
                <li><a href="reservation_validation.php">Confirmation</a></li>
                <li><a href="register.php">Cr&eacute;er un compte</a></li>
                <li><a href="logout.php">D&eacute;connexion</a></li>
            <?php else: ?>
                <li><a href="index.php" class="active">Accueil</a></li>
                <li><a href="logout.php">D&eacute;connexion</a></li>
            <?php endif; ?>
            <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
        </ul>
    </nav>
</header>

<main class="container">
    <?php if ($role === 1): ?>
        <a href="cdi.php" class="card">
            <span class="card-label">Administration CDI</span>
        </a>
        <a href="esp.php" class="card">
            <span class="card-label">Modules ESP</span>
        </a>
        <a href="vehicule.php" class="card">
            <span class="card-label">Réservation</span>
        </a>
        <a href="register.php" class="card">
            <span class="card-label">Créer un compte</span>
        </a>
    <?php elseif ($isReservationAdmin): ?>
        <a href="vehicule.php" class="card">
            <span class="card-label">Accéder à la réservation</span>
        </a>
        <a href="reservation_validation.php" class="card">
            <span class="card-label">Confirmer les réservations</span>
        </a>
    <?php else: ?>
        <?php if ($canAccessCdi): ?>
            <a href="cdi.php" class="card">
                <span class="card-label">Administration CDI</span>
            </a>
            <a href="esp.php" class="card">
                <span class="card-label">Gestion Modules ESP</span>
            </a>
        <?php endif; ?>

        <?php if ($canAccessReservation): ?>
            <a href="vehicule.php" class="card">
                <span class="card-label">Réservation Véhicule</span>
            </a>
            <a href="radio.php" class="card">
                <span class="card-label">Réservation Salle radio</span>
            </a>
            <a href="mobile.php" class="card">
                <span class="card-label">Réservation Classe mobile</span>
            </a>
        <?php endif; ?>

        <?php if ($canManageAccounts): ?>
            <a href="register.php" class="card">
                <span class="card-label">Créer un compte</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>
</main>

</body>
</html>
