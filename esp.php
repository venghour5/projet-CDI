<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

if ((int)($_SESSION['role'] ?? -1) !== 1) {
    header("Location: vehicule.php");
    exit();
}

function generateModuleId(PDO $pdo): int
{
    $nextId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM zone")->fetchColumn();
    return $nextId > 0 ? $nextId : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_module') {
        $ipAddress = trim($_POST['ip_address'] ?? '');

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            header("Location: esp.php?error=invalid_ip");
            exit();
        }

        try {
            $newModuleId = generateModuleId($pdo);
            $stmt = $pdo->prepare("INSERT INTO zone (id, ip_address, etat_batterie, dernier_signal, nombre_section) VALUES (?, ?, 100, NOW(), 1)");
            $stmt->execute([$newModuleId, $ipAddress]);

            header("Location: esp.php?success=module_added");
            exit();
        } catch (PDOException $e) {
            header("Location: esp.php?error=module_add");
            exit();
        }
    }

    if ($action === 'assign_module_zone') {
        $moduleId = (int)($_POST['module_id'] ?? 0);
        $zoneId = (int)($_POST['zone_id'] ?? 0);

        if ($moduleId <= 0 || $zoneId <= 0) {
            header("Location: esp.php?error=assign");
            exit();
        }

        try {
            $zoneExistsStmt = $pdo->prepare("SELECT 1 FROM genre WHERE id = ? LIMIT 1");
            $zoneExistsStmt->execute([$zoneId]);
            if (!$zoneExistsStmt->fetchColumn()) {
                header("Location: esp.php?error=assign");
                exit();
            }

            $moduleExistsStmt = $pdo->prepare("SELECT 1 FROM zone WHERE id = ? LIMIT 1");
            $moduleExistsStmt->execute([$moduleId]);
            if (!$moduleExistsStmt->fetchColumn()) {
                header("Location: esp.php?error=assign");
                exit();
            }

            $existingBlocStmt = $pdo->prepare("SELECT id FROM bloc WHERE id_zone = ? LIMIT 1");
            $existingBlocStmt->execute([$moduleId]);
            $existingBlocId = $existingBlocStmt->fetchColumn();

            if ($existingBlocId !== false) {
                $stmt = $pdo->prepare("UPDATE bloc SET genre = ? WHERE id = ?");
                $stmt->execute([$zoneId, (int)$existingBlocId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO bloc (id_zone, section, alphabet_start, alphabet_end, genre) VALUES (?, 1, 'A', 'Z', ?)");
                $stmt->execute([$moduleId, $zoneId]);
            }

            header("Location: esp.php?success=module_assigned");
            exit();
        } catch (PDOException $e) {
            header("Location: esp.php?error=assign");
            exit();
        }
    }

    if ($action === 'disconnect_module') {
        $moduleId = (int)($_POST['module_id'] ?? 0);

        if ($moduleId <= 0) {
            header("Location: esp.php?error=disconnect");
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE bloc SET genre = NULL WHERE id_zone = ?");
            $stmt->execute([$moduleId]);

            header("Location: esp.php?success=module_disconnected");
            exit();
        } catch (PDOException $e) {
            header("Location: esp.php?error=disconnect");
            exit();
        }
    }
}

$sqlModules = "
    SELECT
        z.id AS id_module,
        z.ip_address,
        z.etat_batterie,
        z.dernier_signal,
        CASE WHEN b.genre IS NULL THEN 'offline' ELSE 'online' END AS statut,
        b.genre AS id_zone,
        g.nom AS nom_zone
    FROM zone z
    LEFT JOIN bloc b ON b.id_zone = z.id
    LEFT JOIN genre g ON g.id = b.genre
    ORDER BY g.nom ASC, z.id ASC
";
$modules = $pdo->query($sqlModules)->fetchAll(PDO::FETCH_ASSOC);

$zones = $pdo->query("SELECT id AS id_zone, nom AS nom_zone FROM genre ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

$feedbackMessage = '';
$feedbackBg = '#89ff57';

if (isset($_GET['success'])) {
    $successCode = $_GET['success'];
    if ($successCode === 'module_added') {
        $feedbackMessage = 'Module ajoute avec succes.';
    } elseif ($successCode === 'module_assigned') {
        $feedbackMessage = 'Module associe a la zone.';
    } elseif ($successCode === 'module_disconnected') {
        $feedbackMessage = 'Module deconnecte de la zone.';
    }
}

if ($feedbackMessage === '' && isset($_GET['error'])) {
    $feedbackBg = '#ff8989';
    $errorCode = $_GET['error'];

    if ($errorCode === 'invalid_ip') {
        $feedbackMessage = 'Adresse IP invalide.';
    } elseif ($errorCode === 'module_add') {
        $feedbackMessage = 'Impossible d ajouter le module.';
    } elseif ($errorCode === 'assign') {
        $feedbackMessage = 'Impossible d associer le module.';
    } elseif ($errorCode === 'disconnect') {
        $feedbackMessage = 'Impossible de deconnecter le module.';
    }
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
    <link rel="stylesheet" href="style.css" />
    <style>
        .esp-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2200;
        }

        .esp-modal-overlay.active {
            display: flex;
        }

        .esp-modal {
            width: min(500px, 92vw);
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

        .esp-modal-form label {
            font-weight: 700;
        }

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

        .esp-btn-secondary {
            background: #b8b8b8;
            color: #000;
        }

        .esp-btn-primary {
            background: #000;
            color: #fff;
        }

        .esp-feedback {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .esp-card-bottom .inline-form {
            flex: 1;
        }

        .disconnect-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
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
                <li><a href="cdi.php">Zone CDI</a></li>
                <li><a href="esp.php" class="active">Modules ESP</a></li>
                <li><a href="logout.php">Deconnexion</a></li>
                <li class="admin-pill">Admin <?php echo htmlspecialchars($_SESSION['login']); ?></li>
            </ul>
        </nav>
    </header>

    <main class="esp-page">
        <?php if ($feedbackMessage !== ''): ?>
            <div class="esp-feedback" style="background: <?php echo $feedbackBg; ?>;">
                <?php echo htmlspecialchars($feedbackMessage); ?>
            </div>
        <?php endif; ?>

        <div class="esp-grid">
            <?php foreach ($modules as $module):
                $battery = isset($module['etat_batterie']) ? (int)$module['etat_batterie'] : null;
                $batteryDisplay = ($battery === null) ? '--' : ($battery . '%');
                $isLowBattery = ($battery !== null && $battery <= 20);
                $ipAddress = trim((string)($module['ip_address'] ?? ''));
                $zoneName = $module['nom_zone'] ?? 'Non associe';
                $title = 'esp ' . ($ipAddress !== '' ? $ipAddress : $module['id_module']);
                $isAssigned = !empty($module['id_zone']);
            ?>
                <article class="esp-card">
                    <div class="esp-card-top">
                        <div>
                            <h2 class="module-title"><?php echo htmlspecialchars($title); ?></h2>
                            <p class="module-zone"><?php echo htmlspecialchars($zoneName); ?></p>
                        </div>

                        <div class="battery-badge <?php echo $isLowBattery ? 'low' : 'full'; ?>">
                            <?php if ($isLowBattery): ?>
                                <span class="battery-dot"></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($batteryDisplay); ?></span>
                        </div>
                    </div>

                    <div class="esp-card-bottom">
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="disconnect_module">
                            <input type="hidden" name="module_id" value="<?php echo htmlspecialchars($module['id_module']); ?>">
                            <button class="disconnect-btn" type="submit" <?php echo $isAssigned ? '' : 'disabled'; ?>>Deconnecter</button>
                        </form>

                        <button
                            class="card-plus-btn open-assign-modal"
                            type="button"
                            aria-label="Associer a une zone"
                            data-module-id="<?php echo htmlspecialchars($module['id_module'], ENT_QUOTES); ?>"
                            data-module-name="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"
                        >
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>

            <button id="openAddModuleModal" class="esp-add-card" type="button" aria-label="Ajouter un module">
                <span class="big-plus-btn">+</span>
            </button>
        </div>
    </main>

    <div class="esp-modal-overlay" id="addModuleModal">
        <div class="esp-modal">
            <button class="esp-modal-close" type="button" data-close-modal="addModuleModal">&times;</button>
            <h2>Ajouter un module ESP</h2>
            <p class="help">Saisissez l adresse IP du module a enregistrer.</p>

            <form method="POST" class="esp-modal-form">
                <input type="hidden" name="action" value="add_module">

                <label for="moduleIpInput">Adresse IP</label>
                <input id="moduleIpInput" type="text" name="ip_address" placeholder="Ex: 10.1.1.4" required>

                <div class="esp-modal-actions">
                    <button type="button" class="esp-btn-secondary" data-close-modal="addModuleModal">Annuler</button>
                    <button type="submit" class="esp-btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="esp-modal-overlay" id="assignZoneModal">
        <div class="esp-modal">
            <button class="esp-modal-close" type="button" data-close-modal="assignZoneModal">&times;</button>
            <h2>Associer un module</h2>
            <p class="help" id="assignHelpText">Selectionnez une zone.</p>

            <form method="POST" class="esp-modal-form">
                <input type="hidden" name="action" value="assign_module_zone">
                <input type="hidden" name="module_id" id="assignModuleIdInput" value="">

                <label for="assignZoneSelect">Zone</label>
                <select id="assignZoneSelect" name="zone_id" <?php echo empty($zones) ? 'disabled' : ''; ?> required>
                    <option value="">Choisir une zone</option>
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo (int)$zone['id_zone']; ?>"><?php echo htmlspecialchars($zone['nom_zone']); ?></option>
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
                    assignHelpText.textContent = `Associer ${moduleName} a une zone.`;
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
