<?php
/**
 * Varnish Warmup via Sitemap
 * Crawls the production sitemap and requests URLs through Varnish
 */

$sitemapUrl = "https://technostationery.com/sitemap.xml";
$varnishUrl = "http://127.0.0.1:80"; // Varnish port 80
$hostHeader = "technostationery.com";
$maxUrls = 1000; 

echo "Fetching sitemap: $sitemapUrl\n";
$xmlContent = @file_get_contents($sitemapUrl);
if (!$xmlContent) {
    die("Error: Could not fetch sitemap.\n");
}

preg_match_all('/<loc>(.*?)<\/loc>/', $xmlContent, $matches);
$urls = $matches[1] ?? [];

echo "Found " . count($urls) . " URLs. Warming up top $maxUrls...\n";

$count = 0;
foreach (array_slice($urls, 0, $maxUrls) as $url) {
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);
    $requestUrl = $varnishUrl . $path . ($query ? "?$query" : "");
    
    $ch = curl_init($requestUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Host: $hostHeader",
        "User-Agent: TechnoMonitor-Warmup/1.0",
        "Accept-Encoding: gzip"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $start = microtime(true);
    curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $count++;
    echo "[$count] $path - HTTP $code ($time ms)\n";
    
    if ($count % 50 === 0) sleep(1);
}

echo "\nWarmup completed. Current Varnish Status:\n";
echo shell_exec("varnishstat -1 | grep cache_hit");
