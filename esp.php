<?php
declare(strict_types=1);

session_start();
require_once 'db.php';
require_once __DIR__ . '/src/module_supervision.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit();
}

$roleStmt = $pdo->prepare('SELECT role FROM utilisateur WHERE id = ? LIMIT 1');
$roleStmt->execute([(int)$_SESSION['id_user']]);
$liveRole = (int)($roleStmt->fetchColumn() ?: -1);
$_SESSION['role'] = $liveRole;

if (!in_array($liveRole, [1, 2], true)) {
    header('Location: vehicule.php');
    exit();
}

$canAddModule = ($liveRole === 1);

ensureModuleSupervisionSchema($pdo);

const OFFLINE_DELAY_SECONDS = 300;

$feedbackMessage = '';
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_module') {
            if (!$canAddModule) {
                throw new RuntimeException('forbidden_add_module');
            }

            $ipAddress = trim((string)($_POST['ip_address'] ?? ''));
            $moduleName = trim((string)($_POST['module_name'] ?? ''));
            $heartbeatInput = (string)($_POST['heartbeat_interval_sec'] ?? '60');

            $heartbeatInterval = is_numeric($heartbeatInput) ? (int)$heartbeatInput : 60;

            registerOrUpdateModule(
                $pdo,
                null,
                $ipAddress,
                $moduleName !== '' ? $moduleName : null,
                $heartbeatInterval,
                null
            );

            header('Location: esp.php?success=module_added');
            exit();
        }

        if ($action === 'assign_module_zone') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $zoneId = (int)($_POST['zone_id'] ?? 0);

            if ($moduleId <= 0 || $zoneId <= 0) {
                throw new InvalidArgumentException('Paramètres invalides');
            }

            assignModuleToZone($pdo, $moduleId, $zoneId);
            logModuleActivity($pdo, $moduleId, 'Association zone ' . $zoneId, null, true);

            header('Location: esp.php?success=module_assigned');
            exit();
        }

        if ($action === 'disconnect_module') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId <= 0) {
                throw new InvalidArgumentException('Module invalide');
            }

            disconnectModuleZone($pdo, $moduleId);
            logModuleActivity($pdo, $moduleId, 'Désassociation zone', null, true);

            header('Location: esp.php?success=module_disconnected');
            exit();
        }

        if ($action === 'mark_unreachable') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId <= 0) {
                throw new InvalidArgumentException('Module invalide');
            }

            logModuleActivity($pdo, $moduleId, 'Ping admin: module non joignable', null, false);
            syncModuleHealthAlerts($pdo, OFFLINE_DELAY_SECONDS);

            header('Location: esp.php?success=module_flagged_unreachable');
            exit();
        }
    } catch (Throwable $e) {
        header('Location: esp.php?error=' . urlencode($action !== '' ? $action : 'unknown'));
        exit();
    }
}

syncModuleHealthAlerts($pdo, OFFLINE_DELAY_SECONDS);

$modules = fetchModulesOverview($pdo, OFFLINE_DELAY_SECONDS);
$zones = $pdo->query('SELECT id AS id_zone, nom AS nom_zone FROM genre ORDER BY nom ASC')->fetchAll(PDO::FETCH_ASSOC);
$summary = buildSupervisionSummary($pdo, OFFLINE_DELAY_SECONDS);

$visibleAlertsCount = 0;
foreach ($modules as &$module) {
    $moduleAlerts = is_array($module['active_alerts'] ?? null) ? $module['active_alerts'] : [];
    $module['active_alerts'] = array_values(array_filter(
        $moduleAlerts,
        static fn(array $alert): bool => (string)($alert['code'] ?? '') !== 'MODULE_UNREACHABLE'
    ));
    $visibleAlertsCount += count($module['active_alerts']);
}
unset($module);
$summary['active_alerts'] = $visibleAlertsCount;

