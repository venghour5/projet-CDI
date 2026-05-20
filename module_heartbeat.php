<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/src/module_supervision.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit();
}

ensureModuleSupervisionSchema($pdo);

$raw = file_get_contents('php://input');
$data = [];

if ($raw !== false && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if (empty($data)) {
    $data = $_POST;
}

$moduleId = isset($data['module_id']) && is_numeric((string)$data['module_id']) ? (int)$data['module_id'] : 0;
$token = trim((string)($data['token'] ?? ''));
$activityLabel = isset($data['activity_label']) ? trim((string)$data['activity_label']) : null;
$reachable = !isset($data['reachable']) || (string)$data['reachable'] !== '0';
$ipAddress = trim((string)($data['ip_address'] ?? ''));

if ($moduleId <= 0 || $token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_credentials']);
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT id, api_token, heartbeat_interval_sec FROM zone WHERE id = ? LIMIT 1');
    $stmt->execute([$moduleId]);
    $moduleRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$moduleRow) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'module_not_found']);
        exit();
    }

    $storedToken = trim((string)($moduleRow['api_token'] ?? ''));
    if ($storedToken === '' || !hash_equals($storedToken, $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid_token']);
        exit();
    }

    logModuleActivity(
        $pdo,
        $moduleId,
        $activityLabel !== null && $activityLabel !== '' ? $activityLabel : 'Heartbeat',
        $ipAddress !== '' ? $ipAddress : null,
        $reachable
    );

    syncModuleHealthAlerts($pdo);
    $overview = getModuleOverview($pdo, $moduleId);

    echo json_encode([
        'ok' => true,
        'server_time' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        'module' => [
            'id' => $overview['id'],
            'online' => $overview['is_online'],
            'zone_id' => $overview['id_zone'],
            'zone_name' => $overview['nom_zone'],
            'heartbeat_interval_sec' => (int)($moduleRow['heartbeat_interval_sec'] ?? 60),
            'offline_delay_seconds' => 300,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}
