<?php
/**
 * OneSignal Test Endpoint
 * Simple endpoint to test OneSignal functionality
 */
header(\"Content-Type: application/json\");
header(\"Access-Control-Allow-Origin: *\");
header(\"Access-Control-Allow-Methods: GET, POST, OPTIONS\");
header(\"Access-Control-Allow-Headers: Content-Type\");

if ($_SERVER[\"REQUEST_METHOD\"] === \"OPTIONS\") {
    http_response_code(200);
    exit();
}

// Return success response
echo json_encode([
    \"status\" => \"success\",
    \"message\" => \"OneSignal test endpoint is working\",
    \"timestamp\" => date(\"Y-m-d H:i:s\"),
    \"server_time\" => time()
]);
?>
