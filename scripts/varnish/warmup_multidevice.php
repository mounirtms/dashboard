<?php
/**
 * Simple Multi-Device Cache Warmup
 */

$sitemapUrl = "https://technostationery.com/sitemap.xml";
$maxUrls = 150;

$devices = [
    'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'tablet' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
    'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
];

echo "=== MULTI-DEVICE WARMUP ===\n";

$xml = @file_get_contents($sitemapUrl);
preg_match_all('/<loc>(.*?)<\/loc>/', $xml, $m);
$urls = array_slice($m[1], 0, $maxUrls);
echo count($urls) . " URLs to warm\n\n";

$stats = [];
foreach ($devices as $dev => $ua) {
    $hits = 0; $misses = 0;
    echo "\n[$dev]\n";
    foreach ($urls as $i => $url) {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $requestUrl = "http://127.0.0.1:80" . $path . ($query ? "?$query" : "");
        
        $ctx = stream_context_create(['http' => [
            'header' => "Host: technostationery.com\r\nUser-Agent: $ua\r\n",
            'timeout' => 5,
            'method' => 'HEAD',
            'ignore_errors' => true
        ]]);
        
        $start = microtime(true);
        @file_get_contents($requestUrl, false, $ctx);
        $time = round((microtime(true) - $start) * 1000);
        
        // Get cache status from headers
        $headers = implode("\n", $http_response_header ?? []);
        if (strpos($headers, 'X-Cache: HIT') !== false) {
            $hits++;
        } else {
            $misses++;
        }
        
        if (($i + 1) % 50 === 0) {
            echo "  [$i] H:$hits M:$misses\n";
        }
    }
    $stats[$dev] = ['hit' => $hits, 'miss' => $misses];
    $total = $hits + $misses;
    echo "  => " . round(($hits/$total)*100, 1) . "% hit rate\n";
}

echo "\n=== SUMMARY ===\n";
$totalH = 0; $totalM = 0;
foreach ($stats as $d => $s) {
    $t = $s['hit'] + $s['miss'];
    printf("%-10s: %3d HIT / %3d MISS (%5.1f%%)\n", strtoupper($d), $s['hit'], $s['miss'], ($t>0?($s['hit']/$t)*100:0));
    $totalH += $s['hit']; $totalM += $s['miss'];
}
$grand = $totalH + $totalM;
printf("OVERALL   : %3d HIT / %3d (%5.1f%%)\n", $totalH, $grand, ($grand>0?($totalH/$grand)*100:0));

echo shell_exec("varnishstat -1 2>/dev/null | grep -E 'cache_hit|cache_miss' | head -4");
