<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

session_start();
require_once 'db.php';
require_once __DIR__ . '/src/module_supervision.php';
require_once __DIR__ . '/src/auth_session.php';

$sessionUser = requireAuthenticatedSessionUser($pdo, [1, 2], 'vehicule.php');
$liveRole = (int)$sessionUser['role'];

$canAddModule = ($liveRole === 1);

ensureModuleSupervisionSchema($pdo);

const OFFLINE_DELAY_SECONDS = 300;

function resolveModuleDisplayName(string $moduleName, int $moduleNumber, array $zoneNames = []): string
{
    $cleanName = trim($moduleName);
    if ($cleanName === '') {
        return 'Module ' . $moduleNumber;
    }

    $reservedNames = [];
    foreach ($zoneNames as $zoneName) {
        $normalizedZoneName = strtolower(trim((string)$zoneName));
        if ($normalizedZoneName !== '') {
            $reservedNames[$normalizedZoneName] = true;
        }
    }

    if (isset($reservedNames[strtolower($cleanName)])) {
        return 'Module ' . $moduleNumber;
    }

    return $cleanName;
}

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
            registerOrUpdateModule(
                $pdo,
                null,
                $ipAddress,
                $moduleName !== '' ? $moduleName : null,
                null
            );

            header('Location: esp.php?success=module_added');
            exit();
        }

        if ($action === 'remove_zone') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $zoneId   = (int)($_POST['zone_id'] ?? 0);
            if ($moduleId < 0 || $zoneId <= 0) {
                throw new InvalidArgumentException('Parametres invalides');
            }
            removeModuleZone($pdo, $moduleId, $zoneId);
            header('Location: esp.php?success=zone_removed');
            exit();
        }

        if ($action === 'assign_module_zone') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $zoneIds = [];
            if (isset($_POST['zone_ids']) && is_array($_POST['zone_ids'])) {
                foreach ($_POST['zone_ids'] as $rawZoneId) {
                    $zoneId = (int)$rawZoneId;
                    if ($zoneId > 0) {
                        $zoneIds[] = $zoneId;
                    }
                }
            } else {
                $zoneId = (int)($_POST['zone_id'] ?? 0);
                if ($zoneId > 0) {
                    $zoneIds[] = $zoneId;
                }
            }
            $zoneIds = array_values(array_unique($zoneIds));

            if ($moduleId < 0 || empty($zoneIds)) {
                throw new InvalidArgumentException('Parametres invalides');
            }

            foreach ($zoneIds as $zoneId) {
                assignModuleToZone($pdo, $moduleId, $zoneId);
                logModuleActivity($pdo, $moduleId, 'Association zone ' . $zoneId, null, true);
            }

            header('Location: esp.php?success=module_assigned');
            exit();
        }

        if ($action === 'disconnect_module') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId < 0) {
                throw new InvalidArgumentException('Module invalide');
            }

            disconnectModuleZone($pdo, $moduleId);
            logModuleActivity($pdo, $moduleId, 'Desassociation zone', null, true);

            header('Location: esp.php?success=module_disconnected');
            exit();
        }

        if ($action === 'delete_module') {
            if (!$canAddModule) {
                throw new RuntimeException('forbidden_delete_module');
            }

            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId <= 0) {
                throw new InvalidArgumentException('Module invalide');
            }

            deleteModule($pdo, $moduleId);

            header('Location: esp.php?success=module_deleted');
            exit();
        }

    } catch (Throwable $e) {
        $errorCode = $action !== '' ? $action : 'unknown';
        if ($e instanceof RuntimeException && $e->getMessage() !== '') {
            $errorCode = $e->getMessage();
        }
        header('Location: esp.php?error=' . urlencode($errorCode));
        exit();
    }
}

$modules = fetchModulesOverview($pdo, OFFLINE_DELAY_SECONDS);
$zones = $pdo->query('SELECT id AS id_zone, nom AS nom_zone FROM genre ORDER BY nom ASC')->fetchAll(PDO::FETCH_ASSOC);
$summary = buildSupervisionSummary($pdo, OFFLINE_DELAY_SECONDS);
$zoneNames = array_map(
    static fn(array $zone): string => trim((string)($zone['nom_zone'] ?? '')),
    $zones
);

if (isset($_GET['success'])) {
    $successCode = (string)$_GET['success'];
    if ($successCode === 'module_added') {
        $feedbackMessage = 'Module ajoute avec succes.';
    } elseif ($successCode === 'module_assigned') {
        $feedbackMessage = 'Module associe a la zone.';
    } elseif ($successCode === 'module_disconnected') {
        $feedbackMessage = 'Module deconnecte de la zone.';
    } elseif ($successCode === 'module_deleted') {
        $feedbackMessage = 'Module supprime avec succes.';
    } elseif ($successCode === 'zone_removed') {
        $feedbackMessage = 'Zone retiree du module.';
    }
}

