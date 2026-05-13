<?php
/**
 * Fix Cloudflare SSL Mode for PIM
 * Updates SSL setting from 'flexible' to 'full' or 'strict' to resolve redirect loops
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/BaseApi.php';
require_once __DIR__ . '/api/MonitorApi.php';

class CloudflareFixer extends BaseApi {
    public function fixSslMode() {
        $cf = Config::get('cloudflare');
        $zoneId = $cf['zone_id'];
        
        if (!$zoneId) {
            echo "Error: Cloudflare zone_id not found in configuration.\n";
            return;
        }

        echo "Checking current SSL setting for zone $zoneId...\n";
        
        $currentSsl = $this->cfApi("/zones/$zoneId/settings/ssl");
        if (!$currentSsl['body']['success']) {
            echo "Error fetching SSL setting: " . ($currentSsl['body']['errors'][0]['message'] ?? 'Unknown error') . "\n";
            return;
        }

        $sslMode = $currentSsl['body']['result']['value'];
        echo "Current SSL Mode: $sslMode\n";

        if ($sslMode === 'flexible') {
            echo "SSL Mode is 'flexible'. Changing to 'full'...\n";
            $update = $this->cfApi("/zones/$zoneId/settings/ssl", 'PATCH', ['value' => 'full']);
            if ($update['body']['success']) {
                echo "Successfully updated SSL Mode to 'full'.\n";
            } else {
                echo "Error updating SSL Mode: " . ($update['body']['errors'][0]['message'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "SSL Mode is already '$sslMode'. No change needed.\n";
        }
        
        echo "Purging Cloudflare cache for pim.technostationery.com...\n";
        $purge = $this->cfApi("/zones/$zoneId/purge_cache", 'POST', [
            'hosts' => ['pim.technostationery.com']
        ]);
        
        if ($purge['body']['success']) {
            echo "Cache purged successfully.\n";
        } else {
            echo "Error purging cache: " . ($purge['body']['errors'][0]['message'] ?? 'Unknown error') . "\n";
        }
    }

    private function cfApi($endpoint, $method = 'GET', $data = null) {
        $cf = Config::get('cloudflare');
        $url = "https://api.cloudflare.com/client/v4$endpoint";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = ["Content-Type: application/json"];
        if (!empty($cf['global_key'])) {
            $headers[] = "X-Auth-Email: " . $cf['email'];
            $headers[] = "X-Auth-Key: " . $cf['global_key'];
        } else {
            $headers[] = "Authorization: Bearer " . $cf['api_token'];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }
}

$fixer = new CloudflareFixer();
$fixer->fixSslMode();
