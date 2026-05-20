<?php
declare(strict_types=1);

session_start();
require_once 'db.php';
require_once __DIR__ . '/src/reservation_workflow.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit();
}

$role = (int)($_SESSION['role'] ?? -1);
if (!in_array($role, [1, 4], true)) {
    header('Location: index.php');
    exit();
}

ensureReservationWorkflowSchema($pdo);
$errorCode = (string)($_GET['error'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $note = trim((string)($_POST['validation_note'] ?? ''));

    if ($reservationId > 0 && in_array($action, ['approve', 'reject'], true)) {
        if ($action === 'approve') {
            $result = approveReservationRequest($pdo, $reservationId, (int)$_SESSION['id_user'], $note);
            if (!$result['ok']) {
                header('Location: reservation_validation.php?error=' . urlencode((string)($result['error'] ?? 'approve_failed')));
                exit();
            }
        } else {
            rejectReservationRequest($pdo, $reservationId, (int)$_SESSION['id_user'], $note);
        }
    }

    header('Location: reservation_validation.php');
    exit();
}

$pendingReservations = $pdo->query(
    "SELECT
        r.id,
        r.day,
        r.heure_debut,
        r.duree,
        r.vehicle_name,
        r.status,
        u.nom AS demandeur,
        rs.nom AS ressource
    FROM reservation r
    LEFT JOIN utilisateur u ON u.id = r.id_utilisateur
    LEFT JOIN ressource rs ON rs.id = r.id_ressource
    WHERE r.status = 'pending'
    ORDER BY r.date_creation DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$validatedReservations = $pdo->query(
    "SELECT
        r.id,
        r.day,
        r.heure_debut,
        r.duree,
        r.vehicle_name,
        r.status,
        r.validation_note,
        u.nom AS demandeur,
        rs.nom AS ressource,
        v.nom AS validateur,
        r.validated_at
    FROM reservation r
    LEFT JOIN utilisateur u ON u.id = r.id_utilisateur
    LEFT JOIN utilisateur v ON v.id = r.validated_by
    LEFT JOIN ressource rs ON rs.id = r.id_ressource
    WHERE r.status IN ('approved', 'rejected')
    ORDER BY r.validated_at DESC
    LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Confirmation réservations</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css" />
    <style>
        .validation-page { padding: 24px; display: flex; flex-direction: column; gap: 18px; }
        .validation-card { background: #d9d9d9; border-radius: 16px; padding: 16px; }
        .validation-list { display: flex; flex-direction: column; gap: 12px; }
        .validation-item { background: #efefef; border: 1px solid #b8b8b8; border-radius: 12px; padding: 12px; }
        .meta { font-size: 14px; margin-bottom: 8px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .actions input { flex: 1; min-width: 180px; border-radius: 8px; border: 1px solid #aaa; padding: 8px; }
        .actions button { border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-weight: 700; }
        .btn-ok { background: #7be84e; }
        .btn-ko { background: #ff8989; }
        .status-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-approved { background: #d6ffd0; }
        .status-rejected { background: #ffd0d0; }
    </style>
</head>
<body class="dashboard-body">
<header class="site-header">
    <nav class="navbar">
        <div class="logo-lycee">
            <a href="index.php">
                <span class="logo-mark">CDI</span>
                CDI <span class="logo-separator">-</span> Lycée
            </a>
        </div>
        <ul class="nav-links">
            <li><a href="vehicule.php">Véhicule</a></li>
            <li><a href="radio.php">Salle radio</a></li>
            <li><a href="mobile.php">Classe mobile</a></li>
            <li><a href="reservation_validation.php" class="active">Confirmation</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
            <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
        </ul>
    </nav>
</header>

<main class="validation-page">
    <?php if ($errorCode !== ''): ?>
        <section class="validation-card" style="background:#ffd0d0;">
            <?php if ($errorCode === 'slot_taken'): ?>
                <p>Impossible de confirmer : le créneau est déjà réservé.</p>
            <?php else: ?>
                <p>Validation impossible. Merci de réessayer.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="validation-card">
        <h2>Réservations à confirmer</h2>
        <?php if (empty($pendingReservations)): ?>
            <p>Aucune réservation en attente.</p>
        <?php else: ?>
            <div class="validation-list">
                <?php foreach ($pendingReservations as $reservation): ?>
                    <article class="validation-item">
                        <div class="meta">
                            <strong>#<?php echo (int)$reservation['id']; ?></strong>
                            - <?php echo htmlspecialchars((string)($reservation['ressource'] ?? 'Ressource')); ?>
                            - <?php echo htmlspecialchars((string)($reservation['demandeur'] ?? 'Utilisateur')); ?>
                            - <?php echo htmlspecialchars((string)$reservation['day']); ?> <?php echo htmlspecialchars((string)$reservation['heure_debut']); ?> (<?php echo (int)$reservation['duree']; ?>h)
                            <?php if (!empty($reservation['vehicle_name'])): ?>
                                - Véhicule : <?php echo htmlspecialchars((string)$reservation['vehicle_name']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="actions">
                            <form method="POST" style="display:flex; gap:8px; flex:1; flex-wrap:wrap;">
                                <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">
                                <input type="text" name="validation_note" placeholder="Commentaire (optionnel)">
                                <button type="submit" name="action" value="approve" class="btn-ok">Confirmer</button>
                                <button type="submit" name="action" value="reject" class="btn-ko">Refuser</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="validation-card">
        <h2>Historique des validations</h2>
        <?php if (empty($validatedReservations)): ?>
            <p>Aucune validation enregistrée.</p>
        <?php else: ?>
            <div class="validation-list">
                <?php foreach ($validatedReservations as $reservation):
                    $isApproved = ($reservation['status'] ?? '') === 'approved';
                ?>
                    <article class="validation-item">
                        <span class="status-pill <?php echo $isApproved ? 'status-approved' : 'status-rejected'; ?>">
                            <?php echo $isApproved ? 'CONFIRMÉE' : 'REFUSÉE'; ?>
                        </span>
                        <div class="meta" style="margin-top:8px;">
                            #<?php echo (int)$reservation['id']; ?> -
                            <?php echo htmlspecialchars((string)($reservation['ressource'] ?? 'Ressource')); ?> -
                            <?php echo htmlspecialchars((string)($reservation['demandeur'] ?? 'Utilisateur')); ?> -
                            <?php echo htmlspecialchars((string)$reservation['day']); ?> <?php echo htmlspecialchars((string)$reservation['heure_debut']); ?>
                            <?php if (!empty($reservation['vehicle_name'])): ?>
                                - Véhicule : <?php echo htmlspecialchars((string)$reservation['vehicle_name']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="meta">
                            Validateur : <?php echo htmlspecialchars((string)($reservation['validateur'] ?? '--')); ?>
                            | Date : <?php echo htmlspecialchars((string)($reservation['validated_at'] ?? '--')); ?>
                        </div>
                        <?php if (!empty($reservation['validation_note'])): ?>
                            <div class="meta">Note : <?php echo htmlspecialchars((string)$reservation['validation_note']); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
