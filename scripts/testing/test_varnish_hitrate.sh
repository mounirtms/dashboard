#!/bin/bash

URL="https://technostationery.com/"
HOST="technostationery.com"
IP="127.0.0.1"
PORT="6081"

echo "Testing Varnish Hit Rate and Device Detection for $URL"
echo "--------------------------------------------------------"

# Test Desktop
echo "Testing Desktop..."
curl -s -I -H "Host: $HOST" -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36" "http://$IP:$PORT/" | grep -E "HTTP|X-Cache|X-Device|Cache-Control|Set-Cookie|Vary"
sleep 1
curl -s -I -H "Host: $HOST" -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36" "http://$IP:$PORT/" | grep -E "HTTP|X-Cache|X-Device"

echo -e "\nTesting Mobile..."
curl -s -I -H "Host: $HOST" -H "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1" "http://$IP:$PORT/" | grep -E "HTTP|X-Cache|X-Device|Cache-Control|Set-Cookie|Vary"
sleep 1
curl -s -I -H "Host: $HOST" -H "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1" "http://$IP:$PORT/" | grep -E "HTTP|X-Cache|X-Device"

echo -e "\nTesting Tablet..."
curl -s -I -H "Host: $HOST" -H "User-Agent: Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1" "http://$IP:$PORT/" | grep -E "X-Cache|X-Device"
sleep 1
curl -s -I -H "Host: $HOST" -H "User-Agent: Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1" "http://$IP:$PORT/" | grep -E "X-Cache|X-Device"

echo -e "\nVarnish Stats:"
varnishstat -1 | grep -E "cache_hit|cache_miss|g_bytes"
