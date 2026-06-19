<?php

header('Content-Type: application/json; charset=utf-8');

if (!file_exists(__DIR__ . '/install.lock')) {
    http_response_code(503);
    echo json_encode(['error' => 'not_installed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = parse_ini_file(__DIR__ . '/config.ini');
if ($config === false) {
    http_response_code(500);
    echo json_encode(['error' => 'config_error'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'apiEndpoint' => 'api.php',
    'token' => $config['validToken'] ?? '',
    'maxFileSize' => 50 * 1024 * 1024,
    'supportedFormats' => ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'],
    'defaultQuality' => 70,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>
