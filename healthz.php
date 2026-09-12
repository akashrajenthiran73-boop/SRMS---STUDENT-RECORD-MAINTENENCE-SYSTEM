<?php
// Lightweight Health Check Endpoint for Render / Load Balancers
header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'ok',
    'app' => 'Arignar Anna Govt Arts College - SRMS',
    'timestamp' => time(),
    'php_version' => PHP_VERSION
]);
exit;
