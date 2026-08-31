<?php
/**
 * Cloudflare Configuration
 *
 * Credentials loaded from environment variables via .env
 * Both the API token and Global API Key need to be regenerated
 * from the Cloudflare dashboard — current keys are invalid.
 */

// Load from environment variables (set by Config::load() from .env)
// Use CF_API_TOKEN from environment for scoped-token authentication (preferred).
$apiToken = getenv('CF_API_TOKEN') ?: ($_ENV['CF_API_TOKEN'] ?? '');
$globalKey = getenv('CF_GLOBAL_KEY') ?: ($_ENV['CF_GLOBAL_KEY'] ?? '');
$email = getenv('CF_EMAIL') ?: ($_ENV['CF_EMAIL'] ?? '');
$zoneId = getenv('CF_ZONE_ID') ?: ($_ENV['CF_ZONE_ID'] ?? '4919ad3406fcabba381edbd543814a68');
$accountId = getenv('CF_ACCOUNT_ID') ?: ($_ENV['CF_ACCOUNT_ID'] ?? '');

return [
    'api_token' => $apiToken,
    'email' => $email,
    'api_key' => $globalKey,
    'global_key' => $globalKey,
    'account_id' => $accountId,
    'zone_id' => $zoneId,

    'zones' => [
        'technostationery.com' => $zoneId,
    ],

    'origin_ca_key' => getenv('CF_ORIGIN_CA_KEY') ?: ($_ENV['CF_ORIGIN_CA_KEY'] ?? ''),

    'timeout' => 10,
    'retry_attempts' => 3,
    'log_actions' => true,
    'log_file' => '/home/dashboard/public_html/logs/cloudflare_actions.log',
    'cache_ttl' => 300,

    // Credential status — set to false until valid keys are provided
    'credentials_valid' => !empty($apiToken) || (!empty($globalKey) && !empty($email)),
];
