<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit();
}

$role = isset($_SESSION['role']) ? (int)$_SESSION['role'] : -1;
if (!in_array($role, [1, 2, 3, 4], true)) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=invalid');
    exit();
}

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
            <li><a href="index.php" class="active">Accueil</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
            <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
        </ul>
    </nav>
</header>

<main class="container">
    <?php if ($role === 1): ?>
        <a href="cdi.php" class="card">Administration<br />CDI</a>
        <a href="vehicule.php" class="card">Réservation</a>
        <a href="register.php" class="card">Créer un compte</a>
    <?php elseif ($isReservationAdmin): ?>
        <a href="vehicule.php" class="card">Accéder à la<br />réservation</a>
        <a href="reservation_validation.php" class="card">Confirmer les<br />réservations</a>
    <?php else: ?>
    <?php if ($canAccessCdi): ?>
        <a href="cdi.php" class="card">Administration<br />CDI</a>
        <a href="esp.php" class="card">Gestion<br />Modules ESP</a>
    <?php endif; ?>

    <?php if ($canAccessReservation): ?>
        <a href="vehicule.php" class="card">Réservation<br />Véhicule</a>
        <a href="radio.php" class="card">Réservation<br />Salle radio</a>
        <a href="mobile.php" class="card">Réservation<br />Classe mobile</a>
    <?php endif; ?>

    <?php if ($canManageAccounts): ?>
        <a href="register.php" class="card">Super Admin<br />Créer un compte</a>
    <?php endif; ?>
    <?php endif; ?>
</main>

</body>
</html>
