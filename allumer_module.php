<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$ip = trim((string)($_GET['ip'] ?? ''));
$sectionsRaw = trim((string)($_GET['section'] ?? ''));

if ($ip === '' || $sectionsRaw === '') {
    echo json_encode(['status' => 'error', 'message' => 'Parametres invalides.']);
    exit;
}

$sections = array_filter(
    array_map(
        static fn(string $section): int => 5 - (int)trim($section) + 1,
        explode(',', $sectionsRaw)
    ),
    static fn(int $section): bool => $section > 0
);

if (empty($sections)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune section valide.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Sections activees temporairement.',
    'sections' => array_values($sections),
]);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    flush();
}

ignore_user_abort(true);
set_time_limit(30);

function sendModuleRequest(string $url): void
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    @file_get_contents($url, false, $context);
}

sendModuleRequest('http://' . $ip . '/enleverAnciennesLEDs');

foreach ($sections as $section) {
    sendModuleRequest('http://' . $ip . '/sectionLED?n=5&s=' . $section);
    sendModuleRequest('http://' . $ip . '/couleurLED?r=255&g=255&b=255');
}

sleep(5);

foreach ($sections as $section) {
    sendModuleRequest('http://' . $ip . '/sectionLED?n=5&s=' . $section);
    sendModuleRequest('http://' . $ip . '/couleurLED?r=0&g=0&b=0');
}

sendModuleRequest('http://' . $ip . '/sectionLED?n=1&s=1');
sendModuleRequest('http://' . $ip . '/enleverAnciennesLEDs');
