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

function redirectToLoginForDbIssue(): void
{
    session_unset();
    session_destroy();
    header("Location: login.php?error=db");
    exit();
}

$zoneCreateError = '';
$showModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_zone') {
        $nom_zone = trim($_POST['nom_zone'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($nom_zone === '') {
            $zoneCreateError = "Le nom est obligatoire.";
            $showModal = true;
        } else {
            try {
                $nextZoneId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM genre")->fetchColumn();
                if ($nextZoneId <= 0) {
                    $nextZoneId = 1;
                }

                $stmt = $pdo->prepare("INSERT INTO genre (id, nom, description) VALUES (?, ?, ?)");
                $stmt->execute([$nextZoneId, $nom_zone, $description]);

                header("Location: cdi.php?zone=$nextZoneId&success=zone_added");
                exit();
            } catch (PDOException $e) {
                $zoneCreateError = "Erreur base de donnees.";
                $showModal = true;
            }
        }
    } elseif ($action === 'update_zone_name') {
        $zoneId = (int)($_POST['id_zone'] ?? 0);
        $newZoneName = trim($_POST['new_nom_zone'] ?? '');

        if ($zoneId > 0 && $newZoneName !== '') {
            try {
                $stmt = $pdo->prepare("UPDATE genre SET nom = ? WHERE id = ?");
                $stmt->execute([$newZoneName, $zoneId]);

                header("Location: cdi.php?zone=$zoneId&success=zone_updated");
                exit();
            } catch (PDOException $e) {
                header("Location: cdi.php?zone=$zoneId&error=zone_update");
                exit();
            }
        }

        header("Location: cdi.php?zone=$zoneId&error=zone_update");
        exit();
    } elseif ($action === 'update_resource_title') {
        $zoneId = (int)($_POST['zone'] ?? 0);
        $resourceId = (int)($_POST['id_ressources'] ?? 0);
        $newTitle = trim($_POST['new_titre'] ?? '');

        if ($zoneId > 0 && $resourceId > 0 && $newTitle !== '') {
            try {
                $stmt = $pdo->prepare("
                    UPDATE livre l
                    INNER JOIN bloc b ON b.id = l.id_bloc
                    SET l.titre = ?
                    WHERE l.id = ? AND b.genre = ?
                ");
                $stmt->execute([$newTitle, $resourceId, $zoneId]);

                header("Location: cdi.php?zone=$zoneId&success=resource_updated");
                exit();
            } catch (PDOException $e) {
                header("Location: cdi.php?zone=$zoneId&error=resource_update");
                exit();
            }
        }

        header("Location: cdi.php?zone=$zoneId&error=resource_update");
        exit();
    } elseif ($action === 'delete_zone') {
        $zoneId = (int)($_POST['id_zone'] ?? 0);

        if ($zoneId > 0) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE bloc SET genre = NULL WHERE genre = ?");
                $stmt->execute([$zoneId]);

                $stmt = $pdo->prepare("DELETE FROM genre WHERE id = ?");
                $stmt->execute([$zoneId]);

                $pdo->commit();

                $nextZoneId = $pdo->query("SELECT id FROM genre ORDER BY nom ASC LIMIT 1")->fetchColumn();
                $location = "cdi.php?success=zone_deleted";
                if ($nextZoneId !== false) {
                    $location .= "&zone=" . (int)$nextZoneId;
                }

                header("Location: $location");
                exit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                header("Location: cdi.php?zone=$zoneId&error=zone_delete");
                exit();
            }
        }

        header("Location: cdi.php?error=zone_delete");
        exit();
    }
}

$currentZoneId = isset($_GET['zone']) ? (int)$_GET['zone'] : 0;
$zones = [];
$zoneInfo = [];
$hasCurrentZone = false;
$zoneModules = [];
$zoneModuleRows = [];
$zoneModuleIds = [];
$resourcesByModule = [];

