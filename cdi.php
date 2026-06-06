<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once "db.php";
require_once __DIR__ . '/src/auth_session.php';

$sessionUser = requireAuthenticatedSessionUser($pdo, [1, 2], 'vehicule.php');
$liveRole = (int)$sessionUser['role'];

function redirectToLoginForDbIssue(): void
{
    session_unset();
    session_destroy();
    header("Location: login.php?error=db");
    exit();
}

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
                    SET l.titre = ?
                    WHERE l.id = ? AND l.id_genre = ?
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
    } elseif ($action === 'update_resource_full') {
        $zoneId = (int)($_POST['zone'] ?? 0);
        $resourceId = (int)($_POST['id_ressources'] ?? 0);
        $title = trim((string)($_POST['titre'] ?? ''));
        $author = trim((string)($_POST['auteur'] ?? ''));
        $category = trim((string)($_POST['categorie'] ?? ''));
        $cote = trim((string)($_POST['cote'] ?? ''));
        $isbn = preg_replace('/[-\s]/', '', (string)($_POST['isbn'] ?? ''));
        $blocId = (int)($_POST['id_bloc'] ?? 0);
        $image = trim((string)($_POST['image'] ?? ''));

        if ($zoneId > 0 && $resourceId > 0 && $title !== '' && $blocId > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE livre l
                    SET l.titre = ?,
                        l.auteur = ?,
                        l.categorie = ?,
                        l.cote = ?,
                        l.isbn = ?,
                        l.id_bloc = ?,
                        l.id_genre = ?,
                        l.image = ?
                    WHERE l.id = ? AND l.id_genre = ?
                ");
                $stmt->execute([
                    $title,
                    $author !== '' ? $author : null,
                    $category !== '' ? $category : null,
                    $cote !== '' ? $cote : null,
                    $isbn !== '' ? $isbn : null,
                    $blocId,
                    $zoneId,
                    $image !== '' ? $image : null,
                    $resourceId,
                    $zoneId,
                ]);

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
        $sectionInput = trim((string)($_POST['section'] ?? ''));
        $title = trim($_POST['titre'] ?? '');
        $author = trim($_POST['auteur'] ?? '');
        $category = trim($_POST['categorie'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $cote = trim($_POST['cote'] ?? '');
        $isbn = preg_replace('/[-\s]/', '', (string)($_POST['isbn'] ?? ''));

        if ($zoneId > 0 && $moduleId > 0 && $sectionInput !== '' && $title !== '' && $author !== '' && $cote !== '') {
            try {
                $blocStmt = $pdo->prepare('SELECT id FROM bloc WHERE id_zone = ? AND genre = ? AND section = ? LIMIT 1');
                $blocStmt->execute([$moduleId, $zoneId, $sectionInput]);
                $blocId = $blocStmt->fetchColumn();
                if ($blocId === false) {
                    $createBlocStmt = $pdo->prepare("
                        INSERT INTO bloc (id_zone, section, alphabet_start, alphabet_end, genre)
                        VALUES (?, ?, 'A', 'Z', ?)
                    ");
                    $createBlocStmt->execute([$moduleId, $sectionInput, $zoneId]);
                    $blocId = (int)$pdo->lastInsertId();
                }

                $nextBookId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM livre')->fetchColumn();
                if ($nextBookId <= 0) {
                    $nextBookId = 1;
                }

                $insertStmt = $pdo->prepare('
                    INSERT INTO livre (id, titre, auteur, categorie, image, cote, isbn, etat, id_bloc, id_genre)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                ');
                $insertStmt->execute([
                    $nextBookId,
                    $title,
                    $author,
                    $category !== '' ? $category : null,
                    $image !== '' ? $image : null,
                    $cote,
                    $isbn !== '' ? $isbn : null,
                    (int)$blocId,
                    $zoneId,
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
                    DELETE FROM livre
                    WHERE id = ? AND id_genre = ?
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
$moduleGenreSectionsMap = [];

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
        SELECT z.id AS id_module, z.ip_address, z.nom_module, b.id AS id_bloc, b.section
        FROM zone z
        INNER JOIN bloc b ON b.id_zone = z.id
        WHERE b.genre = ?
        ORDER BY z.id ASC, b.section ASC
    ");
    $stmtZoneModules->execute([$currentZoneId]);
    $zoneModules = $stmtZoneModules->fetchAll(PDO::FETCH_ASSOC);

    $zoneNames = array_map(
        static fn(array $zone): string => trim((string)($zone['nom_zone'] ?? '')),
        $zones
    );
    $moduleDisplayIndex = 0;
    $groupedModuleRows = [];
    foreach ($zoneModules as $module) {
        $moduleId = trim((string)($module['id_module'] ?? ''));
        $moduleName = trim((string)($module['nom_module'] ?? ''));
        $moduleSection = isset($module['section']) ? (int)$module['section'] : 0;

        if ($moduleId !== '') {
            if (!isset($groupedModuleRows[$moduleId])) {
                $moduleDisplayIndex++;
                $moduleNumber = ctype_digit($moduleId) ? (int)$moduleId : $moduleDisplayIndex;
                $groupedModuleRows[$moduleId] = [
                    'id_module' => $moduleId,
                    'id_bloc' => isset($module['id_bloc']) ? (int)$module['id_bloc'] : 0,
                    'ip_address' => trim((string)($module['ip_address'] ?? '')),
                    'label' => resolveModuleDisplayName($moduleName, $moduleNumber, $zoneNames),
                    'sections' => [],
                ];
                $zoneModuleIds[] = $moduleId;
            }

            if ($moduleSection > 0 && !in_array($moduleSection, $groupedModuleRows[$moduleId]['sections'], true)) {
                $groupedModuleRows[$moduleId]['sections'][] = $moduleSection;
            }
        }
    }
    $zoneModuleRows = array_values($groupedModuleRows);
    foreach ($zoneModuleRows as &$moduleRow) {
        sort($moduleRow['sections'], SORT_NATURAL);
    }
    unset($moduleRow);

    if (!empty($zoneModuleIds)) {
        $modulePlaceholders = implode(',', array_fill(0, count($zoneModuleIds), '?'));
        $stmtGenreSections = $pdo->prepare("
            SELECT id_zone, genre, section
            FROM bloc
            WHERE id_zone IN ($modulePlaceholders)
            ORDER BY id_zone ASC, genre ASC, section ASC
        ");
        $stmtGenreSections->execute($zoneModuleIds);
        $moduleGenreSectionsRows = $stmtGenreSections->fetchAll(PDO::FETCH_ASSOC);

        $moduleGenreSectionsMap = [];
        foreach ($moduleGenreSectionsRows as $genreSectionRow) {
            $mapModuleId = trim((string)($genreSectionRow['id_zone'] ?? ''));
            $mapGenreId = isset($genreSectionRow['genre']) ? (int)$genreSectionRow['genre'] : 0;
            $mapSection = trim((string)($genreSectionRow['section'] ?? ''));

            if ($mapModuleId === '' || $mapGenreId <= 0 || $mapSection === '') {
                continue;
            }

            if (!isset($moduleGenreSectionsMap[$mapModuleId])) {
                $moduleGenreSectionsMap[$mapModuleId] = [];
            }
            if (!isset($moduleGenreSectionsMap[$mapModuleId][$mapGenreId])) {
                $moduleGenreSectionsMap[$mapModuleId][$mapGenreId] = [];
            }
            if (!in_array($mapSection, $moduleGenreSectionsMap[$mapModuleId][$mapGenreId], true)) {
                $moduleGenreSectionsMap[$mapModuleId][$mapGenreId][] = $mapSection;
            }
        }

        $stmtResByModule = $pdo->prepare("
            SELECT z.id AS id_module, l.id, l.titre, l.auteur, l.categorie, l.cote, l.isbn, l.image, l.id_bloc, l.id_genre
            FROM livre l
            INNER JOIN bloc b ON b.id = l.id_bloc
            INNER JOIN zone z ON z.id = b.id_zone
            WHERE z.id IN ($modulePlaceholders)
            ORDER BY z.id ASC, l.titre ASC
        ");
        $executeParams = $zoneModuleIds;
        $stmtResByModule->execute($executeParams);
        $moduleResourcesRows = $stmtResByModule->fetchAll(PDO::FETCH_ASSOC);

        foreach ($moduleResourcesRows as $moduleResource) {
            $moduleKey = trim((string)($moduleResource['id_module'] ?? ''));
            if ($moduleKey === '') {
                continue;
            }

            $resourceGenreId = isset($moduleResource['id_genre']) ? (int)$moduleResource['id_genre'] : 0;
            $category = trim((string)($moduleResource['categorie'] ?? ''));
            $belongsToCurrentZone = $resourceGenreId > 0
                ? ($resourceGenreId === $currentZoneId)
                : strcasecmp($category, trim((string)($zoneInfo['nom_zone'] ?? ''))) === 0;

            if (!$belongsToCurrentZone) {
                continue;
            }

            if (!isset($resourcesByModule[$moduleKey])) {
                $resourcesByModule[$moduleKey] = [];
            }

            $title = trim((string)($moduleResource['titre'] ?? ''));
            $resourcesByModule[$moduleKey][] = [
                'id' => isset($moduleResource['id']) ? (int)$moduleResource['id'] : 0,
                'titre' => $title !== '' ? $title : '--',
                'auteur' => trim((string)($moduleResource['auteur'] ?? '')),
                'categorie' => trim((string)($moduleResource['categorie'] ?? '')),
                'cote' => trim((string)($moduleResource['cote'] ?? '')),
                'isbn' => trim((string)($moduleResource['isbn'] ?? '')),
                'image' => trim((string)($moduleResource['image'] ?? '')),
                'id_bloc' => isset($moduleResource['id_bloc']) ? (int)$moduleResource['id_bloc'] : 0,
                'id_genre' => $resourceGenreId,
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
    <style>
        .book-modal-layout {
            display: flex;
            gap: 24px;
            margin-top: 16px;
        }
        .book-modal-preview {
            flex: 1;
            background: #f7f7f7;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e3e3e3;
            align-self: flex-start;
        }
        .book-modal-preview img {
            width: 130px;
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.14);
            margin-bottom: 14px;
            background: #ececec;
        }
        .preview-details p {
            margin: 6px 0;
            font-size: 0.95rem;
        }
        .book-modal-form-side {
            flex: 1.45;
        }
        .isbn-quick-search-box {
            background: #eef7f2;
            border: 1px solid #cfe3d7;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 18px;
        }
        .isbn-quick-search-row {
            display: flex;
            gap: 10px;
            margin-top: 6px;
        }
        .isbn-quick-search-row input {
            flex: 1;
        }
        .isbn-search-btn {
            background: #1a7a3a;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
        }
        .selectable-book-cell {
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .book-modal-layout {
                flex-direction: column;
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
                <?php if ($liveRole === 1): ?>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="cdi.php" class="active">Zone CDI</a></li>
                    <li><a href="esp.php">Modules ESP</a></li>
                    <li><a href="vehicule.php">Véhicule</a></li>
                    <li><a href="radio.php">Salle radio</a></li>
                    <li><a href="mobile.php">Classe mobile</a></li>
                    <li><a href="reservation_validation.php">Confirmation</a></li>
                    <li><a href="register.php">Créer un compte</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                <?php else: ?>
                    <li><a href="cdi.php" class="active">Zone CDI</a></li>
                    <li><a href="esp.php">Modules ESP</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                <?php endif; ?>
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
                                            <button
                                                type="button"
                                                class="esp-led-btn"
                                                data-module-id="<?php echo (int)$moduleRow['id_module']; ?>"
                                                data-ip="<?php echo htmlspecialchars((string)$moduleRow['ip_address'], ENT_QUOTES); ?>"
                                                data-sections="<?php echo htmlspecialchars(implode(',', array_values($moduleRow['sections'])), ENT_QUOTES); ?>"
                                                <?php echo (empty($moduleRow['ip_address']) || empty($moduleRow['sections'])) ? 'disabled' : ''; ?>
                                            >
                                                Allumer LED
                                            </button>
                                            <button
                                                type="button"
                                                class="esp-add-book-btn open-add-book-modal"
                                                data-module-id="<?php echo (int)$moduleRow['id_module']; ?>"
                                                data-bloc-id="<?php echo (int)$moduleRow['id_bloc']; ?>"
                                                data-sections="<?php echo htmlspecialchars(json_encode(array_values($moduleRow['sections']), JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>"
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
                                                                <td
                                                                    class="<?php echo is_array($bookItem) ? 'selectable-book-cell' : ''; ?>"
                                                                    <?php if (is_array($bookItem)): ?>
                                                                        data-resource-id="<?php echo (int)($bookItem['id'] ?? 0); ?>"
                                                                        data-resource-title="<?php echo htmlspecialchars((string)($bookItem['titre'] ?? ''), ENT_QUOTES); ?>"
                                                                        data-resource-author="<?php echo htmlspecialchars((string)($bookItem['auteur'] ?? ''), ENT_QUOTES); ?>"
                                                                        data-resource-category="<?php echo htmlspecialchars((string)($bookItem['categorie'] ?? ''), ENT_QUOTES); ?>"
                                                                        data-resource-image="<?php echo htmlspecialchars((string)($bookItem['image'] ?? ''), ENT_QUOTES); ?>"
                                                                        data-resource-cote="<?php echo htmlspecialchars((string)($bookItem['cote'] ?? ''), ENT_QUOTES); ?>"
                                                                        data-resource-isbn="<?php echo htmlspecialchars((string)($bookItem['isbn'] ?? ''), ENT_QUOTES); ?>"
                                                                        data-resource-bloc="<?php echo (int)($bookItem['id_bloc'] ?? 0); ?>"
                                                                        data-resource-genre="<?php echo (int)($bookItem['id_genre'] ?? 0); ?>"
                                                                    <?php endif; ?>
                                                                >
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

    <div class="modal-overlay" id="modalEditBook">
        <div class="modal-card" style="max-width: 860px; width: 95%;">
            <button class="modal-close" data-close-modal="modalEditBook">&times;</button>
            <h2 style="text-align:center; margin-bottom: 6px;">Fiche ressource</h2>
            <p class="modal-subtext" style="text-align:center;">Détails du livre et modification complète.</p>

            <div class="book-modal-layout">
                <div class="book-modal-preview">
                    <img id="previewBookImage" src="" alt="Couverture" onerror="this.src='https://placehold.co/130x180?text=Pas+d%27image'">
                    <div class="preview-details">
                        <p><strong>Titre actuel :</strong> <span id="previewBookTitle"></span></p>
                        <p><strong>Auteur actuel :</strong> <span id="previewBookAuthor"></span></p>
                        <p><strong>Catégorie actuelle :</strong> <span id="previewBookCategory"></span></p>
                        <p><strong>Cote actuelle :</strong> <span id="previewBookCote"></span></p>
                        <p><strong>ISBN actuel :</strong> <span id="previewBookIsbn"></span></p>
                        <p><strong>ID bloc actuel :</strong> <span id="previewBookBloc"></span></p>
                    </div>
                </div>

                <div class="book-modal-form-side">
                    <div class="isbn-quick-search-box">
                        <label for="edit_isbn_search"><strong>Remplir automatiquement via ISBN</strong></label>
                        <div class="isbn-quick-search-row">
                            <input id="edit_isbn_search" type="text" placeholder="Ex: 9782344061343">
                            <button type="button" id="btn_edit_search_isbn" class="isbn-search-btn">Remplir</button>
                        </div>
                        <small id="edit_isbn_status" style="display:block; margin-top:6px; font-weight:700; color:#666;"></small>
                    </div>

                    <form method="POST" class="zone-form" id="editBookModalForm" style="margin:0;">
                        <input type="hidden" name="action" value="update_resource_full">
                        <input type="hidden" name="zone" value="<?php echo (int)$currentZoneId; ?>">
                        <input type="hidden" name="id_ressources" id="editBookIdInput" value="">

                        <label for="editBookTitleInput">Titre</label>
                        <input id="editBookTitleInput" type="text" name="titre" required>

                        <label for="editBookAuthorInput">Auteur</label>
                        <input id="editBookAuthorInput" type="text" name="auteur">

                        <label for="editBookCategoryInput">Catégorie / Genre</label>
                        <input id="editBookCategoryInput" type="text" name="categorie">

                        <label for="editBookCoteInput">Cote</label>
                        <input id="editBookCoteInput" type="text" name="cote">

                        <label for="editBookIsbnInput">ISBN</label>
                        <input id="editBookIsbnInput" type="text" name="isbn">

                        <label for="editBookBlocInput">ID bloc rattaché</label>
                        <input id="editBookBlocInput" type="number" min="1" name="id_bloc" required>

                        <label for="editBookImageInput">URL de la couverture</label>
                        <input id="editBookImageInput" type="text" name="image">

                        <div class="modal-actions" style="margin-top:20px;">
                            <button type="button" class="btn-modal-secondary" data-close-modal="modalEditBook">Annuler</button>
                            <button type="submit" class="btn-modal-danger">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalAddBook">
        <div class="modal-card">
            <button class="modal-close" data-close-modal="modalAddBook">&times;</button>
            <h2 style="text-align:center;">Ajouter un livre</h2>
            <p class="modal-subtext" id="addBookModalText">Remplissage automatique par ISBN ou saisie manuelle.</p>

            <div class="isbn-quick-search-box">
                <label for="isbn_search"><strong>Remplissage automatique via ISBN</strong></label>
                <div class="isbn-quick-search-row">
                    <input id="isbn_search" type="text" placeholder="Ex: 9782070415793">
                    <button type="button" id="btn_search_isbn" class="isbn-search-btn">Rechercher</button>
                </div>
                <small id="isbn_status" style="display:block; margin-top:6px; font-weight:700; color:#666;"></small>
            </div>

            <form method="POST" class="zone-form">
                <input type="hidden" name="action" value="add_resource_manual">
                <input type="hidden" name="zone" value="<?php echo (int)$currentZoneId; ?>">
                <input type="hidden" name="module_id" id="addBookModuleIdInput" value="">

                <label for="addBookSectionInput">Section</label>
                <input id="addBookSectionInput" type="number" min="1" name="section" required placeholder="Ex: 1">
                <small class="modal-subtext" id="addBookSectionHelp">Choisis la section du module.</small>

                <label for="addBookTitleInput">Titre</label>
                <input id="addBookTitleInput" type="text" name="titre" required placeholder="Ex: Les Misérables">

                <label for="addBookAuthorInput">Auteur</label>
                <input id="addBookAuthorInput" type="text" name="auteur" required placeholder="Ex: Victor Hugo">

                <label for="addBookCategorieInput">Catégorie / Genre</label>
                <input id="addBookCategorieInput" type="text" name="categorie" placeholder="Ex: Roman">

                <label for="addBookImageInput">Lien de la couverture</label>
                <input id="addBookImageInput" type="text" name="image" placeholder="https://...">

                <label for="addBookCoteInput">Cote</label>
                <input id="addBookCoteInput" type="text" name="cote" required placeholder="Ex: 840 HUG">

                <label for="addBookIsbnInput">ISBN</label>
                <input id="addBookIsbnInput" type="text" name="isbn" placeholder="Ex: 9782070415793">

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
        const moduleGenreSectionsMap = <?php echo json_encode($moduleGenreSectionsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const modal = document.getElementById('modalZone');
        const modalEdit = document.getElementById('modalEdit');
        const modalEditBook = document.getElementById('modalEditBook');
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
            if (event.target === modalEditBook) {
                modalEditBook.classList.remove('active');
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
        const addBookSectionInput = document.getElementById('addBookSectionInput');
        const addBookSectionHelp = document.getElementById('addBookSectionHelp');
        const addBookTitleInput = document.getElementById('addBookTitleInput');
        const addBookAuthorInput = document.getElementById('addBookAuthorInput');
        const addBookCategorieInput = document.getElementById('addBookCategorieInput');
        const addBookImageInput = document.getElementById('addBookImageInput');
        const addBookCoteInput = document.getElementById('addBookCoteInput');
        const addBookIsbnInput = document.getElementById('addBookIsbnInput');

        document.querySelectorAll('.open-add-book-modal').forEach((button) => {
            button.addEventListener('click', () => {
                const moduleId = button.dataset.moduleId || '';
                const moduleName = button.dataset.moduleName || 'ce module';
                const sections = JSON.parse(button.dataset.sections || '[]');
                const defaultSection = sections.length ? sections[0] : 1;

                if (addBookModuleIdInput) {
                    addBookModuleIdInput.value = moduleId;
                }
                if (addBookSectionInput) {
                    addBookSectionInput.value = defaultSection;
                }
                if (addBookSectionHelp) {
                    addBookSectionHelp.textContent = sections.length
                        ? `Sections disponibles pour ${moduleName} : ${sections.join(', ')}`
                        : `La section ${defaultSection} sera créée pour ${moduleName}.`;
                }
                if (addBookModalText) {
                    addBookModalText.textContent = `Ajout manuel dans ${moduleName}.`;
                }
                if (addBookTitleInput) {
                    addBookTitleInput.value = '';
                }
                if (addBookAuthorInput) {
                    addBookAuthorInput.value = '';
                }
                if (addBookCategorieInput) {
                    addBookCategorieInput.value = '';
                }
                if (addBookImageInput) {
                    addBookImageInput.value = '';
                }
                if (addBookCoteInput) {
                    addBookCoteInput.value = '';
                }
                if (addBookIsbnInput) {
                    addBookIsbnInput.value = '';
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

        document.querySelectorAll('.selectable-book-cell').forEach((cell) => {
            cell.addEventListener('click', (event) => {
                if (event.target.closest('.book-delete-btn')) {
                    return;
                }

                const resourceId = cell.dataset.resourceId || '';
                if (resourceId === '') {
                    return;
                }

                const title = cell.dataset.resourceTitle || '';
                const author = cell.dataset.resourceAuthor || '';
                const category = cell.dataset.resourceCategory || '';
                const image = cell.dataset.resourceImage || '';
                const cote = cell.dataset.resourceCote || '';
                const isbn = cell.dataset.resourceIsbn || '';
                const bloc = cell.dataset.resourceBloc || '';

                document.getElementById('previewBookTitle').textContent = title.trim() !== '' ? title : 'Non renseigné';
                document.getElementById('previewBookAuthor').textContent = author.trim() !== '' ? author : 'Non renseigné';
                document.getElementById('previewBookCategory').textContent = category.trim() !== '' ? category : 'Non renseigné';
                document.getElementById('previewBookCote').textContent = cote.trim() !== '' ? cote : 'Non renseigné';
                document.getElementById('previewBookIsbn').textContent = isbn.trim() !== '' ? isbn : 'Non renseigné';
                document.getElementById('previewBookBloc').textContent = bloc.trim() !== '' ? bloc : 'Non renseigné';
                document.getElementById('previewBookImage').src = image.trim() !== '' ? image : 'https://placehold.co/130x180?text=Pas+d%27image';

                document.getElementById('editBookIdInput').value = resourceId;
                document.getElementById('editBookTitleInput').value = title;
                document.getElementById('editBookAuthorInput').value = author;
                document.getElementById('editBookCategoryInput').value = category;
                document.getElementById('editBookCoteInput').value = cote;
                document.getElementById('editBookIsbnInput').value = isbn;
                document.getElementById('editBookBlocInput').value = bloc;
                document.getElementById('editBookImageInput').value = image;
                document.getElementById('edit_isbn_search').value = isbn;
                document.getElementById('edit_isbn_status').textContent = '';

                if (modalEditBook) {
                    modalEditBook.classList.add('active');
                }
            });
        });

    </script>
    <script>
        const authorTranslations = {
            '尾田栄一郎': 'Eiichiro Oda',
            '尾田 栄一郎': 'Eiichiro Oda',
            '岸本斉史': 'Masashi Kishimoto',
            '久保帯人': 'Tite Kubo',
            '堀越耕平': 'Kohei Horikoshi',
            '鳥山明': 'Akira Toriyama'
        };

        function fetchBookDetails(isbn, successCallback, errorCallback) {
            const cleanIsbn = isbn.trim().replace(/[-\s]/g, '');
            if (cleanIsbn.length < 10) {
                errorCallback('Code ISBN non valide.');
                return;
            }

            const url = `https://openlibrary.org/api/books?bibkeys=ISBN:${cleanIsbn}&jscmd=data&format=json`;
            fetch(url)
                .then((response) => response.json())
                .then((data) => {
                    const key = `ISBN:${cleanIsbn}`;
                    if (!data || !data[key]) {
                        errorCallback('Aucun livre trouvé avec cet ISBN.');
                        return;
                    }

                    const info = data[key];
                    const title = info.title || 'Livre inconnu';
                    let authors = 'Auteur inconnu';
                    const image = info.cover ? (info.cover.large || info.cover.medium || '') : '';
                    let category = 'Roman';

                    if (Array.isArray(info.authors) && info.authors.length > 0) {
                        authors = info.authors
                            .map((authorItem) => authorTranslations[authorItem.name.trim()] || authorItem.name.trim())
                            .join(', ');
                    }

                    if (Array.isArray(info.subjects) && info.subjects.length > 0) {
                        const subject = String(info.subjects[0].name || '').toLowerCase();
                        if (subject.includes('manga')) {
                            category = 'Manga';
                        } else if (subject.includes('comic') || subject.includes('bd') || subject.includes('graphic')) {
                            category = 'Bande Dessinee';
                        }
                    }

                    successCallback({ title, authors, image, category, cleanIsbn });
                })
                .catch(() => {
                    errorCallback('Erreur de connexion au service ISBN.');
                });
        }

        const editSearchButton = document.getElementById('btn_edit_search_isbn');
        if (editSearchButton) {
            editSearchButton.addEventListener('click', () => {
                const isbnInput = document.getElementById('edit_isbn_search').value;
                const status = document.getElementById('edit_isbn_status');
                status.style.color = 'orange';
                status.textContent = 'Recuperation des donnees...';

                fetchBookDetails(isbnInput, (book) => {
                    document.getElementById('editBookTitleInput').value = book.title;
                    document.getElementById('editBookAuthorInput').value = book.authors;
                    document.getElementById('editBookCategoryInput').value = book.category;
                    document.getElementById('editBookImageInput').value = book.image;
                    document.getElementById('editBookIsbnInput').value = book.cleanIsbn;
                    status.style.color = 'green';
                    status.textContent = 'Donnees recuperees.';
                }, (errorMessage) => {
                    status.style.color = 'red';
                    status.textContent = errorMessage;
                });
            });
        }

        const addSearchButton = document.getElementById('btn_search_isbn');
        if (addSearchButton) {
            addSearchButton.addEventListener('click', () => {
                const isbnInput = document.getElementById('isbn_search').value;
                const status = document.getElementById('isbn_status');
                status.style.color = 'orange';
                status.textContent = 'Recherche en cours...';

                fetchBookDetails(isbnInput, (book) => {
                    document.getElementById('addBookTitleInput').value = book.title;
                    document.getElementById('addBookAuthorInput').value = book.authors;
                    document.getElementById('addBookCategorieInput').value = book.category;
                    document.getElementById('addBookImageInput').value = book.image;
                    document.getElementById('addBookIsbnInput').value = book.cleanIsbn;
                    status.style.color = 'green';
                    status.textContent = 'Livre trouve. Ajoute maintenant la cote.';
                }, (errorMessage) => {
                    status.style.color = 'red';
                    status.textContent = errorMessage;
                });
            });
        }

        document.querySelectorAll('.esp-led-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const moduleId = button.dataset.moduleId || '';
                const ip = button.dataset.ip || '';
                const group = button.closest('.esp-group');
                const resolvedSections = new Set();

                if (group && moduleId !== '' && moduleGenreSectionsMap[moduleId]) {
                    group.querySelectorAll('.selectable-book-cell[data-resource-genre]').forEach((cell) => {
                        const genreId = cell.dataset.resourceGenre || '';
                        if (!genreId || !moduleGenreSectionsMap[moduleId][genreId]) {
                            return;
                        }

                        moduleGenreSectionsMap[moduleId][genreId].forEach((section) => {
                            if (String(section).trim() !== '') {
                                resolvedSections.add(String(section).trim());
                            }
                        });
                    });
                }

                if (resolvedSections.size === 0) {
                    (button.dataset.sections || '')
                        .split(',')
                        .map((section) => section.trim())
                        .filter((section) => section !== '')
                        .forEach((section) => resolvedSections.add(section));
                }

                const sections = Array.from(resolvedSections).sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true })).join(',');

                if (!sections || !ip) {
                    alert('Donnees manquantes pour allumer la LED.');
                    return;
                }

                button.disabled = true;
                const originalText = button.textContent;
                button.textContent = 'Allumage...';

                fetch(`allumer_module.php?section=${encodeURIComponent(sections)}&ip=${encodeURIComponent(ip)}`)
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.status === 'success') {
                            button.textContent = 'LED allumee';
                            window.setTimeout(() => {
                                button.disabled = false;
                                button.textContent = originalText;
                            }, 5500);
                            return;
                        }

                        button.disabled = false;
                        button.textContent = originalText;
                        alert(data.message || 'Erreur inconnue.');
                    })
                    .catch(() => {
                        button.disabled = false;
                        button.textContent = originalText;
                        alert('Impossible de joindre le module.');
                    });
            });
        });
    </script>
</body>
</html>
