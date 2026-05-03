<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'service' => 'backend'
]);