if (isset($_GET['success'])) {
    $successCode = (string)$_GET['success'];
    if ($successCode === 'module_added') {
        $feedbackMessage = 'Module ajouté avec succès.';
    } elseif ($successCode === 'module_assigned') {
        $feedbackMessage = 'Module associé à la zone.';
    } elseif ($successCode === 'module_disconnected') {
        $feedbackMessage = 'Module déconnecté de la zone.';
    } elseif ($successCode === 'module_flagged_unreachable') {
        $feedbackMessage = 'État non joignable enregistré pour le module.';
    }
}

if ($feedbackMessage === '' && isset($_GET['error'])) {
    $feedbackType = 'error';
    if ((string)$_GET['error'] === 'add_module') {
        $feedbackMessage = 'Seul le Super Admin peut ajouter un module.';
    } else {
        $feedbackMessage = 'Opération impossible. Vérifie les données saisies.';
    }
}

$styleVersion = (string)(@filemtime(__DIR__ . '/style.css') ?: '1');

function formatLastSignal(?string $dateString): string
{
    if ($dateString === null || $dateString === '') {
        return '--';
    }

    try {
        $dt = new DateTimeImmutable($dateString);
        return $dt->format('d/m/Y H:i:s');
    } catch (Throwable $e) {
        return (string)$dateString;
    }
}

