<?php
/**
 * Cloudflare Analytics API with GraphQL Support
 * 
 * Provides real-time analytics from Cloudflare using GraphQL API
 * 
 * @version 2.0
 * @date 2026-05-03
 */

header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication
require_once __DIR__ . '/../session_helper.php';
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Load configuration
$config = include __DIR__ . '/../../config/cloudflare.php';

if (!$config || (empty($config['api_key']) && empty($config['api_token']))) {
    echo json_encode([
        'success' => false,
        'error' => 'Cloudflare API credentials not configured. Please add valid API keys in the Cloudflare dashboard settings.',
        'setup_required' => true
    ]);
    exit;
}

// Get request parameters
$action = $_GET['action'] ?? 'zones';
$zoneId = $_GET['zone_id'] ?? $config['zone_id'] ?? null;
$period = $_GET['period'] ?? '24h'; // 24h, 7d, 30d

/**
 * Make Cloudflare API request
 */
function makeCloudflareRequest($config, $endpoint, $method = 'GET', $data = null) {
    $ch = curl_init("https://api.cloudflare.com/client/v4/{$endpoint}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = ['Content-Type: application/json'];
    
    // Prioritize Global API Key (no IP restrictions)
    if (!empty($config['api_key']) && !empty($config['email'])) {
        $headers[] = 'X-Auth-Email: ' . $config['email'];
        $headers[] = 'X-Auth-Key: ' . $config['api_key'];
    } elseif (!empty($config['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $config['api_token'];
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    
    return null;
}

/**
 * Get analytics using GraphQL
 */
function getAnalyticsGraphQL($config, $zoneId, $period = '24h') {
    // Calculate date range based on period
    $daysAgo = 1;
    if ($period === '7d') $daysAgo = 7;
    elseif ($period === '30d') $daysAgo = 30;
    
    $since = date('Y-m-d', strtotime("-{$daysAgo} days"));
    
    $query = <<<GRAPHQL
{
  viewer {
    zones(filter: {zoneTag: "$zoneId"}) {
      httpRequests1dGroups(limit: $daysAgo, filter: {date_geq: "$since"}) {
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = ['Content-Type: application/json'];
    
    // Prioritize Global API Key
    if (!empty($config['api_key']) && !empty($config['email'])) {
        $headers[] = 'X-Auth-Email: ' . $config['email'];
        $headers[] = 'X-Auth-Key: ' . $config['api_key'];
    } elseif (!empty($config['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $config['api_token'];
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['data']['viewer']['zones'][0]['httpRequests1dGroups'])) {
            return $data['data']['viewer']['zones'][0]['httpRequests1dGroups'];
        }
    }
    
    return null;
}

// Route actions
switch ($action) {
    case 'zones':
        // Get all zones
        $result = makeCloudflareRequest($config, 'zones?per_page=50');
        if ($result && isset($result['result'])) {
            $zones = array_map(function($zone) {
                return [
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'status' => $zone['status'],
                    'development_mode' => $zone['development_mode']
                ];
            }, $result['result']);
            
            echo json_encode([
                'success' => true,
                'zones' => $zones,
                'count' => count($zones)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch zones']);
        }
        break;
    
    case 'analytics':
        // Get analytics for specific zone
        if (!$zoneId) {
            http_response_code(400);
            echo json_encode(['error' => 'zone_id parameter required']);
            exit;
        }
        
        $analytics = getAnalyticsGraphQL($config, $zoneId, $period);
        
        if ($analytics) {
            // Aggregate totals
            $totals = [
                'requests' => 0,
                'cached_requests' => 0,
                'bytes' => 0,
                'threats' => 0,
                'page_views' => 0
            ];
            
            $timeline = [];
            
            foreach ($analytics as $day) {
                $sum = $day['sum'];
                $totals['requests'] += $sum['requests'];
                $totals['cached_requests'] += $sum['cachedRequests'];
                $totals['bytes'] += $sum['bytes'];
                $totals['threats'] += $sum['threats'];
                $totals['page_views'] += $sum['pageViews'] ?? 0;
                
                $timeline[] = [
                    'date' => $day['dimensions']['date'],
                    'requests' => $sum['requests'],
                    'cached' => $sum['cachedRequests'],
                    'bytes' => $sum['bytes'],
                    'threats' => $sum['threats']
                ];
            }
            
            $totals['cache_hit_rate'] = $totals['requests'] > 0 
                ? ($totals['cached_requests'] / $totals['requests']) * 100 
                : 0;
            
            echo json_encode([
                'success' => true,
                'zone_id' => $zoneId,
                'period' => $period,
                'totals' => $totals,
                'timeline' => $timeline
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch analytics']);
        }
        break;
    
    case 'settings':
        // Get zone settings
        if (!$zoneId) {
            http_response_code(400);
            echo json_encode(['error' => 'zone_id parameter required']);
            exit;
        }
        
        $result = makeCloudflareRequest($config, "zones/{$zoneId}/settings");
        if ($result && isset($result['result'])) {
            $settings = [];
            foreach ($result['result'] as $setting) {
                $settings[$setting['id']] = $setting['value'];
            }
            
            echo json_encode([
                'success' => true,
                'zone_id' => $zoneId,
                'settings' => $settings
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch settings']);
        }
        break;
    
    case 'overview':
        // Get comprehensive overview of all zones
        $zonesResult = makeCloudflareRequest($config, 'zones?per_page=50');
        
        if (!$zonesResult || !isset($zonesResult['result'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch zones']);
            exit;
        }
        
        $overview = [
            'total_zones' => count($zonesResult['result']),
            'active_zones' => 0,
            'total_requests_24h' => 0,
            'total_bandwidth_24h' => 0,
            'total_threats_24h' => 0,
            'average_cache_hit_rate' => 0,
            'zones' => []
        ];
        
        $hitRates = [];
        
        foreach ($zonesResult['result'] as $zone) {
            if ($zone['status'] === 'active') {
                $overview['active_zones']++;
                
                // Get analytics for each zone
                $analytics = getAnalyticsGraphQL($config, $zone['id'], '24h');
                
                if ($analytics && isset($analytics[0])) {
                    $sum = $analytics[0]['sum'];
                    $requests = $sum['requests'];
                    $cached = $sum['cachedRequests'];
                    $hitRate = $requests > 0 ? ($cached / $requests) * 100 : 0;
                    
                    $overview['total_requests_24h'] += $requests;
                    $overview['total_bandwidth_24h'] += $sum['bytes'];
                    $overview['total_threats_24h'] += $sum['threats'];
                    $hitRates[] = $hitRate;
                    
                    $overview['zones'][] = [
                        'id' => $zone['id'],
                        'name' => $zone['name'],
                        'status' => $zone['status'],
                        'requests_24h' => $requests,
                        'bandwidth_24h' => $sum['bytes'],
                        'cache_hit_rate' => round($hitRate, 2),
                        'threats_24h' => $sum['threats']
                    ];
                }
            }
        }
        
        if (!empty($hitRates)) {
            $overview['average_cache_hit_rate'] = round(array_sum($hitRates) / count($hitRates), 2);
        }
        
        echo json_encode([
            'success' => true,
            'overview' => $overview,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Supported: zones, analytics, settings, overview']);
}
