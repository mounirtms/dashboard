#!/usr/bin/env php
<?php
/**
 * Test Cloudflare GraphQL API
 */

$config = include '/home/dashboard/public_html/config/cloudflare.php';

$zoneId = $config['zone_id'];
$email = $config['email'];
$apiKey = $config['api_key'];

// GraphQL query for last 24 hours
$yesterday = date('Y-m-d', strtotime('-1 day'));
$query = <<<GRAPHQL
{
  viewer {
    zones(filter: {zoneTag: "$zoneId"}) {
      httpRequests1dGroups(limit: 1, filter: {date_geq: "$yesterday"}) {
        sum {
          requests
          cachedRequests
          bytes
          threats
          pageViews
        }
        dimensions {
          date
        }
      }
    }
  }
}
GRAPHQL;

$ch = curl_init('https://api.cloudflare.com/client/v4/graphql');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Auth-Email: ' . $email,
    'X-Auth-Key: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$data = json_decode($response, true);
print_r($data);

if (isset($data['data']['viewer']['zones'][0]['httpRequests1dGroups'][0])) {
    $stats = $data['data']['viewer']['zones'][0]['httpRequests1dGroups'][0]['sum'];
    $requests = $stats['requests'];
    $cached = $stats['cachedRequests'];
    $hitRate = $requests > 0 ? ($cached / $requests) * 100 : 0;
    
    echo "\n✅ Analytics for last 24h:\n";
    echo "  Total Requests: " . number_format($requests) . "\n";
    echo "  Cached Requests: " . number_format($cached) . "\n";
    echo "  Cache Hit Rate: " . round($hitRate, 2) . "%\n";
    echo "  Bandwidth: " . round($stats['bytes'] / 1024 / 1024, 2) . " MB\n";
    echo "  Threats Blocked: " . number_format($stats['threats']) . "\n";
}
