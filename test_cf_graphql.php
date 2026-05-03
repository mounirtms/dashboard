<?php
/**
 * Cloudflare GraphQL Test Script
 * Tests the exact flow used in monitor.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Cloudflare config
$cfConfig = include __DIR__ . '/config/cloudflare.php';

echo "=== Cloudflare GraphQL API Test ===\n\n";
echo "Config loaded:\n";
echo "  Zone ID: " . ($cfConfig['zone_id'] ?? 'MISSING') . "\n";
echo "  API Key: " . (isset($cfConfig['api_key']) ? 'SET (' . strlen($cfConfig['api_key']) . ' chars)' : 'MISSING') . "\n";
echo "  Email: " . ($cfConfig['email'] ?? 'MISSING') . "\n\n";

$zoneId = $cfConfig['zone_id'];
$weekAgo = date('Y-m-d', strtotime('-8 days'));
$yesterday = date('Y-m-d', strtotime('-1 day'));
$today = date('Y-m-d');

echo "Date range: {$weekAgo} to {$today}\n\n";

// Create GraphQL query
$graphqlQuery = <<<GRAPHQL
{
  viewer {
    zones(filter: {zoneTag: "{$zoneId}"}) {
      dailyTraffic: httpRequests1dGroups(
        limit: 7
        filter: {date_gt: "{$weekAgo}", date_lt: "{$today}"}
        orderBy: [date_ASC]
      ) {
        sum {
          requests pageViews threats
          bytes cachedBytes
          cachedRequests
        }
        uniq { uniques }
        dimensions { date }
      }
      hourlyTraffic: httpRequests1hGroups(
        limit: 24
        filter: {datetime_gt: "{$yesterday}T00:00:00Z"}
        orderBy: [datetime_ASC]
      ) {
        sum { requests bytes threats cachedRequests }
        dimensions { datetime }
      }
      countries: httpRequests1dGroups(
        limit: 10
        filter: {date_gt: "{$weekAgo}"}
        orderBy: [sum_requests_DESC]
      ) {
        sum { requests bytes threats }
        dimensions { clientCountryName }
      }
    }
  }
}
GRAPHQL;

echo "GraphQL Query prepared\n";
echo "Query length: " . strlen($graphqlQuery) . " bytes\n\n";

// Make API call
$url = "https://api.cloudflare.com/client/v4/graphql";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$headers = ["Content-Type: application/json"];
if (!empty($cfConfig['api_key']) && !empty($cfConfig['email'])) {
    $headers[] = "X-Auth-Email: " . $cfConfig['email'];
    $headers[] = "X-Auth-Key: " . $cfConfig['api_key'];
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $graphqlQuery]));

echo "Making API call...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($curlError) {
    echo "cURL Error: {$curlError}\n";
}

$body = json_decode($response, true);
$jsonError = json_last_error();

echo "JSON decode: " . ($jsonError === JSON_ERROR_NONE ? "SUCCESS" : "FAILED - " . json_last_error_msg()) . "\n\n";

// Check response structure
echo "=== Response Structure ===\n";
echo "isset(body): " . (isset($body) ? 'YES' : 'NO') . "\n";
echo "isset(body['data']): " . (isset($body['data']) ? 'YES' : 'NO') . "\n";
echo "isset(body['data']['viewer']): " . (isset($body['data']['viewer']) ? 'YES' : 'NO') . "\n";
echo "isset(body['data']['viewer']['zones']): " . (isset($body['data']['viewer']['zones']) ? 'YES' : 'NO') . "\n";
echo "isset(body['data']['viewer']['zones'][0]): " . (isset($body['data']['viewer']['zones'][0]) ? 'YES' : 'NO') . "\n\n";

if (isset($body['errors'])) {
    echo "⚠️ GraphQL Errors:\n";
    foreach ($body['errors'] as $error) {
        echo "  - " . $error['message'] . "\n";
    }
    echo "\n";
}

if (isset($body['data']['viewer']['zones'][0])) {
    $zoneData = $body['data']['viewer']['zones'][0];
    
    echo "=== Zone Data Structure ===\n";
    echo "dailyTraffic: " . (isset($zoneData['dailyTraffic']) ? count($zoneData['dailyTraffic']) . " records" : "MISSING") . "\n";
    echo "hourlyTraffic: " . (isset($zoneData['hourlyTraffic']) ? count($zoneData['hourlyTraffic']) . " records" : "MISSING") . "\n";
    echo "countries: " . (isset($zoneData['countries']) ? count($zoneData['countries']) . " records" : "MISSING") . "\n\n";
    
    if (isset($zoneData['dailyTraffic']) && !empty($zoneData['dailyTraffic'])) {
        echo "=== Daily Traffic Sample ===\n";
        $totalRequests = 0;
        $totalBytes = 0;
        
        foreach ($zoneData['dailyTraffic'] as $day) {
            $requests = $day['sum']['requests'] ?? 0;
            $bytes = $day['sum']['bytes'] ?? 0;
            $cachedReq = $day['sum']['cachedRequests'] ?? 0;
            $hitRate = $requests > 0 ? ($cachedReq / $requests * 100) : 0;
            
            $totalRequests += $requests;
            $totalBytes += $bytes;
            
            echo sprintf("  %s: %s requests, %.2f MB, %.1f%% cached\n",
                $day['dimensions']['date'],
                number_format($requests),
                $bytes / 1024 / 1024,
                $hitRate
            );
        }
        
        echo "\nTotals:\n";
        echo "  Total Requests: " . number_format($totalRequests) . "\n";
        echo "  Total Bandwidth: " . number_format($totalBytes / 1024 / 1024, 2) . " MB\n\n";
    }
    
    if (isset($zoneData['countries']) && !empty($zoneData['countries'])) {
        echo "=== Top Countries ===\n";
        foreach (array_slice($zoneData['countries'], 0, 5) as $country) {
            echo sprintf("  %s: %s requests\n",
                $country['dimensions']['country'],
                number_format($country['sum']['requests'] ?? 0)
            );
        }
        echo "\n";
    }
    
    echo "✅ GraphQL API is working correctly!\n";
} else {
    echo "❌ No zone data found in response\n";
    echo "\nRaw response (first 500 chars):\n";
    echo substr($response, 0, 500) . "\n";
}
