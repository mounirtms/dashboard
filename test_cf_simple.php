<?php
// Simple test to find correct field names
$cfConfig = include __DIR__ . '/config/cloudflare.php';

$zoneId = $cfConfig['zone_id'];
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$today = date('Y-m-d');

// Test with minimal fields
$query = <<<GRAPHQL
{
  viewer {
    zones(filter: {zoneTag: "{$zoneId}"}) {
      httpRequests1dGroups(
        limit: 3
        filter: {date_gt: "{$weekAgo}"}
        orderBy: [date_ASC]
      ) {
        sum { requests bytes }
        dimensions { date }
      }
    }
  }
}
GRAPHQL;

$ch = curl_init('https://api.cloudflare.com/client/v4/graphql');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Auth-Email: ' . $cfConfig['email'],
    'X-Auth-Key: ' . $cfConfig['api_key'],
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
curl_close($ch);

$body = json_decode($response, true);

if (isset($body['data']['viewer']['zones'][0]['httpRequests1dGroups'])) {
    echo "✅ Basic query works!\n";
    $data = $body['data']['viewer']['zones'][0]['httpRequests1dGroups'];
    echo "Records: " . count($data) . "\n\n";
    foreach ($data as $record) {
        echo "  " . $record['dimensions']['date'] . ": " . number_format($record['sum']['requests']) . " requests\n";
    }
} else {
    echo "❌ Failed\n";
    if (isset($body['errors'])) {
        foreach ($body['errors'] as $e) {
            echo "  Error: " . $e['message'] . "\n";
        }
    }
}
?>
