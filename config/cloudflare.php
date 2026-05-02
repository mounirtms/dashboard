<?php
/**
 * Cloudflare Configuration
 * 
 * Store your Cloudflare API credentials here
 * 
 * You can use either:
 * 1. API Token (recommended) - More secure, scoped permissions
 * 2. API Key + Email - Legacy method, full account access
 * 
 * Get your API credentials from:
 * https://dash.cloudflare.com/profile/api-tokens
 */

return [
    // Option 1: API Token (Recommended)
    'api_token' => getenv('CLOUDFLARE_API_TOKEN') ?: '',
    
    // Option 2: API Key + Email (Legacy)
    'api_key' => getenv('CLOUDFLARE_API_KEY') ?: '',
    'email' => getenv('CLOUDFLARE_EMAIL') ?: '',
    
    // Optional: Pre-configured zone IDs for quick access
    'zones' => [
        // 'example.com' => 'zone_id_here',
        // 'another-domain.com' => 'zone_id_here',
    ],
    
    // API Settings
    'timeout' => 10, // Request timeout in seconds
    'retry_attempts' => 3,
    
    // Logging
    'log_actions' => true,
    'log_file' => '/home/dashboard/public_html/logs/cloudflare_actions.log'
];
