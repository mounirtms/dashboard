<?php
/**
 * Cloudflare Configuration
 * 
 * Credentials loaded from environment variables and provided data
 * 
 * @version 2.0
 * @date 2026-05-03
 */

return [
    // API Token (recommended - most secure)
    'api_token' => getenv('CLOUDFLARE_API_TOKEN') ?: 'zflwN_9EYIx_UDQ6tcFQJt-4CJOjMxs5mnNncqVj',
    
    // Email and Global API Key (legacy - for full account access)
    'email' => 'webmaster@techno-dz.com',
    'api_key' => '35d8fd4b1a5d27eabbce73c6753978fc350bc',
    'global_key' => '35d8fd4b1a5d27eabbce73c6753978fc350bc',
    
    // Account Information
    'account_id' => getenv('CLOUDFLARE_ACCOUNT_ID') ?: 'cb89f9d4bfa5ff6fe2c8528847dbc5fe',
    
    // Primary Zone Configuration
    'zone_id' => getenv('CLOUDFLARE_ZONE_ID') ?: '4919ad3406fcabba381edbd543814a68',
    
    // Pre-configured zones for quick access
    'zones' => [
        'technostationery.com' => '4919ad3406fcabba381edbd543814a68',
    ],
    
    // Origin CA Key (for SSL certificate management)
    'origin_ca_key' => 'v1.0-e81a4a11ffcc64202d9c2157-2dd2bd7036993dc0edc30bb00a394eca11ca654f30650752c661e9c6df9bfcc398d44a2fc7634f17fba4a7830811e196063cb5c2bcb5274a524458f09fd93c4d53fe8f83a57c0d8677',
    
    // Dashboard API Token (if different from main token)
    'dashboard_api_token' => 'cfut_D4T7Fy8FNpNx8u2oQ9N13z9LQ9KoPQucNR9LSa0j737f4219',
    
    // API Settings
    'timeout' => 10,
    'retry_attempts' => 3,
    
    // Logging
    'log_actions' => true,
    'log_file' => '/home/dashboard/public_html/logs/cloudflare_actions.log',
    
    // Cache settings
    'cache_ttl' => 300, // 5 minutes cache for API responses
];
