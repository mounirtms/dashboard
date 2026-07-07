<?php
/**
 * ============================================================================
 * Technostationery Varnish Cache Warmup - Per Device
 * ============================================================================
 * Warms Varnish cache for desktop, mobile, and tablet devices separately.
 * Uses curl_multi for high-performance parallel requests with load monitoring.
 * 
 * Usage: php warmup_per_device.php [--urls=3000] [--parallel=8] [--max-load=12]
 * 
 * Output: Logs to /home/dashboard/public_html/logs/ and sends email report
 * ============================================================================
 */

// ─── Configuration ──────────────────────────────────────────────────────────
$SITEMAP_FILE   = "/home/technadminy7/public_html/pub/sitemap.xml";
$VARNISH_HOST   = "http://127.0.0.1:80";
$DOMAIN         = "technostationery.com";
$LOG_DIR        = "/home/dashboard/public_html/logs";
$REPORT_EMAIL   = "webmaster@techno-dz.com";

// Default settings (overridable via CLI)
$defaults = [
    'max_urls'    => 3000,
    'parallel'    => 8,       // Concurrent requests per batch
    'max_load'    => 12.0,    // Pause warmup if load exceeds this
    'batch_delay' => 200000,  // Microseconds between batches (200ms)
    'timeout'     => 15,      // Seconds per request
];

// Device profiles with realistic user agents
$DEVICES = [
    'desktop' => [
        'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'label' => 'Desktop (Chrome/Windows)',
    ],
    'mobile' => [
        'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'label' => 'Mobile (Safari/iPhone)',
    ],
    'tablet' => [
        'ua' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'label' => 'Tablet (Safari/iPad)',
    ],
];

// Paths to exclude from warmup (private, dynamic, or non-HTML)
$EXCLUDE_PATHS = [
    '/customer/',
    '/checkout/',
    '/admin/',
    '/sysadminy',
    '/api/',
    '/dashboard/',
    '/health_check',
    '/pim.',
    '/rest/',
    '/graphql',
    '/newsletter/',
    '/search/',
    '/catalogsearch/',
    '/gifs-rules/',
];
$EXCLUDE_EXTENSIONS = ['.xml', '.json', '.pdf', '.zip', '.gz', '.tar'];

// ─── Parse CLI Arguments ────────────────────────────────────────────────────
$options = $defaults;
foreach ($_SERVER['argv'] as $arg) {
    if (preg_match('/^--(\w+)=(.+)$/', $arg, $m)) {
        $key = $m[1];
        $val = $m[2];
        if (isset($options[$key])) {
            $options[$key] = is_float($options[$key]) ? (float) $val : (int) $val;
        }
    }
}
extract($options);

// ─── Helpers ────────────────────────────────────────────────────────────────
$logFile = $LOG_DIR . '/warmup_per_device_' . date('Ymd_His') . '.log';
@mkdir($LOG_DIR, 0755, true);

function log_msg($msg, $echo = true) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($echo) echo $line . "\n";
    file_put_contents($logFile, $line . "\n", FILE_APPEND);
}

function get_load() {
    $load = sys_getloadavg();
    return (float) $load[0];
}

function should_pause($max_load) {
    return get_load() > $max_load;
}