try {
    $zones = $pdo->query("
        SELECT
            g.id AS id_zone,
            g.nom AS nom_zone,
            g.description,
            COUNT(DISTINCT b.id_zone) AS modules_count
        FROM genre g
        LEFT JOIN bloc b ON b.genre = g.id
        GROUP BY g.id, g.nom, g.description
        ORDER BY g.nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($currentZoneId === 0 && !empty($zones)) {
        $currentZoneId = (int)$zones[0]['id_zone'];
    }

    $stmt = $pdo->prepare(
        "SELECT
            g.id AS id_zone,
            g.nom AS nom_zone,
            g.description
        FROM genre g
        WHERE g.id = ?"
    );
    $stmt->execute([$currentZoneId]);
    $zoneInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasCurrentZone = !empty($zoneInfo) && isset($zoneInfo['id_zone']);

    $stmtZoneModules = $pdo->prepare("
        SELECT z.id AS id_module, z.ip_address
        FROM zone z
        INNER JOIN bloc b ON b.id_zone = z.id
        WHERE b.genre = ?
        ORDER BY z.id ASC
    ");
    $stmtZoneModules->execute([$currentZoneId]);
    $zoneModules = $stmtZoneModules->fetchAll(PDO::FETCH_ASSOC);

    foreach ($zoneModules as $module) {
        $moduleId = trim((string)($module['id_module'] ?? ''));
        $ipAddress = trim((string)($module['ip_address'] ?? ''));

        if ($moduleId !== '') {
            $zoneModuleRows[] = [
                'id_module' => $moduleId,
                'label' => 'ESP ' . ($ipAddress !== '' ? $ipAddress : $moduleId),
            ];
            $zoneModuleIds[] = $moduleId;
        }
    }

    if (!empty($zoneModuleIds)) {
        $modulePlaceholders = implode(',', array_fill(0, count($zoneModuleIds), '?'));
        $stmtResByModule = $pdo->prepare("
            SELECT z.id AS id_module, l.id, l.titre
            FROM livre l
            INNER JOIN bloc b ON b.id = l.id_bloc
            INNER JOIN zone z ON z.id = b.id_zone
            WHERE z.id IN ($modulePlaceholders) AND b.genre = ?
            ORDER BY z.id ASC, l.titre ASC
        ");
        $executeParams = $zoneModuleIds;
        $executeParams[] = (string)$currentZoneId;
        $stmtResByModule->execute($executeParams);
        $moduleResourcesRows = $stmtResByModule->fetchAll(PDO::FETCH_ASSOC);

        foreach ($moduleResourcesRows as $moduleResource) {
            $moduleKey = trim((string)($moduleResource['id_module'] ?? ''));
            if ($moduleKey === '') {
                continue;
            }

            if (!isset($resourcesByModule[$moduleKey])) {
                $resourcesByModule[$moduleKey] = [];
            }

            $title = trim((string)($moduleResource['titre'] ?? ''));
            $resourcesByModule[$moduleKey][] = [
                'titre' => $title !== '' ? $title : '--',
            ];
        }
    }
} catch (PDOException $e) {
    redirectToLoginForDbIssue();
}

$feedbackMessage = '';
$feedbackBg = '#89ff57';

if (isset($_GET['success'])) {
    $successCode = $_GET['success'];

    if ($successCode === 'zone_added') {
        $feedbackMessage = 'Zone ajoutee avec succes.';
    } elseif ($successCode === 'zone_updated') {
        $feedbackMessage = 'Nom de la zone modifie.';
    } elseif ($successCode === 'resource_updated') {
        $feedbackMessage = 'Nom du livre modifie.';
    } elseif ($successCode === 'zone_deleted') {
        $feedbackMessage = 'Zone supprimee.';
    }
}

if ($feedbackMessage === '' && isset($_GET['error'])) {
    $feedbackBg = '#ff8989';
    $errorCode = $_GET['error'];

    if ($errorCode === 'zone_update') {
        $feedbackMessage = 'Impossible de modifier le nom de la zone.';
    } elseif ($errorCode === 'resource_update') {
        $feedbackMessage = 'Impossible de modifier le nom du livre.';
    } elseif ($errorCode === 'zone_delete') {
        $feedbackMessage = 'Impossible de supprimer la zone.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion CDI - Zone CDI</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
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
                <li><a href="cdi.php" class="active">Zone CDI</a></li>
                <li><a href="esp.php">Modules ESP</a></li>
                <li><a href="logout.php">Deconnexion</a></li>
                <li class="admin-pill">Admin <?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?></li>
            </ul>
        </nav>
    </header>

    <div class="main-wrapper">
        <aside class="sidebar">
            <h2 class="sidebar-title">Genres du CDI</h2>

            <?php foreach ($zones as $z):
                $isConnected = ((int)($z['modules_count'] ?? 0) > 0);
            ?>
                <a href="cdi.php?zone=<?php echo (int)$z['id_zone']; ?>" class="module-item <?php echo ((int)$z['id_zone'] === $currentZoneId) ? 'active' : ''; ?>" style="text-decoration:none; color:inherit;">
                    <span class="module-name"><?php echo htmlspecialchars($z['nom_zone']); ?></span>
                    <span class="status-badge <?php echo $isConnected ? 'connected' : 'disconnected'; ?>">
                        <?php echo $isConnected ? 'Connecte' : 'Pas connecte'; ?>
                    </span>
                </a>
            <?php endforeach; ?>

            <div class="add-container">
                <button type="button" class="btn-add" id="openZoneModal">
                    <i class="fa-solid fa-plus"></i> Ajouter une zone
                </button>
            </div>
        </aside>

        <section class="content-area">
            <?php if ($feedbackMessage !== ''): ?>
                <div style="background:<?php echo $feedbackBg; ?>; padding:15px; border-radius:15px; margin-bottom:20px; font-weight:700;">
                    <?php echo htmlspecialchars($feedbackMessage); ?>
                </div>
            <?php endif; ?>

            <div class="content-header-card">
                <div class="module-info">
                    <span class="title">Zone : <?php echo htmlspecialchars($zoneInfo['nom_zone'] ?? 'Aucune'); ?></span>

                    <?php if ($hasCurrentZone): ?>
                        <button
                            type="button"
                            class="btn-icon"
                            id="editZoneBtn"
                            title="Modifier le nom de la zone"
                            data-zone-id="<?php echo (int)$zoneInfo['id_zone']; ?>"
                            data-zone-name="<?php echo htmlspecialchars($zoneInfo['nom_zone'], ENT_QUOTES); ?>"
                        >
                            <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                    <?php endif; ?>

                </div>

                <?php if ($hasCurrentZone): ?>
                    <button
                        type="button"
                        id="openDeleteModalBtn"
                        class="btn-delete"
                        title="Supprimer la zone"
                        data-zone-id="<?php echo (int)$zoneInfo['id_zone']; ?>"
                        data-zone-name="<?php echo htmlspecialchars($zoneInfo['nom_zone'], ENT_QUOTES); ?>"
                    >
                        <i class="fa-regular fa-trash-can"></i>
                    </button>
                <?php endif; ?>
            </div>

            <div class="panels-container">
                <div class="panel esp-associated-panel">
                    <div class="panel-title">ESP associes</div>

                    <?php if (!empty($zoneModuleRows)): ?>
                        <div class="esp-associated-list">
                            <?php foreach ($zoneModuleRows as $moduleRow):
                                $moduleResourceList = $resourcesByModule[$moduleRow['id_module']] ?? [];
                                $titleColumns = 3;
                            ?>
                                <div class="esp-group">
                                    <div class="esp-module-name"><?php echo htmlspecialchars($moduleRow['label']); ?></div>
                                    <div class="esp-table-wrap">
                                        <table class="esp-resource-table">
                                            <thead>
                                                <tr>
                                                    <th colspan="<?php echo $titleColumns; ?>">Titre</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                        <?php if (!empty($moduleResourceList)): ?>
                                            <?php
                                                $titles = array_map(
                                                    static fn(array $resourceItem): string => (string)($resourceItem['titre'] ?? '--'),
                                                    $moduleResourceList
                                                );
                                                $titleRows = array_chunk($titles, $titleColumns);
                                            ?>
                                            <?php foreach ($titleRows as $titleRow): ?>
                                                <tr>
                                                    <?php for ($col = 0; $col < $titleColumns; $col++): ?>
                                                        <td><?php echo htmlspecialchars($titleRow[$col] ?? ''); ?></td>
                                                    <?php endfor; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                                <tr>
                                                    <td colspan="<?php echo $titleColumns; ?>" class="esp-line-empty">Aucune ressource liee</td>
                                                </tr>
                                        <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="esp-associated-empty-state">Aucun module ESP associe</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="modal-overlay <?php echo $showModal ? 'active' : ''; ?>" id="modalZone">
        <div class="modal-card">
            <button class="modal-close" id="closeZoneModal">&times;</button>
            <h2 style="text-align:center;">Ajouter une zone</h2>

            <?php if ($zoneCreateError): ?>
                <div class="form-feedback error"><?php echo htmlspecialchars($zoneCreateError); ?></div>
            <?php endif; ?>

            <form method="POST" class="zone-form">
                <input type="hidden" name="action" value="add_zone">

                <label>Nom de la zone</label>
                <input type="text" name="nom_zone" required placeholder="Ex: Rayon BD" value="<?php echo htmlspecialchars($_POST['nom_zone'] ?? ''); ?>">

                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Description de l'emplacement..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

                <button type="submit" class="btn-submit">ENREGISTRER</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalEdit">
        <div class="modal-card">
            <button class="modal-close" data-close-modal="modalEdit">&times;</button>
            <h2 id="editModalTitle" style="text-align:center;">Modifier</h2>

            <form method="POST" class="zone-form" id="editModalForm">
                <input type="hidden" name="action" id="editActionInput" value="">
                <input type="hidden" name="id_zone" id="editZoneIdInput" value="">
                <input type="hidden" name="zone" id="editCurrentZoneInput" value="<?php echo (int)$currentZoneId; ?>">
                <input type="hidden" name="id_ressources" id="editResourceIdInput" value="">
                <input type="hidden" name="new_nom_zone" id="editZoneNameInput" value="">
                <input type="hidden" name="new_titre" id="editResourceTitleInput" value="">

                <label id="editModalLabel" for="editModalValue">Nom</label>
                <input type="text" id="editModalValue" required placeholder="">

                <div class="modal-actions">
                    <button type="button" class="btn-modal-secondary" data-close-modal="modalEdit">Annuler</button>
                    <button type="submit" class="btn-modal-danger">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalDelete">
        <div class="modal-card">
            <button class="modal-close" data-close-modal="modalDelete">&times;</button>
            <h2 style="text-align:center;">Supprimer la zone</h2>
            <p class="modal-subtext" id="deleteModalText"></p>

            <div class="modal-actions">
                <button type="button" class="btn-modal-secondary" data-close-modal="modalDelete">Annuler</button>
                <button type="button" class="btn-modal-danger" id="confirmDeleteBtn">Supprimer</button>
            </div>
        </div>
    </div>

    <form method="POST" id="deleteZoneForm" class="hidden-form">
        <input type="hidden" name="action" value="delete_zone">
        <input type="hidden" name="id_zone" id="deleteZoneIdInput" value="">
    </form>

    <script>
        const modal = document.getElementById('modalZone');
        const modalEdit = document.getElementById('modalEdit');
        const modalDelete = document.getElementById('modalDelete');
        const openBtn = document.getElementById('openZoneModal');
        const closeBtn = document.getElementById('closeZoneModal');

        if (openBtn && modal) {
            openBtn.addEventListener('click', () => {
                modal.classList.add('active');
            });
        }

        if (closeBtn && modal) {
            closeBtn.addEventListener('click', () => {
                modal.classList.remove('active');
            });
        }

        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('active');
            }
            if (event.target === modalEdit) {
                modalEdit.classList.remove('active');
            }
            if (event.target === modalDelete) {
                modalDelete.classList.remove('active');
            }
        });

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.closeModal;
                const targetModal = document.getElementById(targetId);
                if (targetModal) {
                    targetModal.classList.remove('active');
                }
            });
        });

        const editZoneBtn = document.getElementById('editZoneBtn');
        const editModalForm = document.getElementById('editModalForm');
        const editActionInput = document.getElementById('editActionInput');
        const editModalTitle = document.getElementById('editModalTitle');
        const editModalLabel = document.getElementById('editModalLabel');
        const editModalValue = document.getElementById('editModalValue');
        const editZoneIdInput = document.getElementById('editZoneIdInput');
        const editZoneNameInput = document.getElementById('editZoneNameInput');
        const editCurrentZoneInput = document.getElementById('editCurrentZoneInput');
        const editResourceIdInput = document.getElementById('editResourceIdInput');
        const editResourceTitleInput = document.getElementById('editResourceTitleInput');

        const openEditModal = ({ action, title, label, value, zoneId, resourceId }) => {
            if (!modalEdit || !editModalForm) {
                return;
            }

            editActionInput.value = action;
            editModalTitle.textContent = title;
            editModalLabel.textContent = label;
            editModalValue.value = value;
            editModalValue.focus();

            editZoneIdInput.value = zoneId || '';
            editResourceIdInput.value = resourceId || '';
            editZoneNameInput.value = '';
            editResourceTitleInput.value = '';
            if (editCurrentZoneInput && !editCurrentZoneInput.value) {
                editCurrentZoneInput.value = '<?php echo (int)$currentZoneId; ?>';
            }

            modalEdit.classList.add('active');
        };

        if (editZoneBtn) {
            editZoneBtn.addEventListener('click', () => {
                const zoneId = editZoneBtn.dataset.zoneId || '';
                const currentName = editZoneBtn.dataset.zoneName || '';
                openEditModal({
                    action: 'update_zone_name',
                    title: 'Modifier la zone',
                    label: 'Nouveau nom de la zone',
                    value: currentName,
                    zoneId: zoneId,
                    resourceId: ''
                });
            });
        }

        const resourceButtons = document.querySelectorAll('.edit-resource-btn');

        if (resourceButtons.length > 0) {
            resourceButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const resourceId = button.dataset.resourceId || '';
                    const currentTitle = button.dataset.resourceTitle || '';
                    openEditModal({
                        action: 'update_resource_title',
                        title: 'Modifier le livre',
                        label: 'Nouveau titre du livre',
                        value: currentTitle,
                        zoneId: '',
                        resourceId: resourceId
                    });
                });
            });
        }

        if (editModalForm) {
            editModalForm.addEventListener('submit', (event) => {
                const value = editModalValue.value.trim();
                if (value === '') {
                    event.preventDefault();
                    editModalValue.focus();
                    return;
                }

                if (editActionInput.value === 'update_zone_name') {
                    editZoneNameInput.value = value;
                    editResourceTitleInput.value = '';
                } else if (editActionInput.value === 'update_resource_title') {
                    editResourceTitleInput.value = value;
                    editZoneNameInput.value = '';
                } else {
                    event.preventDefault();
                }
            });
        }

        const openDeleteModalBtn = document.getElementById('openDeleteModalBtn');
        const deleteModalText = document.getElementById('deleteModalText');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const deleteZoneForm = document.getElementById('deleteZoneForm');
        const deleteZoneIdInput = document.getElementById('deleteZoneIdInput');

        if (openDeleteModalBtn && modalDelete && deleteModalText && deleteZoneIdInput) {
            openDeleteModalBtn.addEventListener('click', () => {
                const zoneId = openDeleteModalBtn.dataset.zoneId || '';
                const zoneName = openDeleteModalBtn.dataset.zoneName || 'cette zone';

                deleteZoneIdInput.value = zoneId;
                deleteModalText.textContent = `Voulez-vous vraiment supprimer la zone "${zoneName}" ?`;
                modalDelete.classList.add('active');
            });
        }

        if (confirmDeleteBtn && deleteZoneForm) {
            confirmDeleteBtn.addEventListener('click', () => {
                deleteZoneForm.submit();
            });
        }

    </script>
</body>
</html>
