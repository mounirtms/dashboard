#!/usr/bin/env php
<?php
/**
 * Simple source account status check
 */

// Get DB credentials from app/etc/env.php
$envConfig = require __DIR__ . '/app/etc/env.php';
$dbConfig = $envConfig['db']['connection']['default'];

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $dbConfig['host'],
            $dbConfig['port'] ?? 3306,
            $dbConfig['dbname']
        ),
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    
    echo "📊 Source Account Status Report\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Add status columns if they don't exist
    $addColumns = [
        "ALTER TABLE mab_yalidine_source_accounts ADD COLUMN IF NOT EXISTS last_api_test DATETIME DEFAULT NULL COMMENT 'Last API Test'",
        "ALTER TABLE mab_yalidine_source_accounts ADD COLUMN IF NOT EXISTS api_test_status VARCHAR(50) DEFAULT NULL COMMENT 'Status'",
        "ALTER TABLE mab_yalidine_source_accounts ADD COLUMN IF NOT EXISTS api_test_message TEXT DEFAULT NULL COMMENT 'Message'"
    ];
    
    foreach ($addColumns as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Column may already exist
        }
    }
    
    // Get all active source accounts
    $stmt = $pdo->query("
        SELECT 
            account_id,
            source_code,
            source_name,
            is_active,
            SUBSTRING(yalidin_app_id, 1, 30) as app_id,
            SUBSTRING(yalidin_token, 1, 30) as token,
            last_api_test,
            api_test_status,
            api_test_message
        FROM mab_yalidine_source_accounts 
        WHERE is_active = 1
        ORDER BY account_id
    ");
    
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "❌ No active source accounts found\n";
        exit(1);
    }
    
    echo "Found " . count($accounts) . " active source account(s):\n\n";
    
    foreach ($accounts as $i => $account) {
        $num = $i + 1;
        echo "[$num] {$account['source_name']} ({$account['source_code']})\n";
        echo "    Account ID: {$account['account_id']}\n";
        echo "    App ID: " . ($account['app_id'] ?: '❌ NOT SET') . "...\n";
        echo "    Token: " . ($account['token'] ?: '❌ NOT SET') . "...\n";
        
        if ($account['last_api_test']) {
            $status = $account['api_test_status'] ?: 'unknown';
            $icon = $status === 'success' ? '✅' : '❌';
            echo "    $icon Last Test: {$account['last_api_test']} - Status: $status\n";
            if ($account['api_test_message']) {
                $msg = substr($account['api_test_message'], 0, 100);
                echo "       Message: $msg\n";
            }
        } else {
            echo "    ⏳ Not tested yet\n";
        }
        echo "\n";
    }
    
    echo str_repeat("=", 70) . "\n";
    echo "📋 Status Summary:\n\n";
    
    $summary = $pdo->query("
        SELECT 
            COALESCE(api_test_status, 'not_tested') as status,
            COUNT(*) as count
        FROM mab_yalidine_source_accounts
        WHERE is_active = 1
        GROUP BY api_test_status
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summary as $row) {
        $icon = $row['status'] === 'success' ? '✅' : ($row['status'] === 'not_tested' ? '⏳' : '❌');
        echo "$icon {$row['status']}: {$row['count']}\n";
    }
    
    echo "\n✅ Report complete\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