function formatSecondsAgo(?int $seconds): string
{
    if ($seconds === null) {
        return '--';
    }

    if ($seconds < 60) {
        return $seconds . ' sec';
    }

    if ($seconds < 3600) {
        return (int)floor($seconds / 60) . ' min';
    }

    return (int)floor($seconds / 3600) . ' h';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion CDI - Modules ESP</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode($styleVersion); ?>" />
    <style>
        .esp-top-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-chip {
            background: #d9d9d9;
            border-radius: 14px;
            padding: 12px;
            font-weight: 700;
            border: 1px solid #9e9e9e;
            text-align: center;
        }

        .summary-chip .value {
            display: block;
            font-size: 26px;
            margin-bottom: 4px;
        }

        .esp-feedback {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .esp-feedback.success { background: #89ff57; }
        .esp-feedback.error { background: #ff8989; }

        .module-meta {
            font-size: 14px;
            line-height: 1.35;
            color: #1d1d1d;
            margin-top: 10px;
        }

        .connection-pill {
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .connection-pill.online {
            background: #88ef3e;
            color: #000;
        }

        .connection-pill.offline {
            background: #ff4f4f;
            color: #fff;
        }

        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .activity-label {
            display: inline-block;
            font-size: 14px;
            font-weight: 700;
            color: #111;
            background: #ececec;
            border-radius: 10px;
            padding: 6px 10px;
            margin-top: 8px;
        }

        .alert-list {
            list-style: none;
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .alert-item {
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px;
            padding: 8px 10px;
            border: 1px solid #8f8f8f;
            background: #efefef;
        }

        .alert-item.warning {
            border-color: #cc9c00;
            background: #fff5ce;
        }

        .alert-item.critical {
            border-color: #ce3f3f;
            background: #ffd7d7;
        }

        .esp-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2200;
        }

        .esp-modal-overlay.active { display: flex; }

        .esp-modal {
            width: min(520px, 92vw);
            background: #d9d9d9;
            border: 2px solid #000;
            border-radius: 18px;
            padding: 24px;
            position: relative;
        }

        .esp-modal-close {
            position: absolute;
            right: 14px;
            top: 8px;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
        }

        .esp-modal h2 {
            font-size: 28px;
            margin-bottom: 12px;
        }

        .esp-modal .help {
            font-size: 15px;
            color: #222;
            margin-bottom: 12px;
        }

        .esp-modal-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .esp-modal-form label { font-weight: 700; }

        .esp-modal-form input,
        .esp-modal-form select {
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            width: 100%;
        }

        .esp-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 6px;
        }

        .esp-btn-secondary,
        .esp-btn-primary {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
        }

        .esp-btn-secondary { background: #b8b8b8; color: #000; }
        .esp-btn-primary { background: #000; color: #fff; }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .small-action-btn {
            border: none;
            border-radius: 10px;
            background: #efefef;
            font-size: 13px;
            padding: 8px 10px;
            cursor: pointer;
            font-weight: 700;
        }

        @media (max-width: 1200px) {
            .esp-top-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .esp-top-summary {
                grid-template-columns: 1fr;
            }
        }
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
                <li><a href="cdi.php">Zone CDI</a></li>
                <li><a href="esp.php" class="active">Modules ESP</a></li>
                <li><a href="logout.php">Déconnexion</a></li>
                <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
            </ul>
        </nav>
    </header>

    <main class="esp-page">
        <?php if ($feedbackMessage !== ''): ?>
            <div class="esp-feedback <?php echo $feedbackType === 'error' ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($feedbackMessage); ?>
            </div>
        <?php endif; ?>

        <section class="esp-top-summary">
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['total_modules']; ?></span>Total modules</article>
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['online_modules']; ?></span>En ligne</article>
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['offline_modules']; ?></span>Hors ligne</article>
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['active_alerts']; ?></span>Alertes actives</article>
        </section>

        <div class="esp-grid">
            <?php foreach ($modules as $module):
                $displayName = $module['nom_module'] !== null && $module['nom_module'] !== ''
                    ? $module['nom_module']
                    : 'ESP ' . ($module['ip_address'] !== '' ? $module['ip_address'] : $module['id']);
                $zoneName = $module['nom_zone'] ?? 'Non associé';
                $isAssigned = $module['id_zone'] !== null;
                $connectionClass = $module['is_online'] ? 'online' : 'offline';
                $connectionText = $module['is_online'] ? 'Connecté' : 'Non joignable';
                $activityText = $module['derniere_activite'] ?? 'Aucune activité remontée';
                $alerts = $module['active_alerts'];
            ?>
                <article class="esp-card">
                    <div class="esp-card-top">
                        <div>
                            <h2 class="module-title"><?php echo htmlspecialchars((string)$displayName); ?></h2>
                            <p class="module-zone"><?php echo htmlspecialchars((string)$zoneName); ?></p>
                        </div>
                    </div>

                    <div class="status-row">
                        <span class="connection-pill <?php echo $connectionClass; ?>">
                            <i class="fa-solid <?php echo $module['is_online'] ? 'fa-wifi' : 'fa-triangle-exclamation'; ?>"></i>
                            <?php echo $connectionText; ?>
                        </span>
                        <span style="font-size:13px; font-weight:700; color:#333;">Signal: <?php echo htmlspecialchars(formatSecondsAgo($module['secondes_depuis_signal'])); ?></span>
                    </div>

                    <div class="module-meta">
                        <div><strong>IP:</strong> <?php echo htmlspecialchars($module['ip_address'] !== '' ? $module['ip_address'] : '--'); ?></div>
                        <div><strong>Dernier signal:</strong> <?php echo htmlspecialchars(formatLastSignal($module['dernier_signal'])); ?></div>
                        <div><strong>Activité :</strong> <span class="activity-label"><?php echo htmlspecialchars((string)$activityText); ?></span></div>
                    </div>

                    <?php if (!empty($alerts)): ?>
                        <ul class="alert-list">
                            <?php foreach ($alerts as $alert): ?>
                                <li class="alert-item <?php echo htmlspecialchars((string)$alert['niveau']); ?>">
                                    <?php echo htmlspecialchars((string)$alert['message']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="esp-card-bottom">
                        <div class="card-actions">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="disconnect_module">
                                <input type="hidden" name="module_id" value="<?php echo (int)$module['id']; ?>">
                                <button class="small-action-btn" type="submit" <?php echo $isAssigned ? '' : 'disabled'; ?>>Désassocier</button>
                            </form>

                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="mark_unreachable">
                                <input type="hidden" name="module_id" value="<?php echo (int)$module['id']; ?>">
                                <button class="small-action-btn" type="submit">Signaler non joignable</button>
                            </form>
                        </div>

                        <button
                            class="card-plus-btn open-assign-modal"
                            type="button"
                            aria-label="Associer à une zone"
                            data-module-id="<?php echo (int)$module['id']; ?>"
                            data-module-name="<?php echo htmlspecialchars((string)$displayName, ENT_QUOTES); ?>"
                        >
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($canAddModule): ?>
                <button id="openAddModuleModal" class="esp-add-card" type="button" aria-label="Ajouter un module">
                    <span class="big-plus-btn">+</span>
                    <span style="display:block;font-size:18px;font-weight:700;margin-top:12px;">Ajouter un module ESP</span>
                </button>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($canAddModule): ?>
        <div class="esp-modal-overlay" id="addModuleModal">
            <div class="esp-modal">
                <button class="esp-modal-close" type="button" data-close-modal="addModuleModal">&times;</button>
                <h2>Ajouter un module ESP</h2>
                <p class="help">Enregistrement initial du module dans la base centrale.</p>

                <form method="POST" class="esp-modal-form">
                    <input type="hidden" name="action" value="add_module">

                    <label for="moduleIpInput">Adresse IP</label>
                    <input id="moduleIpInput" type="text" name="ip_address" placeholder="Ex: 10.1.1.4" required>

                    <label for="moduleNameInput">Nom du module</label>
                    <input id="moduleNameInput" type="text" name="module_name" placeholder="Ex: ESP rayon Histoire">

                    <label for="heartbeatInput">Fréquence heartbeat (sec)</label>
                    <input id="heartbeatInput" type="number" min="10" max="3600" name="heartbeat_interval_sec" value="60" required>

                    <div class="esp-modal-actions">
                        <button type="button" class="esp-btn-secondary" data-close-modal="addModuleModal">Annuler</button>
                        <button type="submit" class="esp-btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="esp-modal-overlay" id="assignZoneModal">
        <div class="esp-modal">
            <button class="esp-modal-close" type="button" data-close-modal="assignZoneModal">&times;</button>
            <h2>Associer un module</h2>
            <p class="help" id="assignHelpText">Sélectionnez une zone.</p>

            <form method="POST" class="esp-modal-form">
                <input type="hidden" name="action" value="assign_module_zone">
                <input type="hidden" name="module_id" id="assignModuleIdInput" value="">

                <label for="assignZoneSelect">Zone</label>
                <select id="assignZoneSelect" name="zone_id" <?php echo empty($zones) ? 'disabled' : ''; ?> required>
                    <option value="">Choisir une zone</option>
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo (int)$zone['id_zone']; ?>"><?php echo htmlspecialchars((string)$zone['nom_zone']); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="esp-modal-actions">
                    <button type="button" class="esp-btn-secondary" data-close-modal="assignZoneModal">Annuler</button>
                    <button type="submit" class="esp-btn-primary" <?php echo empty($zones) ? 'disabled' : ''; ?>>Associer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const addModuleModal = document.getElementById('addModuleModal');
        const assignZoneModal = document.getElementById('assignZoneModal');
        const openAddModuleModal = document.getElementById('openAddModuleModal');
        const assignModuleIdInput = document.getElementById('assignModuleIdInput');
        const assignHelpText = document.getElementById('assignHelpText');

        if (openAddModuleModal && addModuleModal) {
            openAddModuleModal.addEventListener('click', () => {
                addModuleModal.classList.add('active');
            });
        }

        document.querySelectorAll('.open-assign-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const moduleId = button.dataset.moduleId || '';
                const moduleName = button.dataset.moduleName || 'ce module';

                if (assignModuleIdInput) {
                    assignModuleIdInput.value = moduleId;
                }

                if (assignHelpText) {
                    assignHelpText.textContent = `Associer ${moduleName} à une zone.`;
                }

                if (assignZoneModal) {
                    assignZoneModal.classList.add('active');
                }
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.closeModal;
                const target = document.getElementById(targetId);
                if (target) {
                    target.classList.remove('active');
                }
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target === addModuleModal) {
                addModuleModal.classList.remove('active');
            }
            if (event.target === assignZoneModal) {
                assignZoneModal.classList.remove('active');
            }
        });
    </script>
</body>
</html>
