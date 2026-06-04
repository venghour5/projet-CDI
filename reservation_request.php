<?php
declare(strict_types=1);

session_start();
require_once 'db.php';
require_once __DIR__ . '/src/reservation_workflow.php';
require_once __DIR__ . '/src/auth_session.php';

header('Content-Type: application/json; charset=utf-8');

$sessionUser = getAuthenticatedSessionUser($pdo);
if ($sessionUser === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit();
}

$role = (int)$sessionUser['role'];
if (!in_array($role, [1, 3, 4], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit();
}

$raw = file_get_contents('php://input');
$data = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($data)) {
    $data = $_POST;
}

$action = trim((string)($data['action'] ?? 'create'));

if ($action === 'cancel') {
    if (!in_array($role, [1, 4], true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit();
    }

    $reservationId = isset($data['reservation_id']) ? (int)$data['reservation_id'] : 0;
    $result = cancelApprovedReservation($pdo, $reservationId);
    if (!$result['ok']) {
        http_response_code(400);
    }

    echo json_encode($result);
    exit();
}

$resourceId = isset($data['resource_id']) ? (int)$data['resource_id'] : 0;
$day = trim((string)($data['day'] ?? ''));
$startTime = trim((string)($data['heure_debut'] ?? ''));
$duration = isset($data['duree']) ? (int)$data['duree'] : 1;
$vehicleName = isset($data['vehicle_name']) ? trim((string)$data['vehicle_name']) : null;
$teacherName = trim((string)($data['teacher_name'] ?? ''));
$isFullDay = isset($data['full_day']) && in_array($data['full_day'], [true, 1, '1', 'true'], true);

$timeline = reservationSlotsTimeline();
$maxDuration = count($timeline);
if ($isFullDay || $duration >= $maxDuration) {
    $startTime = (string)$timeline[0];
    $duration = $maxDuration;
}

$reservationUserId = (int)$sessionUser['id'];
if ($teacherName === '') {
    $teacherName = trim((string)$sessionUser['nom']);
}

if ($teacherName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_teacher']);
    exit();
}

$findTeacher = $pdo->prepare('SELECT id FROM utilisateur WHERE role = 3 AND nom = ? LIMIT 1');
$findTeacher->execute([$teacherName]);
$teacherId = $findTeacher->fetchColumn();
if ($teacherId === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_teacher']);
    exit();
}

$reservationUserId = (int)$teacherId;

$result = createReservationRequest($pdo, $reservationUserId, $resourceId, $day, $startTime, $duration, $vehicleName);
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode($result);
    exit();
}

if ($role === 3) {
    echo json_encode([
        'ok' => true,
        'status' => 'pending',
        'message' => 'Demande envoyée. En attente de confirmation par l\'admin réservation.',
        'id' => $result['id'] ?? null,
    ]);
    exit();
}

$approve = approveReservationRequest($pdo, (int)($result['id'] ?? 0), (int)$sessionUser['id']);
if (!$approve['ok']) {
    http_response_code(400);
    echo json_encode($approve);
    exit();
}

echo json_encode([
    'ok' => true,
    'status' => 'approved',
    'message' => 'Réservation validée immédiatement.',
    'id' => $result['id'] ?? null,
]);
