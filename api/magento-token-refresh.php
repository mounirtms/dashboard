<?php
/**
 * magento-token-refresh.php
 * CLI / cron script — refreshes the Magento JWT when it is within 4 h of expiry.
 * Designed to run every 6 hours via cron (six-hourly, minutes zero).
 *  0 0,6,12,18 * * *  php /home/dashboard/public_html/api/magento-token-refresh.php
 *  (avoid the 'star slash' cron shorthand inside comments: it ends the block)
 *
 * The MagentoToken class handles the actual refresh and writes the new token
 * back to /home/dashboard/public_html/config/magento_credentials.json
 * (which magento.php reads on every request).
 */

declare(strict_types=1);

// Override REFRESH_BUFFER for CLI: refresh if less than 4 h remain
define('CLI_TOKEN_REFRESH', true);

require_once __DIR__ . '/magento-token.php';

$ts   = date('Y-m-d H:i:s');
$info = null;

try {
    // info() calls get() internally, which auto-refreshes when < REFRESH_BUFFER (1 h)
    // We force a refresh here so the cron at 6-h intervals guarantees freshness
    $token = MagentoToken::get(/* forceRefresh = */ false);
    $info  = MagentoToken::info();

    $hours = $info['hours_remaining'];

    if ($hours < 4) {
        // Less than 4 hours left — force refresh now
        $token = MagentoToken::get(true);
        $info  = MagentoToken::info();
        echo "[{$ts}] FORCED REFRESH — was < 4 h remaining. New expiry: {$info['expires_at_iso']}\n";
    } else {
        echo "[{$ts}] OK — token valid for {$hours} h (expires {$info['expires_at_iso']})\n";
    }

} catch (Throwable $e) {
    echo "[{$ts}] ERROR — " . $e->getMessage() . "\n";
    exit(1);
}