if ($feedbackMessage === '' && isset($_GET['error'])) {
    $feedbackType = 'error';
    if ((string)$_GET['error'] === 'add_module' || (string)$_GET['error'] === 'delete_module') {
        $feedbackMessage = 'Seul le Super Admin peut gerer les modules.';
    } elseif ((string)$_GET['error'] === 'forbidden_delete_module') {
        $feedbackMessage = 'Seul le Super Admin peut supprimer un module.';
    } else {
        $feedbackMessage = 'Operation impossible. Verifie les donnees saisies.';
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
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode($styleVersion); ?>" />
    <style>
        /* Ã¢â‚¬â€ Overrides taille texte cartes Ã¢â‚¬â€ */
        .module-title { font-size: 17px !important; font-weight: 700 !important; margin-bottom: 3px !important; }
        .module-zone  { font-size: 13px !important; color: #555 !important; font-weight: 500 !important; }

        /* Ã¢â‚¬â€ Cartes modules Ã¢â‚¬â€ */
        .esp-card {
            gap: 12px !important;
            min-height: 0 !important;
            padding: 18px 20px !important;
            background: #d9d9d9 !important;
            border-radius: 14px !important;
            border-top: 3px solid #88c6d6 !important;
        }
        .esp-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.10) !important; }

        /* Ã¢â‚¬â€ Carte Ajouter Ã¢â‚¬â€ */
        .esp-add-card {
            flex-direction: column !important;
            min-height: 0 !important;
            padding: 28px 20px !important;
            background: transparent !important;
            border: 2px dashed #88c6d6 !important;
            border-radius: 14px !important;
            color: #555;
            gap: 6px !important;
        }
        .esp-add-card:hover { background: rgba(136,198,214,0.08) !important; }
        .big-plus-btn { font-size: 36px !important; color: #88c6d6 !important; }

        /* Ã¢â‚¬â€ RÃƒÂ©sumÃƒÂ© haut de page Ã¢â‚¬â€ */
        .esp-top-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .summary-chip {
            background: #d9d9d9;
            border-radius: 12px;
            padding: 16px 14px 12px;
            text-align: center;
            border-top: 3px solid #88c6d6;
        }
        .summary-chip .value { display: block; font-size: 30px; font-weight: 800; margin-bottom: 4px; }
        .summary-chip .chip-label { font-size: 11px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: 0.4px; }

        /* Ã¢â‚¬â€ Feedback Ã¢â‚¬â€ */
        .esp-feedback {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .esp-feedback.success { background: #d4f5c1; color: #1a5c00; border-left: 3px solid #89ff57; }
        .esp-feedback.error   { background: #ffd7d7; color: #7c0000; border-left: 3px solid #ff4444; }

        /* Ã¢â‚¬â€ Statut connexion Ã¢â‚¬â€ */
        .status-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .connection-pill {
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .connection-pill.online  { background: #89ff57; color: #000; }
        .connection-pill.offline { background: #ff4444; color: #fff; }
        .signal-label { font-size: 12px; font-weight: 600; color: #555; }

        /* Ã¢â‚¬â€ Meta infos Ã¢â‚¬â€ */
        .module-meta {
            font-size: 13px;
            line-height: 1.6;
            color: #333;
            background: rgba(255,255,255,0.45);
            border-radius: 8px;
            padding: 8px 10px;
        }
        .activity-label {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255,255,255,0.6);
            border-radius: 5px;
            padding: 2px 7px;
        }


        /* Ã¢â‚¬â€ Tags zones Ã¢â‚¬â€ */
        .zone-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
        .zone-tag-form { display: inline-flex; margin: 0; }
        .zone-tag {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(136,198,214,0.28); border: 1.5px solid rgba(136,198,214,0.65);
            border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 600;
            color: #333; cursor: pointer; font-family: inherit; transition: background 0.15s, border-color 0.15s;
        }
        .zone-tag:hover { background: rgba(255,68,68,0.15); border-color: #ff9999; color: #7c0000; }

        /* Ã¢â‚¬â€ Actions carte Ã¢â‚¬â€ */
        .card-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .small-action-btn {
            border: none;
            border-radius: 7px;
            background: rgba(255,255,255,0.5);
            font-size: 12px;
            padding: 6px 10px;
            cursor: pointer;
            font-weight: 600;
            font-family: inherit;
            transition: background 0.15s;
        }
        .small-action-btn:hover    { background: rgba(255,255,255,0.85); }
        .small-action-btn:disabled { opacity: 0.35; cursor: not-allowed; }
        .card-plus-btn {
            width: 36px !important; height: 36px !important;
            border-radius: 8px !important;
            background: #000 !important;
            color: #fff !important;
            font-size: 18px !important;
            flex-shrink: 0;
        }

        /* Ã¢â‚¬â€ Modal Ã¢â‚¬â€ */
        .esp-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 2200; }
        .esp-modal-overlay.active { display: flex; }
        .esp-modal { width: min(480px, 92vw); background: #fff; border-radius: 16px; padding: 24px; position: relative; box-shadow: 0 8px 32px rgba(0,0,0,0.14); }
        .esp-modal-close { position: absolute; right: 14px; top: 10px; background: none; border: none; font-size: 22px; cursor: pointer; color: #888; line-height: 1; }
        .esp-modal-close:hover { color: #000; }
        .esp-modal h2 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
        .esp-modal .help { font-size: 13px; color: #666; margin-bottom: 16px; }
        .esp-modal-form { display: flex; flex-direction: column; gap: 10px; }
        .esp-modal-form label { font-weight: 700; font-size: 13px; color: #333; }
        .esp-modal-form input, .esp-modal-form select { border: 1.5px solid #d8d8d8; border-radius: 10px; padding: 10px 14px; font-size: 14px; width: 100%; outline: none; font-family: inherit; transition: border-color 0.15s; }
        .esp-modal-form input:focus, .esp-modal-form select:focus { border-color: #88c6d6; }
        .esp-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
        .esp-btn-secondary { background: #e5e5e5; color: #000; border: none; border-radius: 10px; padding: 9px 16px; cursor: pointer; font-weight: 700; font-size: 14px; font-family: inherit; }
        .esp-btn-secondary:hover { background: #d5d5d5; }
        .esp-btn-primary    { background: #000;    color: #fff; border: none; border-radius: 10px; padding: 9px 16px; cursor: pointer; font-weight: 700; font-size: 14px; font-family: inherit; }
        .esp-btn-primary:hover { opacity: 0.82; }

        @media (max-width: 1200px) { .esp-top-summary { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 700px)  { .esp-top-summary { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-body">

    <header class="site-header">
        <nav class="navbar">
            <div class="logo-lycee">
                <a href="index.php">
                    <span class="logo-mark">CDI</span>
                    CDI <span class="logo-separator">-</span> Lycee
                </a>
            </div>

            <ul class="nav-links">
                <?php if ($liveRole === 1): ?>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="cdi.php">Zone CDI</a></li>
                    <li><a href="esp.php" class="active">Modules ESP</a></li>
                    <li><a href="vehicule.php">V&eacute;hicule</a></li>
                    <li><a href="radio.php">Salle radio</a></li>
                    <li><a href="mobile.php">Classe mobile</a></li>
                    <li><a href="reservation_validation.php">Confirmation</a></li>
                    <li><a href="register.php">Cr&eacute;er un compte</a></li>
                    <li><a href="logout.php">D&eacute;connexion</a></li>
                <?php else: ?>
                    <li><a href="cdi.php">Zone CDI</a></li>
                    <li><a href="esp.php" class="active">Modules ESP</a></li>
                    <li><a href="logout.php">D&eacute;connexion</a></li>
                <?php endif; ?>
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
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['total_modules']; ?></span><span class="chip-label">Total modules</span></article>
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['online_modules']; ?></span><span class="chip-label">En ligne</span></article>
            <article class="summary-chip"><span class="value"><?php echo (int)$summary['offline_modules']; ?></span><span class="chip-label">Hors ligne</span></article>
        </section>

        <div class="esp-grid">
            <?php foreach ($modules as $module):
                $displayName = resolveModuleDisplayName(
                    (string)($module['nom_module'] ?? ''),
                    (int)$module['id'],
                    $zoneNames
                );
                $moduleZones = $module['zones'] ?? [];
                $isAssigned = !empty($moduleZones);
                $connectionClass = $module['is_online'] ? 'online' : 'offline';
                $connectionText = $module['is_online'] ? 'Connecte' : 'Hors ligne';
            ?>
                <article class="esp-card">
                    <div class="esp-card-top">
                        <div>
                            <h2 class="module-title"><?php echo htmlspecialchars((string)$displayName); ?></h2>
                            <?php if (!empty($moduleZones)): ?>
                                <div class="zone-tags">
                                    <?php foreach ($moduleZones as $z): ?>
                                        <form method="POST" class="zone-tag-form">
                                            <input type="hidden" name="action" value="remove_zone">
                                            <input type="hidden" name="module_id" value="<?php echo (int)$module['id']; ?>">
                                            <input type="hidden" name="zone_id" value="<?php echo (int)$z['id']; ?>">
                                            <button type="submit" class="zone-tag" title="Retirer cette zone"><?php echo htmlspecialchars($z['nom']); ?> x</button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="module-zone">Non associe</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="status-row">
                        <span class="connection-pill <?php echo $connectionClass; ?>">
                            <?php echo $module['is_online'] ? 'o' : '!'; ?>
                            <?php echo $connectionText; ?>
                        </span>
                        <span class="signal-label">Signal: <?php echo htmlspecialchars(formatSecondsAgo($module['secondes_depuis_signal'])); ?></span>
                    </div>

                    <div class="module-meta">
                        <div><strong>IP:</strong> <?php echo htmlspecialchars($module['ip_address'] !== '' ? $module['ip_address'] : '--'); ?></div>
                        <div><strong>Dernier signal:</strong> <?php echo htmlspecialchars(formatLastSignal($module['dernier_signal'])); ?></div>
                        <div><strong>Derniere activite:</strong> <?php echo htmlspecialchars((string)($module['derniere_activite'] ?? '--')); ?></div>
                    </div>

                    <div class="esp-card-bottom">
                        <div class="card-actions">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="disconnect_module">
                                <input type="hidden" name="module_id" value="<?php echo (int)$module['id']; ?>">
                                <button class="small-action-btn" type="submit" <?php echo $isAssigned ? '' : 'disabled'; ?>>Tout desassocier</button>
                            </form>

                            <?php if ($canAddModule): ?>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Supprimer definitivement ce module ?');">
                                    <input type="hidden" name="action" value="delete_module">
                                    <input type="hidden" name="module_id" value="<?php echo (int)$module['id']; ?>">
                                    <button class="small-action-btn" type="submit">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <button
                            class="card-plus-btn open-assign-modal"
                            type="button"
                            aria-label="Ajouter une zone"
                            data-module-id="<?php echo (int)$module['id']; ?>"
                            data-module-name="<?php echo htmlspecialchars((string)$displayName, ENT_QUOTES); ?>"
                            data-zones="<?php echo htmlspecialchars(json_encode($moduleZones, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>"
                        >
                            +
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
            <h2>Ajouter une ou plusieurs zones</h2>
            <p class="help" id="assignHelpText">Selectionnez une ou plusieurs zones a associer.</p>
            <div id="assignCurrentZones" style="margin-bottom:12px;display:none;">
                <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:6px;">Zones deja associees :</p>
                <div id="assignCurrentZonesList" class="zone-tags"></div>
            </div>

            <form method="POST" class="esp-modal-form">
                <input type="hidden" name="action" value="assign_module_zone">
                <input type="hidden" name="module_id" id="assignModuleIdInput" value="">

                <label for="assignZoneSelect">Zones a associer</label>
                <select id="assignZoneSelect" name="zone_ids[]" <?php echo empty($zones) ? 'disabled' : ''; ?> required multiple size="6">
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo (int)$zone['id_zone']; ?>"><?php echo htmlspecialchars((string)$zone['nom_zone']); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="esp-modal-actions">
                    <button type="button" class="esp-btn-secondary" data-close-modal="assignZoneModal">Annuler</button>
                    <button type="submit" class="esp-btn-primary" <?php echo empty($zones) ? 'disabled' : ''; ?>>Ajouter</button>
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

        const assignCurrentZones = document.getElementById('assignCurrentZones');
        const assignCurrentZonesList = document.getElementById('assignCurrentZonesList');
        const assignZoneSelect = document.getElementById('assignZoneSelect');

        document.querySelectorAll('.open-assign-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const moduleId = button.dataset.moduleId || '';
                const moduleName = button.dataset.moduleName || 'ce module';
                const zones = JSON.parse(button.dataset.zones || '[]');
                const assignedIds = new Set(zones.map(z => String(z.id)));

                if (assignModuleIdInput) assignModuleIdInput.value = moduleId;
                if (assignHelpText) assignHelpText.textContent = `Ajouter une ou plusieurs zones a ${moduleName}.`;

                if (assignCurrentZonesList) {
                    assignCurrentZonesList.innerHTML = zones.length
                        ? zones.map(z => `<span class="zone-tag" style="cursor:default">${z.nom}</span>`).join('')
                        : '';
                }
                if (assignCurrentZones) {
                    assignCurrentZones.style.display = zones.length ? 'block' : 'none';
                }

                if (assignZoneSelect) {
                    Array.from(assignZoneSelect.options).forEach(opt => {
                        opt.disabled = assignedIds.has(opt.value);
                        opt.selected = false;
                    });
                }

                if (assignZoneModal) assignZoneModal.classList.add('active');
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
