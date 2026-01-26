<?php
// Access denied
http_response_code(403);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>403 Forbidden</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        h1 {
            color: #EF4444;
            font-size: 4rem;
            margin: 0;
        }
        h2 {
            color: #374151;
            font-size: 1.5rem;
            margin: 1rem 0;
        }
        p {
            color: #6B7280;
            margin: 1rem 0;
        }
        a {
            color: #FF6B35;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Access Forbidden</h2>
        <p>You don't have permission to access this directory.</p>
        <p><a href="../main.html">← Return to Documentation</a></p>
    </div>
</body>
</html>