function extract_urls($sitemap, $exclude_paths, $exclude_ext, $max_urls) {
    if (!file_exists($sitemap)) {
        return ['error' => "Sitemap not found: $sitemap"];
    }
    
    $xml = file_get_contents($sitemap);
    if (!preg_match_all('/<loc>(.*?)<\/loc>/', $xml, $matches)) {
        return ['error' => 'No URLs found in sitemap'];
    }
    
    $urls = [];
    foreach ($matches[1] as $url) {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        if (isset($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }
        
        // Skip excluded paths
        $skip = false;
        foreach ($exclude_paths as $ex) {
            if (strpos($path, $ex) !== false) { $skip = true; break; }
        }
        if ($skip) continue;
        
        // Skip excluded extensions
        foreach ($exclude_ext as $ex) {
            if (substr($path, -strlen($ex)) === $ex) { $skip = true; break; }
        }
        if ($skip) continue;
        
        $urls[] = $path;
        if (count($urls) >= $max_urls) break;
    }
    
    return array_values(array_unique($urls));
}

// ─── Warmup Engine ──────────────────────────────────────────────────────────
function warm_device_urls($urls, $device_key, $device_info, $varnish_host, $domain, $parallel, $max_load, $batch_delay, $timeout) {
    $total_urls = count($urls);
    $hits = 0;
    $misses = 0;
    $errors = 0;
    $processed = 0;
    $start_time = microtime(true);
    
    log_msg("");
    log_msg("=== {$device_info['label']} ===");
    log_msg("URLs to process: $total_urls");
    
    // Process in batches
    for ($i = 0; $i < $total_urls; $i += $parallel) {
        // Load check every batch
        if (should_pause($max_load)) {
            $load = get_load();
            log_msg("  LOAD PAUSE: Load=$load (max=$max_load), waiting 15s");
            sleep(15);
            // Re-check
            if (should_pause($max_load)) {
                log_msg("  Load still high (" . get_load() . "), continuing cautiously");
            }
        }
        
        $batch = array_slice($urls, $i, $parallel);
        $mh = curl_multi_init();
        $handles = [];
        
        $base_opts = [
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => $device_info['ua'],
            CURLOPT_HTTPHEADER => [
                "Host: $domain",
                "X-Forwarded-Proto: https",
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Encoding: gzip, deflate",
            ],
            CURLOPT_ENCODING => '',
        ];
        
        foreach ($batch as $path) {
            $ch = curl_init();
            curl_setopt_array($ch, $base_opts + [CURLOPT_URL => $varnish_host . $path]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }
        
        // Execute batch
        $batch_start = microtime(true);
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
            if (microtime(true) - $batch_start > ($timeout + 5)) {
                break; // Hard timeout
            }
        } while ($running > 0);
        
        // Process results
        foreach ($handles as $ch) {
            $response = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (preg_match('/X-Magento-Cache-Debug:\s*(HIT|MISS)/i', $response, $m)) {
                if ($m[1] === 'HIT') {
                    $hits++;
                } else {
                    $misses++;
                }
            } elseif ($http_code >= 200 && $http_code < 400) {
                // Valid response without cache header - count as HIT (grace/stale)
                $hits++;
            } else {
                $errors++;
            }
            $processed++;
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($mh);
        
        // Progress logging
        if ($processed % 200 == 0 || $processed == $total_urls) {
            $elapsed = round(microtime(true) - $start_time, 1);
            $rate = $elapsed > 0 ? round($processed / $elapsed, 1) : 0;
            $pct = round($processed / $total_urls * 100, 1);
            $load = get_load();
            log_msg(sprintf(
                "  [%s] %d/%d (%.1f%%) - HIT:%d MISS:%d ERR:%d [%.1fs | %s req/s | load:%.2f]",
                strtoupper($device_key),
                $processed,
                $total_urls,
                $pct,
                $hits,
                $misses,
                $errors,
                $elapsed,
                $rate,
                $load
            ));
        }
        
        // Delay between batches
        usleep($batch_delay);
    }
    
    $elapsed = round(microtime(true) - $start_time, 1);
    $processed_total = $hits + $misses;
    $hit_rate = $processed_total > 0 ? round($hits / $processed_total * 100, 1) : 0;
    
    log_msg(sprintf(
        "=> %s: %d HIT / %d MISS / %d ERR (%.1f%% hit rate) in %.1fs",
        strtoupper($device_key),
        $hits,
        $misses,
        $errors,
        $hit_rate,
        $elapsed
    ));
    
    return [
        'hits' => $hits,
        'misses' => $misses,
        'errors' => $errors,
        'hit_rate' => $hit_rate,
        'elapsed' => $elapsed,
    ];
}

// ─── Main Execution ─────────────────────────────────────────────────────────
log_msg("================================================================");
log_msg("TECHNOSTATIONERY VARNISH CACHE WARMUP - PER DEVICE");
log_msg("================================================================");
log_msg("Started: " . date('Y-m-d H:i:s'));
log_msg("Configuration:");
log_msg("  Max URLs:    $max_urls");
log_msg("  Parallel:    $parallel requests/batch");
log_msg("  Max Load:    $max_load");
log_msg("  Batch Delay: " . ($batch_delay / 1000) . "ms");
log_msg("  Timeout:     {$timeout}s");
log_msg("");

// Extract URLs
log_msg("Extracting URLs from sitemap...");
$urls = extract_urls($SITEMAP_FILE, $EXCLUDE_PATHS, $EXCLUDE_EXTENSIONS, $max_urls);
if (isset($urls['error'])) {
    log_msg("ERROR: " . $urls['error']);
    exit(1);
}
log_msg("Found " . count($urls) . " URLs (after filtering)");

// Check initial load
$initial_load = get_load();
log_msg("Current server load: $initial_load");
if ($initial_load > $max_load) {
    log_msg("WARNING: Load is high ($initial_load > $max_load). Warmup will pause periodically.");
}

// Warm each device
$overall_stats = [];
$warmup_start = microtime(true);

foreach ($DEVICES as $key => $info) {
    $result = warm_device_urls(
        $urls, $key, $info,
        $VARNISH_HOST, $DOMAIN,
        $parallel, $max_load, $batch_delay, $timeout
    );
    $overall_stats[$key] = $result;
}

$total_elapsed = round(microtime(true) - $warmup_start, 1);

// ─── Summary ────────────────────────────────────────────────────────────────
log_msg("");
log_msg("================================================================");
log_msg("WARMUP COMPLETE");
log_msg("================================================================");
log_msg("Total Time: {$total_elapsed}s");
log_msg("");

$total_hits = 0;
$total_misses = 0;
$total_errors = 0;

foreach ($DEVICES as $key => $info) {
    $s = $overall_stats[$key];
    log_msg(sprintf(
        "  %-10s: %5d HIT / %5d MISS / %4d ERR (%.1f%% hit rate) - %.1fs",
        strtoupper($key),
        $s['hits'],
        $s['misses'],
        $s['errors'],
        $s['hit_rate'],
        $s['elapsed']
    ));
    $total_hits += $s['hits'];
    $total_misses += $s['misses'];
    $total_errors += $s['errors'];
}

$grand_total = $total_hits + $total_misses;
$overall_rate = $grand_total > 0 ? round($total_hits / $grand_total * 100, 1) : 0;
$urls_per_sec = $grand_total > 0 ? round($grand_total / $total_elapsed, 1) : 0;

log_msg("");
log_msg(sprintf(
    "OVERALL: %d HIT / %d MISS / %d ERR (%.1f%% hit rate) - %.1f req/s",
    $total_hits, $total_misses, $total_errors, $overall_rate, $urls_per_sec
));

// Varnish stats
log_msg("");
log_msg("Varnish Statistics:");
$varnish_stats = shell_exec('varnishstat -1 2>/dev/null | grep -E "MAIN\.(cache_hit|cache_miss|client_req|n_object|bans) " | awk \'{printf "  %-35s %s\\n", $1, $2}\'');
log_msg($varnish_stats);

// Server load
$final_load = get_load();
log_msg("Server Load (end): $final_load");
log_msg("Log File: $logFile");

// ─── Generate & Send Email Report ───────────────────────────────────────────
$subject = "Varnish Cache Warmup Report - " . date('Y-m-d H:i:s');
$body = "
================================================================
TECHNOSTATIONERY - VARNISH CACHE WARMUP REPORT
================================================================
Date:            " . date('Y-m-d H:i:s') . "
Duration:        {$total_elapsed}s
Total Requests:  $grand_total
Throughput:      $urls_per_sec req/s

Per-Device Results:
";

foreach ($DEVICES as $key => $info) {
    $s = $overall_stats[$key];
    $body .= sprintf(
        "\n  %-10s (%s)\n",
        strtoupper($key),
        $info['label']
    );
    $body .= sprintf(
        "    HIT Rate:    %.1f%%\n    Hits:      %d\n    Misses:    %d\n    Errors:    %d\n    Duration:  %.1fs\n",
        $s['hit_rate'], $s['hits'], $s['misses'], $s['errors'], $s['elapsed']
    );
}

$body .= "
Overall Hit Rate:  $overall_rate%
Total Hits:        $total_hits
Total Misses:      $total_misses
Total Errors:      $total_errors

Server Load:
  Start: $initial_load
  End:   $final_load

Varnish Statistics:
$varnish_stats

Configuration Used:
  Max URLs:    $max_urls
  Parallel:    $parallel
  Max Load:    $max_load
  Batch Delay: " . ($batch_delay / 1000) . "ms
  Timeout:     {$timeout}s

Log File: $logFile
================================================================
";

// Send email
$headers = "From: cache-warmup@technostationery.com\r\n";
$headers .= "Reply-To: webmaster@techno-dz.com\r\n";
$headers .= "X-Mailer: Technostationery Cache Warmup\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($REPORT_EMAIL, $subject, $body, $headers)) {
    log_msg("Email report sent to $REPORT_EMAIL");
} else {
    log_msg("WARNING: Failed to send email report to $REPORT_EMAIL");
}

log_msg("Completed: " . date('Y-m-d H:i:s'));

exit(0);
