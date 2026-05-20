<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

$roleStmt = $pdo->prepare('SELECT role FROM utilisateur WHERE id = ? LIMIT 1');
$roleStmt->execute([(int)$_SESSION['id_user']]);
$liveRole = (int)($roleStmt->fetchColumn() ?: -1);
$_SESSION['role'] = $liveRole;

if (!in_array($liveRole, [1, 2], true)) {
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
                $zoneCreateError = "Erreur base de données.";
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
    } elseif ($action === 'add_resource_manual') {
        $zoneId = (int)($_POST['zone'] ?? 0);
        $moduleId = (int)($_POST['module_id'] ?? 0);
        $blocIdInput = (int)($_POST['id_bloc'] ?? 0);
        $title = trim($_POST['titre'] ?? '');
        $author = trim($_POST['auteur'] ?? '');
        $cote = trim($_POST['cote'] ?? '');

        if ($zoneId > 0 && $moduleId > 0 && $blocIdInput > 0 && $title !== '' && $author !== '' && $cote !== '') {
            try {
                $blocStmt = $pdo->prepare('SELECT id FROM bloc WHERE id = ? AND id_zone = ? AND genre = ? LIMIT 1');
                $blocStmt->execute([$blocIdInput, $moduleId, $zoneId]);
                $blocId = $blocStmt->fetchColumn();
                if ($blocId === false) {
                    header("Location: cdi.php?zone=$zoneId&error=resource_add");
                    exit();
                }

                $nextBookId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM livre')->fetchColumn();
                if ($nextBookId <= 0) {
                    $nextBookId = 1;
                }

                $insertStmt = $pdo->prepare('INSERT INTO livre (id, titre, auteur, cote, etat, id_bloc) VALUES (?, ?, ?, ?, 1, ?)');
                $insertStmt->execute([
                    $nextBookId,
                    $title,
                    $author,
                    $cote,
                    (int)$blocId,
                ]);

                header("Location: cdi.php?zone=$zoneId&success=resource_added");
                exit();
            } catch (PDOException $e) {
                header("Location: cdi.php?zone=$zoneId&error=resource_add");
                exit();
            }
        }

        header("Location: cdi.php?zone=$zoneId&error=resource_add");
        exit();
    } elseif ($action === 'delete_resource') {
        $zoneId = (int)($_POST['zone'] ?? 0);
        $resourceId = (int)($_POST['id_ressources'] ?? 0);

        if ($zoneId > 0 && $resourceId > 0) {
            try {
                $stmt = $pdo->prepare("
                    DELETE l
                    FROM livre l
                    INNER JOIN bloc b ON b.id = l.id_bloc
                    WHERE l.id = ? AND b.genre = ?
                ");
                $stmt->execute([$resourceId, $zoneId]);

                header("Location: cdi.php?zone=$zoneId&success=resource_deleted");
                exit();
            } catch (PDOException $e) {
                header("Location: cdi.php?zone=$zoneId&error=resource_delete");
                exit();
            }
        }

        header("Location: cdi.php?zone=$zoneId&error=resource_delete");
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
        SELECT z.id AS id_module, z.ip_address, z.nom_module, b.id AS id_bloc
        FROM zone z
        INNER JOIN bloc b ON b.id_zone = z.id
        WHERE b.genre = ?
        ORDER BY z.id ASC
    ");
    $stmtZoneModules->execute([$currentZoneId]);
    $zoneModules = $stmtZoneModules->fetchAll(PDO::FETCH_ASSOC);

    $moduleDisplayIndex = 0;
    foreach ($zoneModules as $module) {
        $moduleId = trim((string)($module['id_module'] ?? ''));
        $moduleName = trim((string)($module['nom_module'] ?? ''));

        if ($moduleId !== '') {
            $moduleDisplayIndex++;
            $zoneModuleRows[] = [
                'id_module' => $moduleId,
                'id_bloc' => isset($module['id_bloc']) ? (int)$module['id_bloc'] : 0,
                'label' => $moduleName !== '' ? $moduleName : ('Module ' . $moduleDisplayIndex),
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
                'id' => isset($moduleResource['id']) ? (int)$moduleResource['id'] : 0,
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
        $feedbackMessage = 'Zone ajoutée avec succès.';
    } elseif ($successCode === 'zone_updated') {
        $feedbackMessage = 'Nom de la zone modifié.';
    } elseif ($successCode === 'resource_updated') {
        $feedbackMessage = 'Nom du livre modifié.';
    } elseif ($successCode === 'resource_added') {
        $feedbackMessage = 'Livre ajouté avec succès.';
    } elseif ($successCode === 'resource_deleted') {
        $feedbackMessage = 'Livre supprimé avec succès.';
    } elseif ($successCode === 'zone_deleted') {
        $feedbackMessage = 'Zone supprimée.';
    }
}

if ($feedbackMessage === '' && isset($_GET['error'])) {
    $feedbackBg = '#ff8989';
    $errorCode = $_GET['error'];

    if ($errorCode === 'zone_update') {
        $feedbackMessage = 'Impossible de modifier le nom de la zone.';
    } elseif ($errorCode === 'resource_update') {
        $feedbackMessage = 'Impossible de modifier le nom du livre.';
    } elseif ($errorCode === 'resource_add') {
        $feedbackMessage = "Impossible d'ajouter le livre.";
    } elseif ($errorCode === 'resource_delete') {
        $feedbackMessage = 'Impossible de supprimer le livre.';
    } elseif ($errorCode === 'zone_delete') {
        $feedbackMessage = 'Impossible de supprimer la zone.';
    }
}

$styleVersion = (string)(@filemtime(__DIR__ . '/style.css') ?: '1');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion CDI - Zone CDI</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode($styleVersion); ?>" />
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
                <li><a href="cdi.php" class="active">Zone CDI</a></li>
                <li><a href="esp.php">Modules ESP</a></li>
                <li><a href="logout.php">Déconnexion</a></li>
                <li class="admin-pill"><?php echo htmlspecialchars((string)($_SESSION['login'] ?? 'Compte')); ?></li>
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
                        <?php echo $isConnected ? 'Connecté' : 'Pas connecté'; ?>
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
                    <div class="panel-title">ESP associés</div>
                    <p class="esp-associated-subtitle">Vue rapide des modules et des livres rattachés à la zone.</p>

                    <?php if (!empty($zoneModuleRows)): ?>
                        <div class="esp-associated-list">
                            <?php foreach ($zoneModuleRows as $moduleRow):
                                $moduleResourceList = $resourcesByModule[$moduleRow['id_module']] ?? [];
                                $resourceCount = count($moduleResourceList);
                                $titleColumns = 3;
                                $visibleRows = 2;
                                $showBooksToggle = $resourceCount > ($titleColumns * $visibleRows);
                            ?>
                                <div class="esp-group">
                                    <div class="esp-group-header">
                                        <div class="esp-module-actions">
                                            <div class="esp-module-name"><?php echo htmlspecialchars($moduleRow['label']); ?></div>
                                            <button type="button" class="esp-led-btn">Allumer LED</button>
                                            <button
                                                type="button"
                                                class="esp-add-book-btn open-add-book-modal"
                                                data-module-id="<?php echo (int)$moduleRow['id_module']; ?>"
                                                data-bloc-id="<?php echo (int)$moduleRow['id_bloc']; ?>"
                                                data-module-name="<?php echo htmlspecialchars((string)$moduleRow['label'], ENT_QUOTES); ?>"
                                            >
                                                Ajouter livre
                                            </button>
                                        </div>
                                        <span class="esp-resource-count"><?php echo $resourceCount; ?> livre<?php echo $resourceCount > 1 ? 's' : ''; ?></span>
                                    </div>

                                    <?php if (!empty($moduleResourceList)): ?>
                                        <div class="esp-table-wrap <?php echo $showBooksToggle ? 'compact' : ''; ?>" data-books-wrap>
                                            <table class="esp-resource-table">
                                                <thead>
                                                    <tr>
                                                        <th colspan="<?php echo $titleColumns; ?>">Titre</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                        $resourceRows = array_chunk($moduleResourceList, $titleColumns);
                                                    ?>
                                                    <?php foreach ($resourceRows as $resourceRow): ?>
                                                        <tr>
                                                            <?php for ($col = 0; $col < $titleColumns; $col++): ?>
                                                                <?php $bookItem = $resourceRow[$col] ?? null; ?>
                                                                <td>
                                                                    <?php if (is_array($bookItem)): ?>
                                                                        <div class="book-cell-content">
                                                                            <span><?php echo htmlspecialchars((string)($bookItem['titre'] ?? '')); ?></span>
                                                                            <form method="POST" class="inline-form" onsubmit="return confirm('Supprimer ce livre ?');">
                                                                                <input type="hidden" name="action" value="delete_resource">
                                                                                <input type="hidden" name="zone" value="<?php echo (int)$currentZoneId; ?>">
                                                                                <input type="hidden" name="id_ressources" value="<?php echo (int)($bookItem['id'] ?? 0); ?>">
                                                                                <button type="submit" class="book-delete-btn" title="Supprimer le livre">
                                                                                    <i class="fa-regular fa-trash-can"></i>
                                                                                </button>
                                                                            </form>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endfor; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php if ($showBooksToggle): ?>
                                            <button type="button" class="esp-books-toggle-btn" data-books-toggle>Voir plus</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="esp-line-empty">Aucune ressource liée</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="esp-associated-empty-state">Aucun module ESP associé</div>
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

    <div class="modal-overlay" id="modalAddBook">
        <div class="modal-card">
            <button class="modal-close" data-close-modal="modalAddBook">&times;</button>
            <h2 style="text-align:center;">Ajouter un livre</h2>
            <p class="modal-subtext" id="addBookModalText">Ajout manuel dans ce module.</p>

            <form method="POST" class="zone-form">
                <input type="hidden" name="action" value="add_resource_manual">
                <input type="hidden" name="zone" value="<?php echo (int)$currentZoneId; ?>">
                <input type="hidden" name="module_id" id="addBookModuleIdInput" value="">

                <label for="addBookBlocIdInput">ID bloc</label>
                <input id="addBookBlocIdInput" type="number" min="1" name="id_bloc" required placeholder="Ex: 6">
                <small class="modal-subtext" id="addBookBlocHelp">Utilise l'ID bloc du module.</small>

                <label for="addBookTitleInput">Titre</label>
                <input id="addBookTitleInput" type="text" name="titre" required placeholder="Ex: Les Misérables">

                <label for="addBookAuthorInput">Auteur</label>
                <input id="addBookAuthorInput" type="text" name="auteur" required placeholder="Ex: Victor Hugo">

                <label for="addBookCoteInput">Cote</label>
                <input id="addBookCoteInput" type="text" name="cote" required placeholder="Ex: 840 HUG">

                <div class="modal-actions">
                    <button type="button" class="btn-modal-secondary" data-close-modal="modalAddBook">Annuler</button>
                    <button type="submit" class="btn-modal-danger">Ajouter</button>
                </div>
            </form>
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
        const modalAddBook = document.getElementById('modalAddBook');
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
            if (event.target === modalAddBook) {
                modalAddBook.classList.remove('active');
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

        const addBookModalText = document.getElementById('addBookModalText');
        const addBookModuleIdInput = document.getElementById('addBookModuleIdInput');
        const addBookBlocIdInput = document.getElementById('addBookBlocIdInput');
        const addBookBlocHelp = document.getElementById('addBookBlocHelp');
        const addBookTitleInput = document.getElementById('addBookTitleInput');

        document.querySelectorAll('.open-add-book-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const moduleId = button.dataset.moduleId || '';
                const blocId = button.dataset.blocId || '';
                const moduleName = button.dataset.moduleName || 'ce module';

                if (addBookModuleIdInput) {
                    addBookModuleIdInput.value = moduleId;
                }
                if (addBookBlocIdInput) {
                    addBookBlocIdInput.value = blocId;
                }
                if (addBookBlocHelp) {
                    addBookBlocHelp.textContent = blocId !== ''
                        ? `ID bloc du module ${moduleName} : ${blocId}`
                        : "Utilise l'ID bloc du module.";
                }
                if (addBookModalText) {
                    addBookModalText.textContent = `Ajout manuel dans ${moduleName}.`;
                }
                if (addBookTitleInput) {
                    addBookTitleInput.value = '';
                }
                if (modalAddBook) {
                    modalAddBook.classList.add('active');
                }
            });
        });

        document.querySelectorAll('[data-books-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const group = button.closest('.esp-group');
                if (!group) {
                    return;
                }

                const tableWrap = group.querySelector('[data-books-wrap]');
                if (!tableWrap) {
                    return;
                }

                const isExpanded = tableWrap.classList.toggle('expanded');
                tableWrap.classList.toggle('compact', !isExpanded);
                button.textContent = isExpanded ? 'Voir moins' : 'Voir plus';
            });
        });

    </script>
</body>
</html>
