<?php
// System Test File
// This file tests basic functionality of the Client Requirements System

echo "<h2>System Test Results</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection Test</h3>";
try {
    require_once 'config.php';
    $conn = getDBConnection();
    echo "✓ Database connection successful<br>";
    
    // Test if tables exist
    $tables = ['clients', 'admin_users'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✓ Table '$table' exists<br>";
        } else {
            echo "✗ Table '$table' missing<br>";
        }
    }
    $conn->close();
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 2: File Permissions
echo "<h3>2. File Permissions Test</h3>";
$files_to_check = [
    'config.php' => 'Configuration file',
    'submit_form.php' => 'Form submission handler',
    'client-requirement-form.html' => 'Main client form',
    'admin/index.php' => 'Admin dashboard',
    'admin/login.php' => 'Admin login',
    'admin/view.php' => 'Client view page'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "✓ $description is readable<br>";
        } else {
            echo "✗ $description is not readable<br>";
        }
    } else {
        echo "✗ $description not found<br>";
    }
}

// Test 3: PHP Extensions
echo "<h3>3. PHP Extensions Test</h3>";
$required_extensions = ['mysqli', 'json', 'session', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ Extension '$ext' is loaded<br>";
    } else {
        echo "✗ Extension '$ext' is missing<br>";
    }
}

// Test 4: Session Configuration
echo "<h3>4. Session Configuration Test</h3>";
if (session_status() === PHP_SESSION_DISABLED) {
    echo "✗ Sessions are disabled<br>";
} else {
    echo "✓ Sessions are enabled<br>";
}

// Test 5: Memory and Upload Limits
echo "<h3>5. PHP Configuration Test</h3>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s<br>";

// Test 6: Form Submission Endpoint
echo "<h3>6. Form Submission Endpoint Test</h3>";
if (function_exists('curl_init')) {
    $test_data = [
        'client_id' => 'TEST-2026-9999',
        'contactName' => 'Test cURL User',
        'contactEmail' => 'test@example.com',
        'contactPhone' => '1234567890',
        'project_type' => 'website'
    ];
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $target_url = $protocol . '://' . $host . $uri_dir . '/submit_form.php';
    
    echo "<h4>Test 6A: JSON Format POST Submission</h4>";
    echo "Testing JSON POST to: <code>" . htmlspecialchars($target_url) . "</code><br>";
    
    $perf_curl_start = microtime(true);
    
    $ch = curl_init($target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $elapsed = round((microtime(true) - $perf_curl_start) * 1000, 2);
    echo "JSON Request completed in: <strong>" . $elapsed . " ms</strong><br>";
    
    if ($http_code === 200) {
        echo "<span class='success'>✓ JSON submission endpoint responding (HTTP 200)</span><br>";
        echo "Response: <pre style='background: #f4f4f4; padding: 10px; border-radius: 4px;'>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<span class='error'>✗ JSON submission endpoint error (HTTP $http_code)</span><br>";
        if ($curl_error) {
            echo "cURL Error: <code>" . htmlspecialchars($curl_error) . "</code><br>";
        } else {
            echo "Response: <pre style='background: #f4f4f4; padding: 10px; border-radius: 4px;'>" . htmlspecialchars($response) . "</pre>";
        }
    }
    
    echo "<h4>Test 6B: Standard Form URL-encoded POST Submission</h4>";
    echo "Testing Form POST to: <code>" . htmlspecialchars($target_url) . "</code><br>";
    
    $perf_curl_start2 = microtime(true);
    
    $ch = curl_init($target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $elapsed2 = round((microtime(true) - $perf_curl_start2) * 1000, 2);
    echo "Form Request completed in: <strong>" . $elapsed2 . " ms</strong><br>";
    
    if ($http_code === 200) {
        echo "<span class='success'>✓ Form submission endpoint responding (HTTP 200)</span><br>";
        echo "Response: <pre style='background: #f4f4f4; padding: 10px; border-radius: 4px;'>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<span class='error'>✗ Form submission endpoint error (HTTP $http_code)</span><br>";
        if ($curl_error) {
            echo "cURL Error: <code>" . htmlspecialchars($curl_error) . "</code><br>";
        } else {
            echo "Response: <pre style='background: #f4f4f4; padding: 10px; border-radius: 4px;'>" . htmlspecialchars($response) . "</pre>";
        }
    }
} else {
    echo "⚠ Cannot test form endpoint (cURL not available)<br>";
}

echo "<br><h3>Test Summary</h3>";
echo "<p>If all tests show ✓, your system is ready to use!</p>";
echo "<p>If you see ✗ marks, please address those issues before proceeding.</p>";

echo "<br><h3>Next Steps</h3>";
echo "<ol>";
echo "<li><a href='client-requirement-form.html'>Test the client form</a></li>";
// echo "<li><a href='admin/login.php'>Test admin login</a> (username: admin, password: admin123)</li>";
echo "<li>Submit a test form and verify it appears in the admin dashboard</li>";
echo "<li>Test PDF generation from the client view page</li>";
echo "</ol>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Test - Client Requirements System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            line-height: 1.6;
        }
        h2, h3 {
            color: #333;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
</body>
</html>
