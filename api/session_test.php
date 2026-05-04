<?php
require_once __DIR__ . '/auth.php';
session_start();
if (empty($_SESSION['count'])) {
    $_SESSION['count'] = 0;
} else {
    $_SESSION['count']++;
}
echo "Count: " . $_SESSION['count'];
echo "<br>";
echo "Session ID: " . session_id();
