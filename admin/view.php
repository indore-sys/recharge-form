<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get client ID from URL
$client_id = $_GET['id'] ?? '';
if (empty($client_id)) {
    header('Location: index.php');
    exit();
}

// Get database connection
$conn = getDBConnection();

// Get client data
$sql = "SELECT * FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$client = $result->fetch_assoc();
$form_data = json_decode($client['form_data'], true);
$apiFiles = collectApiFiles($form_data);

// Get project type to determine which sections to show
$project_type = $form_data['project_type'] ?? '';

// DEBUG: Check all possible field names for project type
if (empty($project_type) && !empty($form_data['projectType'])) {
    $project_type = $form_data['projectType'];
}

$is_mobile_app = ($project_type === 'mobile-app' || $project_type === 'both');
$is_website = ($project_type === 'website' || $project_type === 'both');

// Debug info for troubleshooting
$debug_info = "Project Type: " . ($project_type ?: 'NOT SET') . " | Mobile: " . ($is_mobile_app ? 'YES' : 'NO') . " | Website: " . ($is_website ? 'YES' : 'NO');

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'] ?? 'New';
    
    $update_sql = "UPDATE clients SET status = ? WHERE client_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ss", $new_status, $client_id);
    $update_stmt->execute();
    
    $client['status'] = $new_status;
}

$stmt->close();
$conn->close();

// Helper function to display field value
function displayValue($value) {
    if (empty($value) || $value === 'Not Provided') {
        return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
    }
    return '<span style="font-size: 18px;">' . htmlspecialchars($value) . '</span>';
}

// Helper function to display formatted array values (improved version)
function displayArray($values) {
    if ($values === null || $values === '' || $values === []) {
        return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
    }

    if (is_string($values)) {
        $trimmed = trim($values);

        if ($trimmed === '') {
            return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $values = $decoded;
        } elseif (strpos($trimmed, ',') !== false) {
            $values = array_map('trim', explode(',', $trimmed));
        } else {
            $values = [$trimmed];
        }
    } elseif (!is_array($values)) {
        $values = [$values];
    }

    $flatValues = [];
    array_walk_recursive($values, function ($value) use (&$flatValues) {
        if ($value !== null && $value !== '') {
            $flatValues[] = $value;
        }
    });

    if (empty($flatValues)) {
        return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
    }

    return '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $flatValues)) . '</span>';
}

function normalizeArrayValues($values) {
    if ($values === null || $values === '' || $values === []) {
        return [];
    }

    if (is_string($values)) {
        $trimmed = trim($values);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $values = $decoded;
        } elseif (strpos($trimmed, ',') !== false) {
            $values = array_map('trim', explode(',', $trimmed));
        } else {
            $values = [$trimmed];
        }
    } elseif (!is_array($values)) {
        $values = [$values];
    }

    $flatValues = [];
    array_walk_recursive($values, function ($value) use (&$flatValues) {
        if ($value !== null) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                $flatValues[] = $trimmed;
            }
        }
    });

    return $flatValues;
}

function formatScreenLabel($screen) {
    $screen = trim((string) $screen);
    if ($screen === '') {
        return '';
    }

    if (strpos($screen, 'custom_') === 0) {
        $screen = substr($screen, 7);
    }

    $labelMap = [
        'login' => 'Login / Sign Up',
        'home' => 'Home / Dashboard',
        'profile' => 'User Profile',
        'settings' => 'Settings',
        'search' => 'Search',
        'notifications' => 'Notifications',
        'cart' => 'Cart / Checkout',
        'orders' => 'Orders / History',
        'favorites' => 'Favorites / Wishlist',
        'support' => 'Help / Support',
        'help' => 'Help / Support',
        'messages' => 'Messages / Chat',
        'map' => 'Map / Location',
        'location' => 'Map / Location',
        'dashboard' => 'Dashboard',
        'register' => 'Register',
        'checkout' => 'Checkout',
        'payment' => 'Payment',
        'wishlist' => 'Wishlist'
    ];

    if (isset($labelMap[$screen])) {
        return $labelMap[$screen];
    }

    return ucwords(str_replace('_', ' ', $screen));
}

function formatAppFeatureValues($values) {
    $featureLabels = [
        'login' => 'User Login',
        'registration' => 'User Registration',
        'payment' => 'Payment Integration',
        'notifications' => 'Push Notifications',
        'chat' => 'In-app Chat',
        'booking' => 'Booking System',
        'maps' => 'Maps/Location',
        'camera' => 'Camera Integration',
        'social' => 'Social Media Integration',
        'search' => 'Search Functionality',
        'offline' => 'Offline Mode',
        'multilingual' => 'Multi-language Support',
        'analytics' => 'Analytics',
        'custom' => 'Custom Features'
    ];

    $formatted = [];
    foreach (normalizeArrayValues($values) as $feature) {
        $formatted[] = $featureLabels[$feature] ?? ucwords(str_replace(['-', '_'], ' ', $feature));
    }

    return $formatted;
}

function formatMappedValue($value, array $labels) {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '';
    }

    return $labels[$normalized] ?? ucwords(str_replace(['-', '_'], ' ', $normalized));
}

function formatMappedValues($values, array $labels) {
    $formatted = [];
    foreach (normalizeArrayValues($values) as $value) {
        $formatted[] = formatMappedValue($value, $labels);
    }
    return $formatted;
}

function displayOpenDays($values) {
    if ($values === null || $values === '' || $values === []) {
        return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
    }

    if (is_string($values)) {
        $trimmed = trim($values);

        if ($trimmed === '') {
            return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $values = $decoded;
        } elseif (strpos($trimmed, ',') !== false) {
            $values = array_map('trim', explode(',', $trimmed));
        } else {
            $values = [$trimmed];
        }
    } elseif (!is_array($values)) {
        $values = [$values];
    }

    $dayLabels = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
        'weekdays' => 'Weekdays',
        'weekends' => 'Weekends',
        'all-days' => 'All Days'
    ];

    $displayDays = [];
    array_walk_recursive($values, function ($day) use (&$displayDays, $dayLabels) {
        if ($day === null || $day === '') {
            return;
        }
        $displayDays[] = $dayLabels[$day] ?? ucwords(str_replace('-', ' ', $day));
    });

    if (empty($displayDays)) {
        return '<span style="color: #999; font-size: 18px;">Not Provided</span>';
    }

    return '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayDays)) . '</span>';
}

function fieldAssetExists(array $formData, string $fieldName): bool {
    return !empty($formData[$fieldName . '_path']) || !empty($formData[$fieldName . '_data']);
}

// Function to display API files specifically
function collectApiFiles(array $formData): array {
    $apiFiles = [];

    if (isset($formData['app_api_files']) && is_array($formData['app_api_files'])) {
        foreach ($formData['app_api_files'] as $index => $file) {
            $apiFiles[$index] = [
                'index' => $index,
                'file' => $file,
                'field_name' => 'app_api_files[' . $index . ']',
            ];
        }
    }

    if (isset($formData['app_api_files[]']) && is_array($formData['app_api_files[]'])) {
        foreach ($formData['app_api_files[]'] as $index => $file) {
            $apiFiles[$index] = [
                'index' => $index,
                'file' => $file,
                'field_name' => 'app_api_files[' . $index . ']',
            ];
        }
    }

    foreach ($formData as $key => $value) {
        if (preg_match('/^app_api_files\[(\d+)\]$/', $key, $matches)) {
            $index = (int) $matches[1];
            $apiFiles[$index] = [
                'index' => $index,
                'file' => $value,
                'field_name' => $key,
            ];
        }
    }

    ksort($apiFiles);
    return array_values($apiFiles);
}

function displayApiFiles(array $formData): string {
    $apiFiles = collectApiFiles($formData);
    if (empty($apiFiles)) {
        return '<span style="color: #999; font-size: 18px;">No API files uploaded</span>';
    }

    $fileNames = [];
    foreach ($apiFiles as $file) {
        if (!empty($file['file'])) {
            $fileNames[] = $file['file'];
        }
    }

    if (empty($fileNames)) {
        return '<span style="color: #999; font-size: 18px;">No API files uploaded</span>';
    }

    return '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $fileNames)) . '</span>';
}

function fieldAssetUrl(string $clientId, string $fieldName, bool $download = false): string {
    $query = [
        'client_id' => $clientId,
        'type' => 'field',
        'field' => $fieldName,
    ];
    if ($download) {
        $query['download'] = '1';
    }
    return '../download_asset.php?' . http_build_query($query);
}

function pageAssetUrl(string $clientId, string $type, string $page, bool $download = false): string {
    $query = [
        'client_id' => $clientId,
        'type' => $type,
        'page' => $page,
    ];
    if ($download) {
        $query['download'] = '1';
    }
    return '../download_asset.php?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Details - <?php echo htmlspecialchars($client['client_id']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        /* Stitch-style design tokens */
        :root {
            --st-font: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --st-ink: #0f172a;
            --st-ink-muted: #475569;
            --st-ink-subtle: #64748b;
            --st-surface: #ffffff;
            --st-surface-2: #f8fafc;
            --st-surface-3: #f1f5f9;
            --st-border: rgba(15, 23, 42, 0.08);
            --st-border-strong: rgba(15, 23, 42, 0.12);
            --st-primary: #4f46e5;
            --st-primary-2: #7c3aed;
            --st-primary-soft: rgba(79, 70, 229, 0.1);
            --st-success: #059669;
            --st-success-hover: #047857;
            --st-danger: #dc2626;
            --st-danger-hover: #b91c1c;
            --st-warn: #ea580c;
            --st-warn-soft: rgba(234, 88, 12, 0.12);
            --st-radius-sm: 10px;
            --st-radius-md: 14px;
            --st-radius-lg: 20px;
            --st-radius-xl: 24px;
            --st-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
            --st-shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 12px 28px -8px rgba(15, 23, 42, 0.1);
            --st-shadow-lg: 0 20px 50px -20px rgba(15, 23, 42, 0.18);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--st-font);
            color: var(--st-ink);
            background:
                radial-gradient(1000px 520px at 0% -5%, rgba(124, 58, 237, 0.09), transparent 50%),
                radial-gradient(800px 480px at 100% 0%, rgba(79, 70, 229, 0.1), transparent 48%),
                var(--st-surface-2);
            font-size: 16px;
            line-height: 1.65;
            min-height: 100vh;
        }

        .header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(18px) saturate(1.5);
            -webkit-backdrop-filter: blur(18px) saturate(1.5);
            border-bottom: 1px solid var(--st-border);
            box-shadow: var(--st-shadow-sm);
            color: var(--st-ink);
            padding: 18px 0;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .header h1 {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--st-ink);
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: var(--st-shadow-sm);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--st-surface);
            color: var(--st-ink-muted);
            border: 1px solid var(--st-border-strong);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: var(--st-surface-3);
            color: var(--st-ink);
        }

        .btn-primary {
            background: linear-gradient(115deg, var(--st-primary) 0%, var(--st-primary-2) 100%);
            color: #fff;
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
        }

        .btn-pdf {
            background: linear-gradient(115deg, var(--st-success) 0%, #0d9488 100%);
            color: #fff;
        }

        .btn-pdf:hover {
            background: linear-gradient(115deg, var(--st-success-hover) 0%, #0f766e 100%);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }

        .btn-delete {
            background: var(--st-surface);
            color: var(--st-danger);
            border: 1px solid rgba(220, 38, 38, 0.35);
            box-shadow: none;
        }

        .btn-delete:hover {
            background: rgba(254, 226, 226, 0.6);
            border-color: var(--st-danger);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal {
            background: var(--st-surface);
            border-radius: var(--st-radius-lg);
            padding: 28px;
            max-width: 420px;
            width: 90%;
            box-shadow: var(--st-shadow-lg);
            border: 1px solid var(--st-border);
            animation: modalSlideIn 0.28s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-24px) scale(0.98);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--st-ink);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }

        .modal-icon {
            font-size: 18px;
        }

        .modal-message {
            color: var(--st-ink-muted);
            margin-bottom: 22px;
            line-height: 1.55;
            font-size: 15px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 10px 18px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .btn-modal-cancel {
            background: var(--st-surface-3);
            color: var(--st-ink-muted);
        }

        .btn-modal-cancel:hover {
            background: #e2e8f0;
            color: var(--st-ink);
        }

        .btn-modal-confirm {
            background: var(--st-danger);
            color: #fff;
        }

        .btn-modal-confirm:hover {
            background: var(--st-danger-hover);
        }

        .modal-success .modal-title {
            color: var(--st-success);
        }

        .modal-success .btn-modal-confirm {
            background: var(--st-success);
        }

        .modal-success .btn-modal-confirm:hover {
            background: var(--st-success-hover);
        }

        .container {
            max-width: 1360px;
            margin: 32px auto 48px;
            padding: 0 24px;
        }

        .client-info {
            background: var(--st-surface);
            padding: 28px 32px;
            border-radius: var(--st-radius-xl);
            box-shadow: var(--st-shadow-md);
            border: 1px solid var(--st-border);
            margin-bottom: 0;
        }

        .client-info h2 {
            color: var(--st-ink);
            margin-bottom: 20px;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .client-info h2::after {
            content: "";
            display: block;
            width: 48px;
            height: 4px;
            margin-top: 10px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--st-primary), var(--st-primary-2));
        }

        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .detail-item {
            padding: 16px 18px;
            background: var(--st-surface-2);
            border-radius: var(--st-radius-md);
            border: 1px solid var(--st-border);
        }

        .detail-label {
            font-weight: 600;
            color: var(--st-ink-subtle);
            margin-bottom: 6px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .detail-value {
            color: var(--st-ink);
            font-size: 17px;
            font-weight: 500;
        }

        .status-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .status-select {
            padding: 8px 14px;
            border: 1px solid var(--st-border-strong);
            border-radius: var(--st-radius-sm);
            font-size: 15px;
            font-family: inherit;
            background: var(--st-surface);
            color: var(--st-ink);
        }

        .section {
            background: var(--st-surface);
            margin-bottom: 0;
            border-radius: var(--st-radius-xl);
            box-shadow: var(--st-shadow-md);
            border: 1px solid var(--st-border);
            overflow: hidden;
            min-width: 0;
            page-break-inside: avoid;
        }

        .section-header {
            position: relative;
            background: var(--st-surface);
            color: var(--st-ink);
            padding: 20px 28px 20px 32px;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            border-bottom: 1px solid var(--st-border);
        }

        .section-header::before {
            content: "";
            position: absolute;
            left: 14px;
            top: 22px;
            bottom: 22px;
            width: 4px;
            border-radius: 999px;
            background: linear-gradient(180deg, var(--st-primary), var(--st-primary-2));
        }

        .section-content {
            padding: 28px 32px 32px;
            background: linear-gradient(180deg, var(--st-surface) 0%, var(--st-surface-2) 100%);
        }

        .field-group {
            margin-bottom: 26px;
            page-break-inside: avoid;
        }

        .field-group:last-child {
            margin-bottom: 0;
        }

        .field-grid > .field-group {
            margin-bottom: 0;
            padding: 18px 20px;
            background: var(--st-surface);
            border-radius: var(--st-radius-md);
            border: 1px solid var(--st-border);
            box-shadow: var(--st-shadow-sm);
        }

        .field-label {
            font-weight: 600;
            color: var(--st-ink-subtle);
            margin-bottom: 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .field-value {
            color: var(--st-ink);
            line-height: 1.65;
            font-size: 17px;
            font-weight: 500;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }

        .checkbox-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .checkbox-item {
            background: var(--st-primary-soft);
            color: var(--st-primary);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(79, 70, 229, 0.15);
        }

        .file-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }

        .file-status.uploaded {
            background: rgba(5, 150, 105, 0.12);
            color: var(--st-success);
        }

        .file-status.not-provided {
            background: var(--st-warn-soft);
            color: var(--st-warn);
        }

        .page-structure {
            background: var(--st-surface);
            padding: 18px 20px;
            border-radius: var(--st-radius-md);
            margin-top: 10px;
            border: 1px solid var(--st-border);
        }

        .page-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--st-border);
        }

        .page-item:last-child {
            border-bottom: none;
        }

        .page-name {
            flex: 1;
            font-weight: 600;
            font-size: 16px;
            color: var(--st-ink);
        }

        .page-type {
            background: linear-gradient(115deg, var(--st-primary), var(--st-primary-2));
            color: #fff;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 10px;
            letter-spacing: 0.02em;
        }

        .subsection {
            margin-top: 28px;
            padding: 22px 24px;
            background: var(--st-surface);
            border: 1px solid var(--st-border);
            border-radius: var(--st-radius-lg);
            box-shadow: var(--st-shadow-sm);
        }

        .subsection h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--st-ink);
            letter-spacing: -0.02em;
        }

        .content-card {
            margin-bottom: 24px;
            padding: 22px;
            border: 1px solid var(--st-border);
            border-radius: var(--st-radius-lg);
            background: var(--st-surface);
            box-shadow: var(--st-shadow-sm);
        }

        .content-card__title {
            margin: 0 0 14px 0;
            color: var(--st-ink);
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .content-card__section {
            margin-bottom: 18px;
            padding: 16px;
            border-radius: var(--st-radius-md);
        }

        .content-card__section--copy {
            background: rgba(245, 158, 11, 0.08);
            border-left: 4px solid #f59e0b;
        }

        .content-card__section--settings {
            background: rgba(59, 130, 246, 0.08);
            border-left: 4px solid #3b82f6;
        }

        .content-card__label {
            display: block;
            margin-bottom: 8px;
            color: var(--st-ink);
            font-weight: 700;
            font-size: 15px;
        }

        .content-card__value {
            color: var(--st-ink-muted);
            margin-top: 4px;
            line-height: 1.75;
            font-size: 16px;
            word-break: break-word;
        }

        .content-chip {
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 14px;
            font-weight: 600;
        }

        .content-badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 10px;
        }

        @media (max-width: 768px) {
            .client-details,
            .field-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .container {
                padding: 0 14px;
            }

            .section-content,
            .client-info {
                padding: 20px;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .header {
                position: static;
                background: #fff !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                border-bottom: 1px solid #ccc;
                box-shadow: none;
            }

            .header h1 {
                color: #000 !important;
            }

            .header-actions,
            .btn-pdf,
            .status-form,
            .btn-delete,
            .btn-view,
            .btn-modal,
            .modal-overlay,
            a[href*="download_asset.php"] {
                display: none !important;
            }

            .section,
            .client-info,
            .field-group,
            .field-grid,
            .detail-item,
            .content-card,
            .subsection {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            h2, h3, h4 {
                break-after: avoid;
                page-break-after: avoid;
            }

            img {
                max-width: 100%;
                height: auto;
                break-inside: avoid;
            }
        }

        @media not print {
            .section-content img,
            .client-info img,
            .content-card img,
            .subsection img {
                display: none !important;
            }

            div[style*="width: 80px"][style*="height: 80px"],
            div[style*="width: 100px"][style*="height: 100px"],
            div[style*="width: 150px"][style*="height: 150px"],
            div[style*="width: 100%"][style*="height: 200px"],
            div[style*="background: #f0f0f0"][style*="overflow: hidden"] {
                display: none !important;
            }
        }

        /* Two-column “bento” grid for section cards (Stitch-style dense layout) */
        #pdfContent {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            align-items: start;
            page-break-inside: auto;
        }

        #pdfContent > div:first-child,
        #pdfContent > .client-info {
            grid-column: 1 / -1;
        }

        @media (max-width: 1024px) {
            #pdfContent {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            #pdfContent {
                display: block !important;
            }

            .section {
                margin-bottom: 12px;
            }
        }

        /* Align legacy inline "Not Provided" spans with design tokens */
        .field-value span[style*="color: #999"],
        .detail-value span[style*="color: #999"],
        .content-card__value span[style*="color: #999"] {
            color: var(--st-ink-subtle) !important;
            font-size: 16px !important;
            font-weight: 400 !important;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Client Details: <?php echo htmlspecialchars($client['client_id']); ?></h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="generatePDF(event)" class="btn btn-pdf">📄 Download PDF</button>
                <button onclick="deleteClient()" class="btn btn-delete">🗑️ Delete</button>
            </div>
        </div>
    </header>

    <div class="container" id="pdfContent">
        <!-- Logo for PDF -->
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="../Rechh looo.png" alt="Logo" style="max-width: 180px; height: auto;">
        </div>

        <!-- Client Information -->
        <div class="client-info">
            <h2>Client Information</h2>
            <div class="client-details">
                <div class="detail-item">
                    <div class="detail-label">Client ID</div>
                    <div class="detail-value"><?php echo htmlspecialchars($client['client_id']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Name</div>
                    <div class="detail-value"><?php 
                        $display_name = $client['name'] ?: ($form_data['app_contactName'] ?? $form_data['contactName'] ?? '');
                        echo displayValue($display_name); 
                    ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php 
                        $display_email = $client['email'] ?: ($form_data['app_contactEmail'] ?? $form_data['contactEmail'] ?? '');
                        echo displayValue($display_email); 
                    ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?php 
                        $display_phone = $client['phone'] ?: ($form_data['app_contactPhone'] ?? $form_data['contactPhone'] ?? '');
                        echo displayValue($display_phone); 
                    ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Company</div>
                    <div class="detail-value"><?php 
                        $display_company = $client['company_name'] ?: ($form_data['app_name'] ?? $form_data['companyName'] ?? '');
                        echo displayValue($display_company); 
                    ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Submission Date</div>
                    <div class="detail-value"><?php echo date('M j, Y H:i', strtotime($client['created_at'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-form">
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 15px; font-weight: 500; 
                                <?php 
                                if ($client['status'] === 'New') echo 'background: #e3f2fd; color: #1976d2;';
                                elseif ($client['status'] === 'In Progress') echo 'background: #fff3e0; color: #f57c00;';
                                elseif ($client['status'] === 'Hold') echo 'background: #fce4ec; color: #c2185b;';
                                elseif ($client['status'] === 'Completed') echo 'background: #e8f5e8; color: #2e7d32;';
                                ?>">
                                <?php echo htmlspecialchars($client['status']); ?>
                            </span>
                        </span>
                        <form method="POST" class="status-form" style="margin-top: 8px;">
                            <select name="status" class="status-select">
                                <option value="New" <?php echo $client['status'] === 'New' ? 'selected' : ''; ?>>New</option>
                                <option value="In Progress" <?php echo $client['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Hold" <?php echo $client['status'] === 'Hold' ? 'selected' : ''; ?>>Hold</option>
                                <option value="Completed" <?php echo $client['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">Update</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_website): ?>
        <!-- WEBSITE SECTIONS -->

        <!-- Section 1: Project Basics -->
        <div class="section">
            <div class="section-header">1. Project Basics</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">Website Type</div>
                        <div class="field-value"><?php 
                        $app_type = $form_data['applicationType'] ?? '';
                        $app_type_labels = [
                            'informational' => 'Informational Website',
                            'online-store' => 'Online Store'
                        ];
                        echo displayValue($app_type_labels[$app_type] ?? $app_type); 
                        ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Brand Name</div>
                        <div class="field-value"><?php echo displayValue($form_data['brandName'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Brief Brand Description</div>
                        <div class="field-value"><?php echo displayValue($form_data['brandShortDescription'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Project Type</div>
                        <div class="field-value" style="font-weight: 600; color: #667eea;"><?php 
                        $pt = $form_data['project_type'] ?? '';
                        $pt_labels = ['website' => 'Website', 'mobile-app' => 'Mobile App', 'both' => 'Both (Website + Mobile App)'];
                        echo displayValue($pt_labels[$pt] ?? $pt); 
                        ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Business Category</div>
                        <div class="field-value">
                            <?php 
                            $businessCategory = $form_data['businessCategory'] ?? '';
                            $categoryLabels = [
                                'retail' => 'Retail & E-commerce',
                                'healthcare' => 'Healthcare & Medical',
                                'education' => 'Education & Training',
                                'technology' => 'Technology & IT Services',
                                'restaurant' => 'Restaurant & Food Services',
                                'realestate' => 'Real Estate & Construction',
                                'consulting' => 'Consulting & Professional Services',
                                'manufacturing' => 'Manufacturing & Industrial',
                                'other' => 'Other'
                            ];
                            echo displayValue($categoryLabels[$businessCategory] ?? $businessCategory);
                            ?>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Target Launch Date</div>
                        <div class="field-value"><?php echo displayValue($form_data['launchDate'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">Project Description</div>
                    <div class="field-value"><?php echo displayValue($form_data['projectDescription'] ?? ''); ?></div>
                </div>
                <div class="field-group">
                    <div class="field-label">Business Goals & Visitor Journey</div>
                    <div class="field-value">
                        <strong>Goals:</strong> 
                        <?php 
                        $goals = is_array($form_data['businessGoals'] ?? []) ? $form_data['businessGoals'] : [$form_data['businessGoals'] ?? []];
                        $goalLabels = [
                            'increase-sales' => 'Increase Sales',
                            'brand-awareness' => 'Brand Awareness',
                            'lead-generation' => 'Lead Generation',
                            'customer-support' => 'Customer Support',
                            'online-booking' => 'Online Booking',
                            'content-management' => 'Content Management',
                            'e-commerce' => 'E-commerce',
                            'user-engagement' => 'User Engagement',
                            'portfolio-showcase' => 'Portfolio Showcase',
                            'information-provision' => 'Information Provision',
                            'community-building' => 'Community Building',
                            'generate-leads' => 'Generate Leads',
                            'sell-online' => 'Sell Online',
                            'book-appointments' => 'Book Appointments',
                            'share-information' => 'Share Information'
                        ];
                        $displayGoals = [];
                        foreach ($goals as $goal) {
                            $displayGoals[] = $goalLabels[$goal] ?? ucwords(str_replace('-', ' ', $goal));
                        }
                        echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayGoals)) . '</span>';
                        ?><br>
                        
                        <strong>Target Audience:</strong> <?php echo displayValue($form_data['targetAudience'] ?? ''); ?><br>
                        <strong>Visitor Problem:</strong> <?php echo displayValue($form_data['visitorProblem'] ?? ''); ?><br>
                        
                        <strong>Primary Actions:</strong> 
                        <?php 
                        $actions = is_array($form_data['visitorActions'] ?? []) ? $form_data['visitorActions'] : [$form_data['visitorActions'] ?? []];
                        $actionLabels = [
                            'browse-products' => 'Browse Products',
                            'make-purchase' => 'Make Purchase',
                            'contact-support' => 'Contact Support',
                            'request-quote' => 'Request Quote',
                            'book-appointment' => 'Book Appointment',
                            'read-content' => 'Read Content',
                            'register-account' => 'Register Account',
                            'login-account' => 'Login to Account',
                            'share-content' => 'Share Content',
                            'subscribe-newsletter' => 'Subscribe Newsletter',
                            'call' => 'Call',
                            'whatsapp' => 'WhatsApp',
                            'inquiry-form' => 'Inquiry Form'
                        ];
                        $displayActions = [];
                        foreach ($actions as $action) {
                            $displayActions[] = $actionLabels[$action] ?? ucwords(str_replace('-', ' ', $action));
                        }
                        echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayActions)) . '</span>';
                        ?><br>
                        
                        <strong>Priority Focus:</strong> 
                        <?php 
                        $focus = is_array($form_data['priorityFocus'] ?? []) ? $form_data['priorityFocus'] : [$form_data['priorityFocus'] ?? []];
                        $focusLabels = [
                            'user-experience' => 'User Experience',
                            'performance-speed' => 'Performance & Speed',
                            'security' => 'Security',
                            'seo-optimization' => 'SEO Optimization',
                            'mobile-responsive' => 'Mobile Responsive',
                            'content-quality' => 'Content Quality',
                            'conversion-optimization' => 'Conversion Optimization',
                            'accessibility' => 'Accessibility',
                            'browser-compatibility' => 'Browser Compatibility',
                            'premium-design' => 'Premium Design',
                            'seo' => 'SEO'
                        ];
                        $displayFocus = [];
                        foreach ($focus as $f) {
                            $displayFocus[] = $focusLabels[$f] ?? ucwords(str_replace('-', ' ', $f));
                        }
                        echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayFocus)) . '</span>';
                        ?>
                    </div>
                </div>
                <?php if (!empty($form_data['otherProjectTypeInput'])): ?>
                    <div class="field-group">
                        <div class="field-label">Other Project Type</div>
                        <div class="field-value"><?php echo displayValue($form_data['otherProjectTypeInput']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($form_data['otherBusinessCategoryInput'])): ?>
                    <div class="field-group">
                        <div class="field-label">Other Business Category</div>
                        <div class="field-value"><?php echo displayValue($form_data['otherBusinessCategoryInput']); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 2: Technical Setup -->
        <div class="section">
            <div class="section-header">2. Technical Setup</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">Preferred Platform</div>
                        <div class="field-value"><?php echo displayValue($form_data['platform'] ?? ''); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($form_data['preferredCms'])): ?>
                    <div class="field-group">
                        <div class="field-label">Preferred CMS</div>
                        <div class="field-value"><?php echo displayValue($form_data['preferredCms']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="field-group">
                    <div class="field-label">Domain Information</div>
                    <div class="field-value">
                        <strong>Has Domain:</strong> <?php echo displayValue($form_data['hasDomain'] ?? ''); ?><br>
                        <?php if (!empty($form_data['domainName'])): ?>
                            <strong>Domain Name:</strong> <?php echo displayValue($form_data['domainName']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['domainProvider'])): ?>
                            <strong>Provider:</strong> <?php echo displayValue($form_data['domainProvider']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['domainRegistrationSource'])): ?>
                            <strong>Registered With:</strong> <?php echo displayValue($form_data['domainRegistrationSource']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['domainManagementPreference'])): ?>
                            <strong>Domain Handling:</strong> <?php echo displayValue($form_data['domainManagementPreference']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['preferredDomain'])): ?>
                            <strong>Preferred Domain:</strong> <?php echo displayValue($form_data['preferredDomain']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Hosting Information</div>
                    <div class="field-value">
                        <strong>Has Hosting:</strong> <?php echo displayValue($form_data['hasHosting'] ?? ''); ?><br>
                        <?php if (!empty($form_data['hostingProvider'])): ?>
                            <strong>Hosting Provider:</strong> <?php echo displayValue($form_data['hostingProvider']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['hostingPlan'])): ?>
                            <strong>Hosting Plan:</strong> <?php echo displayValue($form_data['hostingPlan']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['hostingUsername'])): ?>
                            <strong>Hosting Username:</strong> <?php echo displayValue($form_data['hostingUsername']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['hostingPassword'])): ?>
                            <strong>Hosting Password:</strong> <span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px; font-family: monospace;"><?php echo displayValue($form_data['hostingPassword']); ?></span><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['cpanelUrl'])): ?>
                            <strong>cPanel URL:</strong> <?php echo displayValue($form_data['cpanelUrl']); ?>
                        <?php endif; ?>
                        <?php if (!empty($form_data['getspaceRegistrationEmail'])): ?>
                            <br><strong>Getspace Email:</strong> <?php echo displayValue($form_data['getspaceRegistrationEmail']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($form_data['hasDomain']) && $form_data['hasDomain'] === 'yes'): ?>
                    <div class="field-group">
                        <div class="field-label">Domain Account Details</div>
                        <div class="field-value">
                            <?php if (!empty($form_data['domainUsername'])): ?>
                                <strong>Domain Username:</strong> <?php echo displayValue($form_data['domainUsername']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['domainPassword'])): ?>
                                <strong>Domain Password:</strong> <span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px; font-family: monospace;"><?php echo displayValue($form_data['domainPassword']); ?></span><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['domainRegistrar'])): ?>
                                <strong>Registrar Panel:</strong> <?php echo displayValue($form_data['domainRegistrar']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Security & SSL Section -->
                <div class="field-group">
                    <div class="field-label">Security & SSL</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['hasSSL'])): ?>
                            <strong>SSL Available:</strong> <?php echo displayValue($form_data['hasSSL']); ?><br>
                            <?php if ($form_data['hasSSL'] === 'yes'): ?>
                                <?php if (!empty($form_data['sslProvider'])): ?>
                                    <strong>Provider:</strong> <?php echo displayValue($form_data['sslProvider']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['sslExpiryDate'])): ?>
                                    <strong>Expiry Date:</strong> <?php echo displayValue($form_data['sslExpiryDate']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['sslType'])): ?>
                                    <strong>SSL Type:</strong> <?php echo displayValue($form_data['sslType']); ?><br>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($form_data['sslPurchase'])): ?>
                                    <strong>Purchase Required:</strong> <?php echo displayValue($form_data['sslPurchase']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['sslPreferredProvider'])): ?>
                                    <strong>Preferred Provider:</strong> <?php echo displayValue($form_data['sslPreferredProvider']); ?><br>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Not Provided</span>
                        <?php endif; ?>
                        <?php if (!empty($form_data['securityRequirements'])): ?>
                            <strong>Security Requirements:</strong> <?php echo displayValue($form_data['securityRequirements']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Theme Information</div>
                    <div class="field-value">
                        <strong>Has Theme:</strong> <?php echo displayValue($form_data['hasTheme'] ?? ''); ?><br>
                        <?php if (!empty($form_data['themeName'])): ?>
                            <strong>Theme Name:</strong> <?php echo displayValue($form_data['themeName']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['websitePageStyle'])): ?>
                            <strong>Page Style:</strong> <?php 
                            $page_style = $form_data['websitePageStyle'];
                            $page_style_labels = [
                                'one-page' => 'One Page',
                                'multi-page' => 'Multi Page'
                            ];
                            echo displayValue($page_style_labels[$page_style] ?? $page_style); 
                            ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['designStyle'])): ?>
                            <strong>Design Style:</strong> <?php 
                            $design_style = $form_data['designStyle'];
                            $design_style_labels = [
                                'simple-minimal' => 'Simple and minimalistic',
                                'modern-bold' => 'Modern and bold',
                                'classic-professional' => 'Classic and professional',
                                'luxury-premium' => 'Luxury and premium',
                                'playful-colorful' => 'Playful and colorful',
                                'team-choice' => 'Use any style you think is suitable'
                            ];
                            echo displayValue($design_style_labels[$design_style] ?? $design_style); 
                            ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['colorScheme'])): ?>
                            <strong>Color Scheme:</strong> <?php 
                            $color_scheme = $form_data['colorScheme'];
                            $color_scheme_labels = [
                                'team-choice' => 'Let team pick',
                                'navy-gold' => 'Navy / Gold',
                                'black-white' => 'Black / White',
                                'green-cream' => 'Green / Cream',
                                'blue-gray' => 'Blue / Gray',
                                'red-charcoal' => 'Red / Charcoal',
                                'teal-sand' => 'Teal / Sand',
                                'purple-silver' => 'Purple / Silver',
                                'orange-ink' => 'Orange / Ink',
                                'pink-brown' => 'Pink / Brown',
                                'earth-neutral' => 'Earth / Neutral',
                                'custom-colors' => 'Custom codes above'
                            ];
                            echo displayValue($color_scheme_labels[$color_scheme] ?? $color_scheme); 
                            ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['fontPreference'])): ?>
                            <strong>Font Preference:</strong> <?php 
                            $font_pref = $form_data['fontPreference'];
                            $font_pref_labels = [
                                'team-choice' => 'Let team pick two suitable fonts',
                                'clean-sans' => 'Clean sans-serif',
                                'classic-serif' => 'Classic serif',
                                'modern-geometric' => 'Modern geometric',
                                'elegant-display' => 'Elegant display',
                                'custom' => 'I will provide a font'
                            ];
                            echo displayValue($font_pref_labels[$font_pref] ?? $font_pref); 
                            ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['fontUrl'])): ?>
                            <strong>Font URL / Name:</strong> <?php echo displayValue($form_data['fontUrl']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['referenceWebsite1']) || !empty($form_data['referenceWebsite2']) || !empty($form_data['referenceWebsite3'])): ?>
                            <strong>Reference Sites:</strong>
                            <?php echo displayArray(array_filter([$form_data['referenceWebsite1'] ?? '', $form_data['referenceWebsite2'] ?? '', $form_data['referenceWebsite3'] ?? ''])); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['designComments'])): ?>
                            <strong>Design Comments:</strong> <?php echo nl2br(htmlspecialchars($form_data['designComments'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Content & Legal -->
        <div class="section">
            <div class="section-header">3. Content & Legal</div>
            <div class="section-content">
                
                <!-- Branding Subsection -->
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-bottom: 15px;">🎨 BRANDING INFORMATION</h4>
                    <div class="field-grid">
                        <div class="field-group">
                            <div class="field-label">Company Name</div>
                            <div class="field-value"><?php echo displayValue($form_data['companyName'] ?? 'Not Provided'); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Tagline/Slogan</div>
                            <div class="field-value"><?php echo displayValue($form_data['tagline'] ?? 'Not Provided'); ?></div>
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field-group">
                            <div class="field-label">Logo Status</div>
                            <div class="field-value"><?php echo displayValue($form_data['hasLogo'] ?? 'Not Provided'); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Logo File Link</div>
                            <div class="field-value">
                                <?php 
                                if (!empty($form_data['logoLink'])) {
                                    echo '<a href="' . htmlspecialchars($form_data['logoLink']) . '" target="_blank" style="color: #3498db; text-decoration: none;">🔗 View Logo File</a>';
                                } else {
                                    echo 'Not Provided';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                   <?php if (fieldAssetExists($form_data, 'logoFile')): ?>
                    <div class="field-group">
                        <div class="field-label">Uploaded Logo File</div>
                        <div class="field-value">
                            <?php 
                            $logoFileName = $form_data['logoFile'] ?? 'logo';
                            $logoFileType = $form_data['logoFile_type'] ?? 'image/jpeg';
                            $isImageFile = strpos($logoFileType, 'image/') === 0;
                            ?>
                            <?php if ($isImageFile): ?>
                                <div style="margin-bottom: 15px;">
                                    <img src="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=logo" 
                                         alt="Logo" 
                                         style="max-width: 300px; height: auto; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div style="display: none; padding: 20px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px;">
                                        <p style="margin: 0; color: #6c757d;">🖼️ Image preview not available</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span style="font-size: 18px; color: #6c757d;">
                                    📄 File: <?php echo htmlspecialchars($logoFileName); ?>
                                </span>
                                <span style="font-size: 12px; color: #999; background: #e9ecef; padding: 2px 6px; border-radius: 4px;">
                                    <?php echo htmlspecialchars($logoFileType); ?>
                                </span>
                                <a href="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=logo&download=1" 
                                   style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; font-size: 18px; font-weight: 600; transition: background 0.3s;"
                                   onmouseover="this.style.background='#0056b3';" 
                                   onmouseout="this.style.background='#007bff';">
                                    📥 Download
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                    <?php if (!empty($form_data['logoSize'])): ?>
                        <div class="field-group">
                            <div class="field-label">Logo Size (Dimensions)</div>
                            <div class="field-value"><?php echo displayValue($form_data['logoSize']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($form_data['logoColors'])): ?>
                    <div class="field-group">
                        <div class="field-label">Logo Colors & Symbols</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['logoColors'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['temporaryLogoText']) || !empty($form_data['professionalLogoText']) || !empty($form_data['professionalLogoDescription'])): ?>
                    <div class="field-group">
                        <div class="field-label">Logo Request Details</div>
                        <div class="field-value">
                            <?php if (!empty($form_data['temporaryLogoText'])): ?>
                                <strong>Temporary Logo Text:</strong> <?php echo displayValue($form_data['temporaryLogoText']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['professionalLogoText'])): ?>
                                <strong>Professional Logo Text:</strong> <?php echo displayValue($form_data['professionalLogoText']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['professionalLogoDescription'])): ?>
                                <strong>Professional Logo Brief:</strong> <?php echo nl2br(htmlspecialchars($form_data['professionalLogoDescription'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="field-group">
                        <div class="field-label">Brand Guidelines</div>
                        <div class="field-value"><?php echo displayValue($form_data['brandGuidelines'] ?? 'Not Provided'); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Asset Links</div>
                        <div class="field-value"><?php echo displayValue($form_data['assetLinks'] ?? ''); ?></div>
                    </div>
                    <?php if (!empty($form_data['businessAssetsFile'])): ?>
                    <div class="field-group">
                        <div class="field-label">Uploaded Business Assets</div>
                        <div class="field-value">
                            <strong>File:</strong> <?php echo displayValue($form_data['businessAssetsFile']); ?><br>
                            <strong>Type:</strong> <?php echo displayValue($form_data['businessAssetsFile_type'] ?? ''); ?><br>
                            <?php if (fieldAssetExists($form_data, 'businessAssetsFile')): ?>
                                <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 18px; color: #6c757d;">
                                        File: <?php echo htmlspecialchars($form_data['businessAssetsFile']); ?>
                                    </span>
                                    <a href="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=business-assets&download=1" 
                                       style="display: inline-block; padding: 10px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 600;" 
                                       target="_blank">
                                        📥 Download Business Assets
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color: #7f8c8d; font-style: italic;">Business assets data not available for download</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                    <div class="field-group">
                        <div class="field-label">Brand Voice/Tone</div>
                        <div class="field-value">
                            <?php 
                            $brandVoice = $form_data['brandVoice'] ?? '';
                            if (!empty($brandVoice)) {
                                $brandVoiceLabels = [
                                    'professional' => 'Professional',
                                    'friendly' => 'Friendly & Casual',
                                    'formal' => 'Formal',
                                    'playful' => 'Playful',
                                    'luxury' => 'Luxury & Premium'
                                ];
                                $displayVoice = $brandVoiceLabels[$brandVoice] ?? $brandVoice;
                                echo '<span style="background: #e8f5e8; padding: 3px 8px; border-radius: 4px; font-weight: 500;">' . htmlspecialchars($displayVoice) . '</span>';
                            } else {
                                echo 'Not Provided';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Company/GST/Billing Details -->
                    <div class="field-group" style="margin-top: 20px;">
                        <div class="field-label">Company / GST / Billing Details</div>
                        <div class="field-value">
                            <?php 
                            $addCompanyDetails = $form_data['addCompanyDetails'] ?? '';
                            if ($addCompanyDetails === 'yes') {
                                echo '<span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-weight: 500;">✓ Provided</span>';
                            } else {
                                echo '<span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-weight: 500;">✗ Not Provided</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <?php if ($addCompanyDetails === 'yes'): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; border: 1px solid #dee2e6;">
                        <div class="field-grid">
                            <div class="field-group">
                                <div class="field-label">Company Code</div>
                                <div class="field-value"><?php echo displayValue($form_data['companyCode'] ?? 'Not Provided'); ?></div>
                            </div>
                            <div class="field-group">
                                <div class="field-label">GST / VAT Number</div>
                                <div class="field-value"><?php echo displayValue($form_data['gstNumber'] ?? 'Not Provided'); ?></div>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <div class="field-label">Business Registration Number</div>
                                <div class="field-value"><?php echo displayValue($form_data['registrationNumber'] ?? 'Not Provided'); ?></div>
                            </div>
                            <div class="field-group">
                                <div class="field-label">Bank Account Number / IBAN</div>
                                <div class="field-value"><?php echo displayValue($form_data['bankAccount'] ?? 'Not Provided'); ?></div>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <div class="field-label">Bank Name</div>
                                <div class="field-value"><?php echo displayValue($form_data['bankName'] ?? 'Not Provided'); ?></div>
                            </div>
                            <div class="field-group">
                                <div class="field-label">IFSC / SWIFT Code</div>
                                <div class="field-value"><?php echo displayValue($form_data['ifscCode'] ?? 'Not Provided'); ?></div>
                            </div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Company Address</div>
                            <div class="field-value"><?php echo displayValue($form_data['companyAddress'] ?? 'Not Provided'); ?></div>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <div class="field-label">City</div>
                                <div class="field-value"><?php echo displayValue($form_data['city'] ?? 'Not Provided'); ?></div>
                            </div>
                            <div class="field-group">
                                <div class="field-label">State</div>
                                <div class="field-value"><?php echo displayValue($form_data['state'] ?? 'Not Provided'); ?></div>
                            </div>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <div class="field-label">Country</div>
                                <div class="field-value"><?php echo displayValue($form_data['country'] ?? 'Not Provided'); ?></div>
                            </div>
                            <div class="field-group">
                                <div class="field-label">Pincode</div>
                                <div class="field-value"><?php echo displayValue($form_data['pincode'] ?? 'Not Provided'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Content Collection Subsection -->
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 8px; margin-bottom: 18px;">📄 CONTENT COLLECTION</h4>
                <div class="field-group">
                    <div class="field-label">Content Ready Status</div>
                    <div class="field-value"><?php echo displayValue($form_data['contentReady'] ?? 'Not Provided'); ?></div>
                </div>
                <div class="field-group">
                    <div class="field-label">Content & Media Support</div>
                    <div class="field-value">
                        <strong>Content Support:</strong> 
                        <?php 
                        $contentSupport = is_array($form_data['contentSupport'] ?? []) ? $form_data['contentSupport'] : [$form_data['contentSupport'] ?? []];
                        $contentSupportLabels = [
                            'rewrite' => 'Rewrite my content',
                            'copywriting' => 'Create content from scratch',
                            'placeholder' => 'Use placeholder content first',
                            'proofread' => 'Proofread only',
                            'not-sure' => 'Not sure, recommend for me'
                        ];
                        $displayContentSupport = [];
                        foreach ($contentSupport as $support) {
                            $displayContentSupport[] = $contentSupportLabels[$support] ?? ucwords(str_replace('-', ' ', $support));
                        }
                        echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayContentSupport)) . '</span>';
                        ?><br>
                        
                        <strong>Media Support:</strong> 
                        <?php 
                        $mediaSupport = is_array($form_data['mediaSupport'] ?? []) ? $form_data['mediaSupport'] : [$form_data['mediaSupport'] ?? []];
                        $mediaSupportLabels = [
                            'stock-images' => 'Use stock images',
                            'image-editing' => 'Edit provided images',
                            'product-cleanup' => 'Product photo cleanup',
                            'banner-design' => 'Design banners',
                            'video-embed' => 'Embed videos'
                        ];
                        $displayMediaSupport = [];
                        foreach ($mediaSupport as $support) {
                            $displayMediaSupport[] = $mediaSupportLabels[$support] ?? ucwords(str_replace('-', ' ', $support));
                        }
                        echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayMediaSupport)) . '</span>';
                        ?>
                    </div>
                </div>
                </div>

                
                <!-- Page Structure Information -->
                <div class="field-group">
                    <div class="field-label">Header Pages</div>
                    <div class="field-value"><?php echo displayValue(implode(', ', $form_data['headerPages'] ?? [])); ?></div>
                </div>

                <div class="field-group">
                    <div class="field-label">Footer Pages</div>
                    <div class="field-value"><?php echo displayValue(implode(', ', $form_data['footerPages'] ?? [])); ?></div>
                </div>

                <!-- Page Contents -->
                <?php if (!empty($form_data['pageContents']) && is_array($form_data['pageContents'])): ?>
                    <div class="field-group">
                        <div class="field-label">Page Contents</div>
                        <div class="field-value">
                            <?php foreach ($form_data['pageContents'] as $pageName => $content):
                                // Check if this is the contact page with form builder
                                $isContactForm = (strtolower($pageName) === 'contact');
                            ?>
                                <div class="content-card">
                                    <div style="border-bottom: 2px solid #3498db; padding-bottom: 12px; margin-bottom: 18px;">
                                        <h4 class="content-card__title" style="text-transform: capitalize;">
                                            <?php echo htmlspecialchars($pageName); ?> Page
                                        </h4>
                                    </div>

                                    <?php if ($isContactForm): ?>
                                        <!-- Contact Page Copy -->
                                        <?php if (!empty($form_data['pageHeadings'][$pageName]) || !empty($form_data['pageSubheadings'][$pageName]) || !empty($content)): ?>
                                            <div class="content-card__section content-card__section--copy">
                                                <h5 style="margin: 0 0 14px 0; color: #2c3e50; font-size: 16px;">📝 Contact Page Content</h5>

                                                <?php if (!empty($form_data['pageHeadings'][$pageName])): ?>
                                                    <div style="margin-bottom: 14px;">
                                                        <strong class="content-card__label">Heading:</strong>
                                                        <div class="content-card__value"><?php echo htmlspecialchars($form_data['pageHeadings'][$pageName]); ?></div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($form_data['pageSubheadings'][$pageName])): ?>
                                                    <div style="margin-bottom: 14px;">
                                                        <strong class="content-card__label">Subheading:</strong>
                                                        <div class="content-card__value">
                                                            <?php echo nl2br(htmlspecialchars($form_data['pageSubheadings'][$pageName])); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($content)): ?>
                                                    <div style="margin-bottom: 8px;">
                                                        <strong class="content-card__label">Content:</strong>
                                                        <div class="content-card__value">
                                                            <?php echo nl2br(htmlspecialchars($content)); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Contact Form Settings -->
                                        <div class="content-card__section content-card__section--settings">
                                            <h5 style="margin: 0 0 14px 0; color: #2c3e50; font-size: 16px;">📧 Contact Form Configuration</h5>

                                            <?php if (!empty($form_data['contactFormTitle'])): ?>
                                                <div style="margin-bottom: 14px;">
                                                    <strong class="content-card__label">Form Title:</strong>
                                                    <div class="content-card__value"><?php echo htmlspecialchars($form_data['contactFormTitle']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($form_data['contactFormSubmitText'])): ?>
                                                <div style="margin-bottom: 14px;">
                                                    <strong class="content-card__label">Submit Button:</strong>
                                                    <div class="content-card__value"><?php echo htmlspecialchars($form_data['contactFormSubmitText']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($form_data['contactFormThankYou'])): ?>
                                                <div style="margin-bottom: 14px;">
                                                    <strong class="content-card__label">Thank You Message:</strong>
                                                    <div class="content-card__value" style="font-style: italic;">
                                                        <?php echo nl2br(htmlspecialchars($form_data['contactFormThankYou'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($form_data['contactFormAdminEmail'])): ?>
                                                <div style="margin-bottom: 14px;">
                                                    <strong class="content-card__label">Admin Email:</strong>
                                                    <div class="content-card__value"><?php echo htmlspecialchars($form_data['contactFormAdminEmail']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (empty($form_data['contactFormTitle']) && empty($form_data['contactFormSubmitText']) && empty($form_data['contactFormThankYou']) && empty($form_data['contactFormAdminEmail'])): ?>
                                                <div style="color: #7f8c8d; font-style: italic;">No contact form settings provided.</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Contact Form Fields -->
                                        <div style="margin-bottom: 20px;">
                                            <h5 style="margin: 0 0 14px 0; color: #2c3e50; font-size: 18px;">📝 Form Fields</h5>
                                            <div class="content-badge-grid">
                                                <?php
                                                $contactFields = [
                                                    'name' => ['icon' => '👤', 'label' => 'Name'],
                                                    'email' => ['icon' => '✉️', 'label' => 'Email'],
                                                    'phone' => ['icon' => '📞', 'label' => 'Phone'],
                                                    'message' => ['icon' => '📝', 'label' => 'Message']
                                                ];
                                                foreach ($contactFields as $fieldKey => $fieldInfo):
                                                    $fieldName = "contactField_{$fieldKey}";
                                                    $isEnabled = !empty($form_data[$fieldName]) && $form_data[$fieldName] === 'yes';
                                                ?>
                                                    <div style="padding: 12px 14px; background: <?php echo $isEnabled ? '#d4edda' : '#f8d7da'; ?>; border-radius: 12px; text-align: center;">
                                                        <span style="font-size: 16px;"><?php echo $fieldInfo['icon']; ?></span>
                                                        <div style="font-size: 18px; color: <?php echo $isEnabled ? '#155724' : '#721c24'; ?>; font-weight: 600; margin-top: 4px;">
                                                            <?php echo $fieldInfo['label']; ?>: <?php echo $isEnabled ? '✓ Yes' : '✗ No'; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Custom Fields -->
                                        <?php
                                        // Check for custom fields
                                        $customFields = [];
                                        foreach ($form_data as $key => $value) {
                                            if (preg_match('/^contactCustomField_(\d+)_label$/', $key, $matches)) {
                                                $index = $matches[1];
                                                $label = $value;
                                                $type = $form_data["contactCustomField_{$index}_type"] ?? 'text';
                                                $customFields[] = ['label' => $label, 'type' => $type];
                                            }
                                        }
                                        if (!empty($customFields)):
                                        ?>
                                            <div style="margin-bottom: 15px;">
                                                <h5 style="margin: 0 0 14px 0; color: #2c3e50; font-size: 18px;">🔧 Custom Fields</h5>
                                                <ul style="list-style: none; padding: 0; margin: 0;">
                                                    <?php foreach ($customFields as $customField): ?>
                                                        <li style="padding: 12px 14px; margin-bottom: 10px; background: white; border-radius: 10px; border: 1px solid #dee2e6;">
                                                            <strong style="font-size: 18px;"><?php echo htmlspecialchars($customField['label']); ?></strong>
                                                            <span style="color: #6c757d; font-size: 18px; margin-left: 8px;">(<?php echo htmlspecialchars($customField['type']); ?>)</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <!-- Regular Page Content -->
                                        <?php if (!empty($form_data['pageHeadings'][$pageName])): ?>
                                            <div style="margin-bottom: 14px;">
                                                <strong class="content-card__label">Heading:</strong>
                                                <div class="content-card__value" style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($form_data['pageHeadings'][$pageName]); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($form_data['pageSubheadings'][$pageName])): ?>
                                            <div style="margin-bottom: 14px;">
                                                <strong class="content-card__label">Subheading:</strong>
                                                <div class="content-card__value">
                                                    <?php echo htmlspecialchars($form_data['pageSubheadings'][$pageName]); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($content)): ?>
                                            <div style="margin-bottom: 16px;">
                                                <strong class="content-card__label">Content:</strong>
                                                <div class="content-card__value">
                                                    <?php echo nl2br(htmlspecialchars($content)); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($form_data['pageImages'][$pageName])): ?>
                                            <div style="margin-bottom: 16px;">
                                                <strong class="content-card__label">Page Image:</strong>
                                                <div style="margin-top: 8px;">
                                                    <?php
                                                    $imageData = $form_data['pageImages'][$pageName];
                                                    if ((is_array($imageData) && (!empty($imageData['path']) || !empty($imageData['data']))) || (is_string($imageData) && !empty($imageData))):
                                                        $imageFileName = is_array($imageData) ? ($imageData['fileName'] ?? $pageName . '.jpg') : ($pageName . '.jpg');
                                                        $imageFileType = is_array($imageData) ? ($imageData['fileType'] ?? 'image/jpeg') : 'image/jpeg';
                                                        $isImageFile = strpos($imageFileType, 'image/') === 0;
                                                    ?>
                                                        <?php if ($isImageFile): ?>
                                                            <div style="margin-bottom: 15px;">
                                                                <img src="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=page-image&page=<?php echo urlencode($pageName); ?>" 
                                                                     alt="<?php echo htmlspecialchars($pageName); ?> Image" 
                                                                     style="max-width: 250px; height: auto; border-radius: 6px; border: 1px solid #ddd; box-shadow: 0 2px 6px rgba(0,0,0,0.1);"
                                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                                <div style="display: none; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px;">
                                                                    <p style="margin: 0; color: #6c757d; font-size: 13px;">🖼️ Image preview not available</p>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                                                            <span style="font-size: 13px; color: #6c757d;">
                                                                📄 <?php echo htmlspecialchars($imageFileName); ?>
                                                            </span>
                                                            <span style="font-size: 11px; color: #999; background: #e9ecef; padding: 2px 5px; border-radius: 3px;">
                                                                <?php echo htmlspecialchars($imageFileType); ?>
                                                            </span>
                                                            <a href="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=page-image&page=<?php echo urlencode($pageName); ?>&download=1" 
                                                               style="display: inline-block; padding: 6px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 13px; font-weight: 600; transition: background 0.3s;" 
                                                               target="_blank"
                                                               onmouseover="this.style.background='#1e7e34';" 
                                                               onmouseout="this.style.background='#28a745';">
                                                                📥 Download
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: #7f8c8d; font-style: italic;">Image data not available</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Contact Form Configuration (Standalone Section) -->
                <?php if (!empty($form_data['contactFormTitle']) || !empty($form_data['contactFormSubmitText']) || !empty($form_data['contactFormThankYou']) || !empty($form_data['contactFormAdminEmail']) || !empty($form_data['contactField_name']) || !empty($form_data['contactField_email']) || !empty($form_data['contactField_phone']) || !empty($form_data['contactField_message'])): ?>
                    <div class="field-group">
                        <div class="field-label">📧 Contact Form Configuration</div>
                        <div class="field-value">
                            <div class="content-card">
                                <div style="border-bottom: 2px solid #3498db; padding-bottom: 12px; margin-bottom: 18px;">
                                    <h4 class="content-card__title">Contact Page Form Settings</h4>
                                </div>

                                <!-- Contact Form Settings -->
                                <div class="content-card__section content-card__section--settings" style="margin-bottom: 20px;">
                                    <h5 style="margin: 0 0 14px 0; color: #2c3e50; font-size: 18px;">Form Settings</h5>

                                    <?php if (!empty($form_data['contactFormTitle'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <strong class="content-card__label">Form Title:</strong>
                                            <div class="content-card__value"><?php echo htmlspecialchars($form_data['contactFormTitle']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($form_data['contactFormSubmitText'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <strong class="content-card__label">Submit Button:</strong>
                                            <div class="content-card__value"><?php echo htmlspecialchars($form_data['contactFormSubmitText']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($form_data['contactFormThankYou'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <strong class="content-card__label">Thank You Message:</strong>
                                            <div class="content-card__value" style="font-style: italic;">
                                                <?php echo nl2br(htmlspecialchars($form_data['contactFormThankYou'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($form_data['contactFormAdminEmail'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <strong class="content-card__label">Admin Email:</strong>
                                            <div class="content-card__value"><?php echo htmlspecialchars($form_data['contactFormAdminEmail']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Contact Form Fields -->
                                <div style="margin-bottom: 20px;">
                                    <h5 style="margin: 0 0 14px 0; color: #2c3e50; font-size: 18px;">📝 Form Fields</h5>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;">
                                        <?php
                                        $contactFields = [
                                            'name' => ['icon' => '👤', 'label' => 'Name'],
                                            'email' => ['icon' => '✉️', 'label' => 'Email'],
                                            'phone' => ['icon' => '📞', 'label' => 'Phone'],
                                            'message' => ['icon' => '📝', 'label' => 'Message']
                                        ];
                                        foreach ($contactFields as $fieldKey => $fieldInfo):
                                            $fieldName = "contactField_{$fieldKey}";
                                            $isEnabled = !empty($form_data[$fieldName]) && $form_data[$fieldName] === 'yes';
                                        ?>
                                            <div style="padding: 12px 14px; background: <?php echo $isEnabled ? '#d4edda' : '#f8d7da'; ?>; border-radius: 12px; text-align: center;">
                                                <span style="font-size: 16px;"><?php echo $fieldInfo['icon']; ?></span>
                                                <div style="font-size: 18px; color: <?php echo $isEnabled ? '#155724' : '#721c24'; ?>; font-weight: 600; margin-top: 4px;">
                                                    <?php echo $fieldInfo['label']; ?>: <?php echo $isEnabled ? '✓ Yes' : '✗ No'; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Page Attachments -->
                <?php if (!empty($form_data['pageAttachments']) && is_array($form_data['pageAttachments'])): ?>
                    <div class="field-group">
                        <div class="field-label">Page Attachments</div>
                        <div class="field-value">
                            <?php foreach ($form_data['pageAttachments'] as $pageName => $attachment): ?>
                                <div style="margin-bottom: 12px; padding: 12px 14px; background: #f8f9fa; border-radius: 10px;">
                                    <strong style="text-transform: capitalize; font-size: 18px;"><?php echo htmlspecialchars($pageName); ?>:</strong><br>
                                    <?php if (is_array($attachment) && (!empty($attachment['path']) || !empty($attachment['data']))): ?>
                                        <a href="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=page-attachment&page=<?php echo urlencode($pageName); ?>&download=1" 
                                           style="color: #28a745; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; background: #e8f5e8; border-radius: 8px; border: 1px solid #28a745; margin-top: 6px;" target="_blank">
                                            📎 <?php echo htmlspecialchars($attachment['fileName'] ?? 'Download File'); ?>
                                        </a><br>
                                    <?php else: ?>
                                        <span style="color: #28a745;">📎 <?php echo htmlspecialchars($attachment['fileName'] ?? $attachment['name'] ?? 'File uploaded'); ?></span><br>
                                    <?php endif; ?>
                                    <small style="color: #6c757d; font-size: 0.98rem;">Type: <?php echo htmlspecialchars($attachment['fileType'] ?? 'Unknown'); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                $testimonialItems = [];
                $galleryItems = [];

                if ($is_website) {
                    // Handle website testimonial fields only
                    foreach ($form_data as $key => $value) {
                        if (preg_match('/^testimonial_(\d+)_name$/', $key, $matches)) {
                            $index = $matches[1];
                            $testimonialItems[$index]['name'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^testimonial_(\d+)_date$/', $key, $matches)) {
                            $index = $matches[1];
                            $testimonialItems[$index]['date'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^testimonial_(\d+)_text$/', $key, $matches)) {
                            $index = $matches[1];
                            $testimonialItems[$index]['text'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^testimonial_(\d+)_image$/', $key, $matches)) {
                            $index = $matches[1];
                            $testimonialItems[$index]['image'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                            $testimonialItems[$index]['field_name'] = $matches[0]; // Store the full field name
                        }
                        // Handle website gallery fields only
                        if (preg_match('/^gallery_(\d+)_caption$/', $key, $matches)) {
                            $index = $matches[1];
                            $galleryItems[$index]['caption'] = $value;
                            $galleryItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^gallery_(\d+)_image$/', $key, $matches)) {
                            $index = $matches[1];
                            $galleryItems[$index]['file'] = $value;
                            $galleryItems[$index]['index'] = $index;
                            $galleryItems[$index]['field_name'] = $matches[0]; // Store the full field name
                        }
                    }
                } else {
                    // Handle mobile app testimonial and gallery fields
                    foreach ($form_data as $key => $value) {
                        if (preg_match('/^app_testimonial_(\d+)_name$/', $key, $matches)) {
                            $index = $matches[2];
                            $testimonialItems[$index]['name'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^app_testimonial_(\d+)_date$/', $key, $matches)) {
                            $index = $matches[2];
                            $testimonialItems[$index]['date'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^app_testimonial_(\d+)_text$/', $key, $matches)) {
                            $index = $matches[2];
                            $testimonialItems[$index]['text'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^app_testimonial_(\d+)_image$/', $key, $matches)) {
                            $index = $matches[2];
                            $testimonialItems[$index]['image'] = $value;
                            $testimonialItems[$index]['index'] = $index;
                            $testimonialItems[$index]['field_name'] = $matches[0]; // Store the full field name
                        }
                        if (preg_match('/^app_gallery_(\d+)_caption$/', $key, $matches)) {
                            $index = $matches[2];
                            $galleryItems[$index]['caption'] = $value;
                            $galleryItems[$index]['index'] = $index;
                        }
                        if (preg_match('/^app_gallery_(\d+)_image$/', $key, $matches)) {
                            $index = $matches[2];
                            $galleryItems[$index]['file'] = $value;
                            $galleryItems[$index]['index'] = $index;
                            $galleryItems[$index]['field_name'] = $matches[0]; // Store the full field name
                        }
                        if (preg_match('/^app_api_files\[(\d+)\]$/', $key, $matches)) {
                            $index = $matches[1];
                            $apiFiles[$index]['file'] = $value;
                            $apiFiles[$index]['index'] = $index;
                            $apiFiles[$index]['field_name'] = $matches[0]; // Store the full field name
                        }
                    }
                }

                // Normalize testimonial and gallery items to sequential array values
                $testimonialItems = array_values(array_filter($testimonialItems, function ($item) {
                    return !empty($item['name']) || !empty($item['image']) || !empty($item['text']);
                }));
                $galleryItems = array_values(array_filter($galleryItems, function ($item) {
                    return !empty($item['file']) || !empty($item['caption']);
                }));
                $apiFiles = array_values(array_filter($apiFiles ?? [], function ($item) {
                    return !empty($item['file']);
                }));
                ?>
                <?php if (!empty($form_data['pageExtras']) || !empty($testimonialItems) || !empty($galleryItems) || !empty($apiFiles) || !empty($form_data['galleryTitle'])): ?>
                    <div class="field-group">
                        <div class="field-label">Page Extras</div>
                        <div class="field-value">
                            <?php if (!empty($form_data['pageExtras'])): ?>
                                <strong>Selected Extras:</strong> <?php echo displayArray($form_data['pageExtras']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($testimonialItems)): ?>
                                <strong>Testimonials:</strong><br>
                                <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 15px;">
                                    <?php foreach ($testimonialItems as $item): ?>
                                        <div style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; background: #fafafa;">
                                            <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px;">
                                                <?php
                                                // Check for testimonial image - use the correct field name
                                                $fieldName = $item['field_name'] ?? 'testimonial_' . $item['index'] . '_image';
                                                $imageData = $form_data[$fieldName . '_data'] ?? '';
                                                $imagePath = $form_data[$fieldName . '_path'] ?? '';
                                                $imageName = $form_data[$fieldName] ?? '';
                                                if (!empty($imageData) || !empty($imagePath)):
                                                ?>
                                                    <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: #f0f0f0;">
                                                        <img src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $fieldName)); ?>"
                                                             style="width: 100%; height: 100%; object-fit: cover;"
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                             onerror="this.style.display='none'; this.parentElement.style.background='#e9ecef'; this.parentElement.innerHTML='🖼️';">
                                                    </div>
                                                <?php endif; ?>
                                                <div style="flex: 1;">
                                                    <div style="font-weight: 600; font-size: 16px; color: #2c3e50; margin-bottom: 5px;">
                                                        <?php echo htmlspecialchars($item['name'] ?: 'Anonymous'); ?>
                                                    </div>
                                                    <?php if (!empty($item['date'])): ?>
                                                        <div style="font-size: 13px; color: #6c757d;">
                                                            <?php echo htmlspecialchars($item['date']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if (!empty($item['text'])): ?>
                                                <div style="font-style: italic; color: #555; padding: 15px; background: white; border-radius: 8px; border-left: 3px solid #3498db; margin-bottom: 10px;">
                                                    "<?php echo nl2br(htmlspecialchars($item['text'])); ?>"
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($imageData) || !empty($imagePath)): ?>
                                                <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $fieldName, true)); ?>"
                                                   download="<?php echo htmlspecialchars($imageName ?: 'testimonial-' . $item['index'] . '.jpg'); ?>"
                                                   style="display: inline-block; padding: 8px 14px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                                   📥 Download Image
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($form_data['galleryTitle'])): ?>
                                <strong>Gallery:</strong> <?php echo displayValue($form_data['galleryTitle']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['galleryDescription'])): ?>
                                <strong>Gallery Description:</strong><?php echo nl2br(htmlspecialchars($form_data['galleryDescription'])); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($galleryItems)): ?>
                                <strong>Gallery Images:</strong><br>
                                <div class="field-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                                    <?php foreach ($galleryItems as $item): ?>
                                        <div class="field-group" style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 15px; background: #fafafa;">
                                            <?php
                                            // Check for gallery image data - use correct field name
                                            $fieldName = $item['field_name'] ?? 'gallery_' . $item['index'] . '_image';
                                            $imageData = $form_data[$fieldName . '_data'] ?? '';
                                            $imagePath = $form_data[$fieldName . '_path'] ?? '';
                                            $imageName = $item['file'] ?? '';
                                            ?>
                                            <div style="width: 100%; height: 200px; border-radius: 8px; overflow: hidden; margin-bottom: 12px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                                <?php if (!empty($imageData) || !empty($imagePath)): ?>
                                                    <img src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $fieldName)); ?>"
                                                         style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                                         alt="Gallery Image <?php echo $item['index']; ?>"
                                                         onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\\'color: #999; font-size: 18px;\\'>🖼️ Preview Error</span>';">
                                                <?php else: ?>
                                                    <span style="color: #999; font-size: 18px;">No Preview</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 13px; color: #6c757d; margin-bottom: 10px; text-align: center;">
                                                <strong><?php echo htmlspecialchars($item['caption']); ?></strong>
                                            </div>
                                            <?php if (!empty($imageData) || !empty($imagePath)): ?>
                                                <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $fieldName, true)); ?>"
                                                   download="<?php echo htmlspecialchars($imageName ?: 'gallery-image-' . $item['index'] . '.jpg'); ?>"
                                                   style="display: block; width: 100%; text-align: center; padding: 10px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 18px; font-weight: 600;">
                                                   📥 Download Image
                                                </a>
                                            <?php else: ?>
                                                <div style="text-align: center; padding: 10px 16px; background: #f8f9fa; color: #6c757d; border-radius: 8px; font-size: 18px;">
                                                    File: <?php echo htmlspecialchars($imageName ?: 'No file uploaded'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Content Collection Fields -->
                <?php if (!empty($form_data['sellType']) && in_array($form_data['sellType'], ['services', 'both'])): ?>
                    <div class="field-group">
                        <div class="field-label">Service Categories</div>
                        <div class="field-value"><?php echo displayValue($form_data['serviceCategories'] ?? ''); ?></div>
                    </div>

                    <div class="field-group">
                        <div class="field-label">Service Portfolio Link</div>
                        <div class="field-value"><?php echo displayValue($form_data['servicePortfolioLink'] ?? ''); ?></div>
                    </div>

                    <div class="field-group">
                        <div class="field-label">Service Pricing</div>
                        <div class="field-value"><?php echo displayValue($form_data['servicePricing'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Service Operations</div>
                        <div class="field-value">
                            <strong>Needs:</strong> <?php echo displayArray($form_data['serviceOperations'] ?? []); ?><br>
                            <strong>Notes:</strong> <?php echo displayValue($form_data['serviceOperationNotes'] ?? ''); ?>
                        </div>
                    </div>

                    <?php if (!empty($form_data['serviceDetailsFile'])): ?>
                        <div class="field-group">
                            <div class="field-label">Service Details File</div>
                            <div class="field-value">
                                <?php if (!empty($form_data['serviceDetailsFile_data'])): ?>
                                    <a href="../download_asset.php?client_id=<?php echo urlencode($client['client_id']); ?>&type=service-details&download=1" 
                                       style="color: #007bff; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; padding: 8px 15px; background: #e3f2fd; border-radius: 4px; border: 1px solid #007bff; margin-bottom: 10px;" target="_blank">
                                        📄 Download <?php echo htmlspecialchars($form_data['serviceDetailsFile'] ?? 'Service Details'); ?>
                                    </a><br>
                                <?php endif; ?>
                                <strong>File:</strong> <?php echo displayValue($form_data['serviceDetailsFile'] ?? ''); ?><br>
                                <strong>Type:</strong> <?php echo displayValue($form_data['serviceDetailsFile_type'] ?? ''); ?><br>
                                <em>File uploaded successfully</em>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($form_data['sellType']) && in_array($form_data['sellType'], ['products', 'both'])): ?>
                    <div class="field-group">
                        <div class="field-label">Product Categories</div>
                        <div class="field-value"><?php echo displayValue($form_data['productCategories'] ?? ''); ?></div>
                    </div>
                <?php endif; ?>

                <div class="field-group">
                    <div class="field-label">Additional Notes</div>
                    <div class="field-value"><?php echo displayValue($form_data['additionalNotes'] ?? ''); ?></div>
                </div>
            </div>
        </div>

        <!-- Section 4: Products & Features -->
        <div class="section">
            <div class="section-header">4. Products & Features</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">Business Type</div>
                    <div class="field-value"><?php echo displayValue($form_data['sellType'] ?? ''); ?></div>
                </div>

                <?php if (!empty($form_data['sellType']) && in_array($form_data['sellType'], ['products', 'both'])): ?>
                    <div class="field-group">
                        <div class="field-label">Product Information</div>
                        <div class="field-value">
                            <?php if (!empty($form_data['productCategories'])): ?>
                                <strong>Categories:</strong> <?php echo displayValue($form_data['productCategories']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['productDriveLink'])): ?>
                                <strong>Product Drive Link:</strong> <a href="<?php echo htmlspecialchars($form_data['productDriveLink']); ?>" target="_blank">View Products</a><br>
                            <?php endif; ?>
                            <?php if (!empty($form_data['filterAttributes'])): ?>
                                <strong>Filter Attributes:</strong> <?php echo displayArray($form_data['filterAttributes']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $productItems = [];
                    foreach ($form_data as $key => $value) {
                        if (preg_match('/^product_(\d+)_name$/', $key, $matches) && !empty($value)) {
                            $index = $matches[1];
                            $productItems[] = [
                                'name' => $value,
                                'price' => $form_data["product_{$index}_price"] ?? '',
                                'description' => $form_data["product_{$index}_description"] ?? '',
                                'image' => $form_data["product_{$index}_image"] ?? ''
                            ];
                        }
                    }
                    ?>
                    <?php if (!empty($productItems)): ?>
                        <div class="field-group">
                            <div class="field-label">Individual Products</div>
                            <div class="field-value">
                                <?php foreach ($productItems as $index => $item): ?>
                                    <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;">
                                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                                            <div style="flex: 1;">
                                                <div style="margin-bottom: 12px;">
                                                    <strong style="color: #333; font-size: 18px;">Product Name:</strong>
                                                    <div style="margin-top: 4px; padding: 8px; background-color: #f5f5f5; border-radius: 4px;">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($item['price'])): ?>
                                                    <div style="margin-bottom: 12px;">
                                                        <strong style="color: #333; font-size: 18px;">Price:</strong>
                                                        <div style="margin-top: 4px; padding: 8px; background-color: #f5f5f5; border-radius: 4px; font-size: 16px; font-weight: bold; color: #2c5aa0;">
                                                            ₹<?php echo number_format((float)str_replace(',', '', $item['price']), 2); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($item['description'])): ?>
                                                    <div style="margin-bottom: 12px;">
                                                        <strong style="color: #333; font-size: 18px;">Description:</strong>
                                                        <div style="margin-top: 4px; padding: 8px; background-color: #f5f5f5; border-radius: 4px; color: #666; line-height: 1.4;">
                                                            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($item['image'])): ?>
                                                    <div style="margin-bottom: 12px;">
                                                        <strong style="color: #333; font-size: 18px;">Image:</strong>
                                                        <div style="margin-top: 8px;">
                                                            <?php 
                                                            $imagePath = '';
                                                            if (strpos($item['image'], 'uploads/') === 0) {
                                                                $imagePath = '../' . $item['image'];
                                                            } elseif (strpos($item['image'], 'http') === 0) {
                                                                $imagePath = $item['image'];
                                                            } else {
                                                                $imagePath = '../uploads/clients/' . $client['client_id'] . '/' . $item['image'];
                                                            }
                                                            ?>
                                                            
                                                            <a href="<?php echo $imagePath; ?>" download="<?php echo htmlspecialchars(basename($item['image'])); ?>" 
                                                               style="padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 18px; display: inline-block;">
                                                                📥 Download Image
                                                            </a>
                                                        </div>
                                                        <div style="margin-top: 4px; font-size: 12px; color: #888;">
                                                            📁 <?php echo htmlspecialchars(basename($item['image'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                
                <div class="field-group">
                    <div class="field-label">Custom Features</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['customFeatures'])): ?>
                            <div class="checkbox-list">
                                <?php 
                                $features = is_array($form_data['customFeatures']) ? $form_data['customFeatures'] : [$form_data['customFeatures']];
                                foreach ($features as $feature): ?>
                                    <span class="checkbox-item"><?php echo htmlspecialchars(ucfirst($feature)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php echo displayValue(''); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($form_data['otherFeatures'])): ?>
                    <div class="field-group">
                        <div class="field-label">Other Features</div>
                        <div class="field-value"><?php echo displayValue($form_data['otherFeatures']); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Ecommerce Setup Subsection -->
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e67e22; padding-bottom: 8px; margin-bottom: 18px;">🛒 ECOMMERCE SETUP</h4>
                    
                    <div class="field-group">
                        <div class="field-label">E-commerce Website</div>
                        <div class="field-value"><?php echo displayValue($form_data['isEcommerce'] ?? ''); ?></div>
                    </div>

                    <?php if (!empty($form_data['isEcommerce']) && $form_data['isEcommerce'] === 'yes'): ?>
                        <div class="field-group">
                            <div class="field-label">Payment Methods</div>
                            <div class="field-value">
                                <?php 
                                $payment_methods = $form_data['paymentMethods'] ?? [];
                                if (!is_array($payment_methods)) $payment_methods = [$payment_methods];
                                
                                if (!empty($payment_methods)) {
                                    echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                                    foreach ($payment_methods as $method) {
                                        $method_name = ucfirst(str_replace('-', ' ', $method));
                                        echo '<span class="content-chip" style="background: #e8f5e9; color: #2e7d32;">' . htmlspecialchars($method_name) . '</span>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo displayValue('');
                                }
                                ?>
                            </div>
                        </div>

                        <?php if (!empty($form_data['razorpayKeyId'])): ?>
                        <div class="field-group">
                            <div class="field-label">Razorpay Key ID</div>
                            <div class="field-value"><?php echo htmlspecialchars($form_data['razorpayKeyId']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($form_data['stripeKey'])): ?>
                        <div class="field-group">
                            <div class="field-label">Stripe Publishable Key</div>
                            <div class="field-value"><?php echo htmlspecialchars($form_data['stripeKey']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($form_data['paypalEmail'])): ?>
                        <div class="field-group">
                            <div class="field-label">PayPal Email</div>
                            <div class="field-value"><?php echo htmlspecialchars($form_data['paypalEmail']); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($form_data['otherPaymentMethod'])): ?>
                        <div class="field-group">
                            <div class="field-label">Other Payment Method</div>
                            <div class="field-value"><?php echo displayValue($form_data['otherPaymentMethod']); ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="field-group">
                            <div class="field-label">Currencies Accepted</div>
                            <div class="field-value">
                                <?php 
                                $currencies = $form_data['currencies'] ?? [];
                                if (!is_array($currencies)) $currencies = [$currencies];
                                
                                if (!empty($currencies)) {
                                    echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                                    foreach ($currencies as $currency) {
                                        echo '<span class="content-chip" style="background: #fff3e0; color: #e65100;">' . htmlspecialchars($currency) . '</span>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo displayValue('');
                                }
                                ?>
                            </div>
                        </div>

                        <?php if (!empty($form_data['otherCurrencyText'])): ?>
                        <div class="field-group">
                            <div class="field-label">Other Currency</div>
                            <div class="field-value"><?php echo displayValue($form_data['otherCurrencyText']); ?></div>
                        </div>
                        <?php endif; ?>

                        <div class="field-group">
                            <div class="field-label">Tax Information</div>
                            <div class="field-value">
                                <?php if (!empty($form_data['taxRegion'])): ?>
                                    <strong>Tax Region:</strong> <?php echo displayValue($form_data['taxRegion']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['taxRate'])): ?>
                                    <strong>Tax Rate:</strong> <?php echo displayValue($form_data['taxRate']); ?>%
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="field-group">
                            <div class="field-label">Shipping Information</div>
                            <div class="field-value">
                                <?php if (!empty($form_data['shippingType'])): ?>
                                    <strong>Shipping Type:</strong> <?php echo displayValue(ucfirst(str_replace('-', ' ', $form_data['shippingType']))); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['flatRate'])): ?>
                                    <strong>Flat Rate:</strong> <?php echo displayValue($form_data['flatRate']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['shippingZones'])): ?>
                                    <strong>Shipping Zones:</strong> <?php echo nl2br(htmlspecialchars($form_data['shippingZones'])); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['shippingRates'])): ?>
                                    <strong>Shipping Rates:</strong> <?php echo nl2br(htmlspecialchars($form_data['shippingRates'])); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($form_data['deliveryTimeframes'])): ?>
                                    <strong>Delivery Timeframes:</strong> <?php echo nl2br(htmlspecialchars($form_data['deliveryTimeframes'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="field-group">
                            <div class="field-label">Inventory Type</div>
                            <div class="field-value"><?php echo displayValue(ucfirst($form_data['inventoryType'] ?? '')); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">Store Operations</div>
                            <div class="field-value">
                                <strong>Needs:</strong> <?php echo displayArray($form_data['ecommerceOperations'] ?? []); ?><br>
                                <strong>Notes:</strong> <?php echo displayValue($form_data['ecommerceOperationNotes'] ?? ''); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section 5: Marketing & SEO -->
        <div class="section">
            <div class="section-header">5. Marketing & SEO</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">SEO Information</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['businessDescription'])): ?>
                            <strong>Business Description:</strong> <?php echo displayValue($form_data['businessDescription']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['keywords'])): ?>
                            <strong>Keywords:</strong> <?php echo displayValue($form_data['keywords']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['competitors'])): ?>
                            <strong>Competitors:</strong> <?php echo displayValue($form_data['competitors']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['avoidWebsites'])): ?>
                            <strong>Avoid:</strong> <?php echo displayValue($form_data['avoidWebsites']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['targetLocations'])): ?>
                            <strong>Target Locations:</strong> <?php echo displayValue($form_data['targetLocations']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['googleBusinessProfile'])): ?>
                            <strong>Google Business Profile:</strong> <?php echo displayValue($form_data['googleBusinessProfile']); ?><br>
                        <?php endif; ?>
                        <strong>Trust Assets:</strong> <?php echo displayArray($form_data['trustAssets'] ?? []); ?><br>
                        <strong>Legal Needs:</strong> <?php echo displayArray($form_data['legalNeeds'] ?? []); ?><br>
                        <strong>Legal / Trust Notes:</strong> <?php echo displayValue($form_data['legalTrustNotes'] ?? ''); ?>
                    </div>
                </div>

                
                <div class="field-group">
                    <div class="field-label">Google Analytics</div>
                    <div class="field-value"><?php echo displayValue($form_data['hasAnalytics'] ?? ''); ?></div>
                </div>

                <!-- Social Media Subsection -->
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e67e22; padding-bottom: 8px; margin-bottom: 18px;">📱 SOCIAL MEDIA</h4>
                    <div class="field-group">
                        <div class="field-label">Show Social Icons</div>
                        <div class="field-value"><?php echo displayValue($form_data['showSocialIcons'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Social Media Links</div>
                        <div class="field-value">
                            <?php 
                            $social_platforms = ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'pinterest', 'tiktok'];
                            $has_social = false;
                            foreach ($social_platforms as $platform) {
                                $field_name = $platform . 'Url';
                                if (!empty($form_data[$field_name])) {
                                    echo '<div style="margin-bottom: 10px; font-size: 18px;"><strong>' . ucfirst($platform) . ':</strong> <a href="' . htmlspecialchars($form_data[$field_name]) . '" target="_blank" style="color: #3498db; text-decoration: none;">🔗 ' . htmlspecialchars($form_data[$field_name]) . '</a></div>';
                                    $has_social = true;
                                }
                            }
                            if (!$has_social) {
                                echo '<span style="color: #7f8c8d; font-style: italic;">No social media links provided</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($form_data['otherSocialMedia'])): ?>
                        <div class="field-group">
                            <div class="field-label">Other Social Media</div>
                            <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['otherSocialMedia'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section 6: Final Details -->
        <div class="section">
            <div class="section-header">6. Final Details</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">Contact Information</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['contactName'])): ?>
                            <strong>Contact Name:</strong> <?php echo displayValue($form_data['contactName']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['contactEmail'])): ?>
                            <strong>Contact Email:</strong> <?php echo displayValue($form_data['contactEmail']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['contactPhone'])): ?>
                            <strong>Contact Phone:</strong> <?php echo displayValue($form_data['contactPhone']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Working Hours</div>
                    <div class="field-value">
                        <strong>Mode:</strong> <?php 
                        $working_mode = $form_data['workingHoursMode'] ?? '';
                        $working_mode_labels = [
                            'always-open' => 'Always open',
                            'selected-hours' => 'Open for selected hours'
                        ];
                        echo displayValue($working_mode_labels[$working_mode] ?? $working_mode); 
                        ?><br>
                        <?php if (!empty($form_data['openDays'])): ?>
                            <strong>Open Days:</strong> <?php echo displayArray($form_data['openDays']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['openingTime']) || !empty($form_data['closingTime'])): ?>
                            <strong>Hours:</strong> <?php echo displayValue(($form_data['openingTime'] ?? '') . ' - ' . ($form_data['closingTime'] ?? '')); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">Approval & Communication</div>
                    <div class="field-value">
                        <strong>Decision Maker:</strong> <?php echo displayValue($form_data['decisionMaker'] ?? ''); ?><br>
                        <strong>Reviewers:</strong> <?php echo displayValue($form_data['reviewerCount'] ?? ''); ?><br>
                        <strong>Preferred Communication:</strong> <?php 
                        $comm_method = $form_data['preferredCommunication'] ?? '';
                        $comm_method_labels = [
                            'whatsapp' => 'WhatsApp',
                            'email' => 'Email',
                            'call' => 'Phone call',
                            'video-call' => 'Video call',
                            'project-manager' => 'Through project manager'
                        ];
                        echo displayValue($comm_method_labels[$comm_method] ?? $comm_method); 
                        ?><br>
                        <strong>Best Contact Time:</strong> <?php echo displayValue($form_data['bestContactTime'] ?? ''); ?><br>
                        <strong>Deadline Reason:</strong> <?php echo displayValue($form_data['deadlineReason'] ?? ''); ?><br>
                        <strong>Uncertainties:</strong> <?php echo displayValue($form_data['clientUncertainties'] ?? ''); ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Custom Features & Forms</div>
                    <div class="field-value">
                        <?php
                        $custom_features = $form_data['customFeatures'] ?? [];
                        if (!is_array($custom_features)) $custom_features = [$custom_features];
                        
                        if (!empty($custom_features)) {
                            echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                            foreach ($custom_features as $feature) {
                                echo '<span class="content-chip" style="background: #e3f2fd; color: #1976d2;">' . htmlspecialchars(ucfirst($feature)) . '</span>';
                            }
                            echo '</div>';
                        } else {
                            echo displayValue('');
                        }
                        ?>
                    </div>
                </div>

                <?php if (!empty($form_data['otherFeatures'])): ?>
                <div class="field-group">
                    <div class="field-label">Other Custom Features</div>
                    <div class="field-value"><?php echo displayValue($form_data['otherFeatures']); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($form_data['integrations'])): ?>
                <div class="field-group">
                    <div class="field-label">Third-party Integrations</div>
                    <div class="field-value"><?php echo displayValue($form_data['integrations']); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($form_data['formRequirements'])): ?>
                <div class="field-group">
                    <div class="field-label">Form Requirements</div>
                    <div class="field-value"><?php echo displayValue($form_data['formRequirements']); ?></div>
                </div>
                <?php endif; ?>
                <div class="field-group">
                    <div class="field-label">Lead Routing</div>
                    <div class="field-value">
                        <strong>Destinations:</strong> <?php echo displayArray($form_data['leadDestinations'] ?? []); ?><br>
                        <strong>Notification Email:</strong> <?php echo displayValue($form_data['leadNotificationEmail'] ?? ''); ?><br>
                        <strong>Auto Reply:</strong> <?php echo displayValue($form_data['leadAutoReply'] ?? ''); ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Website Structure</div>
                    <div class="page-structure">
                        <?php
                        $header_pages = $form_data['headerPages'] ?? [];
                        $footer_pages = $form_data['footerPages'] ?? [];
                        
                        if (!is_array($header_pages)) $header_pages = [$header_pages];
                        if (!is_array($footer_pages)) $footer_pages = [$footer_pages];
                        
                        $all_pages = [];
                        foreach ($header_pages as $page) {
                            $all_pages[] = ['name' => ucfirst($page), 'type' => 'Header'];
                        }
                        foreach ($footer_pages as $page) {
                            $all_pages[] = ['name' => ucfirst($page), 'type' => 'Footer'];
                        }
                        
                        if (!empty($all_pages)) {
                            foreach ($all_pages as $page) {
                                echo '<div class="page-item">';
                                echo '<span class="page-name">' . htmlspecialchars($page['name']) . '</span>';
                                echo '<span class="page-type">' . htmlspecialchars($page['type']) . '</span>';
                                echo '</div>';
                            }
                        } else {
                            echo displayValue('');
                        }
                        ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Additional Notes</div>
                    <div class="field-value"><?php echo displayValue($form_data['additionalNotes'] ?? ''); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($is_mobile_app): ?>
        <!-- MOBILE APP SECTIONS -->

        <!-- Section: App Basic Info -->
        <div class="section">
            <div class="section-header">📱 App Basic Info</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">App Name</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_name'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">App Tagline</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_tagline'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">App Purpose</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_purpose'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Target Audience</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_target_audience'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Splash Screen Required</div>
                        <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_splash_screen_required'], [
                            'yes' => 'Yes',
                            'no' => 'No'
                        ])); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($form_data['app_features'])): ?>
                <div class="field-group">
                    <div class="field-label">App Features</div>
                    <div class="field-value">
                        <?php 
                        echo displayArray(formatAppFeatureValues($form_data['app_features']));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_target_platforms'])): ?>
                <div class="field-group">
                    <div class="field-label">Target Platforms</div>
                    <div class="field-value">
                        <?php 
                        $platformLabels = [
                            'ios' => 'iOS',
                            'android' => 'Android',
                            'both' => 'Both iOS & Android',
                            'web' => 'Web App'
                        ];
                        echo displayArray(formatMappedValues($form_data['app_target_platforms'], $platformLabels));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_development_type'])): ?>
                <div class="field-group">
                    <div class="field-label">Development Type</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_development_type'], [
                        'native' => 'Native Development',
                        'react-native' => 'React Native',
                        'flutter' => 'Flutter',
                        'ionic' => 'Ionic',
                        'pwa' => 'Progressive Web App',
                        'not-sure' => 'Not Sure - Need Recommendation'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_need_design'])): ?>
                <div class="field-group">
                    <div class="field-label">Design Required</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_need_design'], [
                        'yes' => 'Yes',
                        'no' => 'No'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_design_available'])): ?>
                <div class="field-group">
                    <div class="field-label">Design Available</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_design_available'], [
                        'yes' => 'Yes - We have designs',
                        'no' => 'No - Need design',
                        'partial' => 'Partial - Some designs ready'
                    ])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: Technical Requirements -->
        <div class="section">
            <div class="section-header">🔧 Technical Requirements</div>
            <div class="section-content">
                <?php if (!empty($form_data['app_backend_required'])): ?>
                <div class="field-group">
                    <div class="field-label">Backend Required</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_backend_required'], [
                        'yes' => 'Yes',
                        'no' => 'No'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_api_available'])): ?>
                <div class="field-group">
                    <div class="field-label">API Available</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_api_available'], [
                        'yes' => 'Yes - We have API',
                        'no' => 'No - Need API development'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_api_source'])): ?>
                <div class="field-group">
                    <div class="field-label">API Source / Details</div>
                    <div class="field-value"><?php echo displayValue($form_data['app_api_source']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_api_documentation'])): ?>
                <div class="field-group">
                    <div class="field-label">API Documentation Link</div>
                    <div class="field-value"><?php echo displayValue($form_data['app_api_documentation']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_api_auth_type'])): ?>
                <div class="field-group">
                    <div class="field-label">Authentication Type</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_api_auth_type'], [
                        'none' => 'None',
                        'api-key' => 'API Key',
                        'bearer-token' => 'Bearer Token',
                        'oauth2' => 'OAuth 2.0',
                        'basic-auth' => 'Basic Auth',
                        'custom' => 'Custom'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_api_credentials'])): ?>
                <div class="field-group">
                    <div class="field-label">API Credentials</div>
                    <div class="field-value"><?php echo displayValue($form_data['app_api_credentials']); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($apiFiles)): ?>
                <div class="field-group">
                    <div class="field-label">API Documentation Files</div>
                    <div class="field-value">
                        <div class="field-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                            <?php foreach ($apiFiles as $item): ?>
                                <div class="field-group" style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 15px; background: #fafafa;">
                                    <?php
                                    $fieldName = $item['field_name'] ?? 'app_api_files[' . $item['index'] . ']';
                                    $filePath = $form_data[$fieldName . '_path'] ?? '';
                                    $fileName = $item['file'] ?? '';
                                    ?>
                                    <div style="width: 100%; height: 120px; border-radius: 8px; overflow: hidden; margin-bottom: 12px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <span style="color: #999; font-size: 24px;">📄</span>
                                    </div>
                                    <div style="font-size: 13px; color: #6c757d; margin-bottom: 10px; text-align: center; word-break: break-all;">
                                        <strong><?php echo htmlspecialchars($fileName); ?></strong>
                                    </div>
                                    <?php if (!empty($filePath)): ?>
                                        <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $fieldName, true)); ?>"
                                           download="<?php echo htmlspecialchars($fileName ?: 'api-file-' . $item['index']); ?>"
                                           style="display: block; width: 100%; text-align: center; padding: 10px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600;">
                                           📥 Download File
                                        </a>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 10px 16px; background: #f8f9fa; color: #6c757d; border-radius: 8px; font-size: 14px;">
                                            File: <?php echo htmlspecialchars($fileName ?: 'No file uploaded'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_backend_tech'])): ?>

                <div class="field-group">
                    <div class="field-label">Backend Technology</div>
                    <div class="field-value"><?php 
                    $techValue = $form_data['app_backend_tech'] ?? '';
                    if ($techValue === 'other' && !empty($form_data['app_backend_tech_other'])) {
                        echo displayValue($form_data['app_backend_tech_other']);
                    } else {
                        echo displayValue(formatMappedValue($techValue, [
                            'firebase' => 'Firebase',
                            'aws' => 'AWS',
                            'nodejs' => 'Node.js',
                            'python' => 'Python',
                            'php' => 'PHP',
                            'java' => 'Java',
                            'not-sure' => 'Not Sure',
                            'other' => 'Other'
                        ]));
                    }
                    ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_deployment_stores'])): ?>
                <div class="field-group">
                    <div class="field-label">Deployment Stores</div>
                    <div class="field-value">
                        <?php 
                        $storeLabels = [
                            'app-store' => 'Apple App Store',
                            'play-store' => 'Google Play Store',
                            'both' => 'Both App Stores',
                            'direct' => 'Direct Download',
                            'help-needed' => 'Need Help with Setup'
                        ];
                        echo displayArray(formatMappedValues($form_data['app_deployment_stores'], $storeLabels));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_playstore_login']) || !empty($form_data['app_playstore_password']) || !empty($form_data['app_appstore_login']) || !empty($form_data['app_appstore_password'])): ?>
                <div class="field-group">
                    <div class="field-label">Deployment Login Credentials</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['app_playstore_login'])): ?>
                            <strong>Google Play Store Login:</strong> <?php echo displayValue($form_data['app_playstore_login']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_playstore_password'])): ?>
                            <strong>Google Play Store Password:</strong> <?php echo displayValue($form_data['app_playstore_password']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_appstore_login'])): ?>
                            <strong>Apple App Store Login:</strong> <?php echo displayValue($form_data['app_appstore_login']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_appstore_password'])): ?>
                            <strong>Apple App Store Password:</strong> <?php echo displayValue($form_data['app_appstore_password']); ?><br>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>

        <!-- Section: Monetization & Payments -->
        <div class="section">
            <div class="section-header">💰 Monetization & Payments</div>
            <div class="section-content">
                <?php if (!empty($form_data['app_payment_required'])): ?>
                <div class="field-group">
                    <div class="field-label">Payment Required</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_payment_required'], [
                        'yes' => 'Yes',
                        'no' => 'No'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_payment_methods'])): ?>
                <div class="field-group">
                    <div class="field-label">Payment Methods</div>
                    <div class="field-value">
                        <?php 
                        $paymentLabels = [
                            'paypal' => 'PayPal',
                            'stripe' => 'Stripe',
                            'cod' => 'Cash on Delivery',
                            'razorpay' => 'RazorPay',
                            'paytm' => 'PayTM',
                            'phonepe' => 'PhonePe',
                            'google-pay' => 'Google Pay',
                            'apple-pay' => 'Apple Pay',
                            'credit-card' => 'Credit/Debit Cards',
                            'debit-card' => 'Debit Card',
                            'net-banking' => 'Net Banking',
                            'upi' => 'UPI'
                        ];
                        echo displayArray(formatMappedValues($form_data['app_payment_methods'], $paymentLabels));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>

        <!-- Section: Design & Branding -->
        <div class="section">
            <div class="section-header">🎨 Design & Branding</div>
            <div class="section-content">
                <?php if (!empty($form_data['app_hasIcon'])): ?>
                <div class="field-group">
                    <div class="field-label">Icon Type</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_hasIcon'], [
                        'yes' => 'Yes - I have app icon',
                        'temporary' => 'No - Create a temporary icon',
                        'professional' => 'I want a professional paid icon'
                    ])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_professionalIconText'])): ?>
                <div class="field-group">
                    <div class="field-label">Professional Icon Text</div>
                    <div class="field-value"><?php echo displayValue($form_data['app_professionalIconText']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_professionalIconDescription'])): ?>
                <div class="field-group">
                    <div class="field-label">Icon Description</div>
                    <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_professionalIconDescription'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_brandGuidelines'])): ?>
                <div class="field-group">
                    <div class="field-label">Brand Guidelines</div>
                    <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_brandGuidelines'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($form_data['app_brandVoice'])): ?>
                <div class="field-group">
                    <div class="field-label">Brand Voice</div>
                    <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_brandVoice'], [
                        'professional' => 'Professional',
                        'friendly' => 'Friendly & Casual',
                        'formal' => 'Formal',
                        'playful' => 'Playful',
                        'luxury' => 'Luxury & Premium'
                    ])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: App Content & Branding -->
        <div class="section">
            <div class="section-header">📱 App Content & Branding</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">Selected Screens</div>
                        <div class="field-value">
                            <?php 
                            $selected_screen_labels = [];
                            $selected_screen_keys = [];

                            foreach (normalizeArrayValues($form_data['appScreens'] ?? []) as $screen_value) {
                                $normalized_screen = strtolower(trim((string) $screen_value));
                                if ($normalized_screen === '') {
                                    continue;
                                }
                                $selected_screen_keys[$normalized_screen] = true;
                                $label = formatScreenLabel($normalized_screen);
                                if ($label !== '') {
                                    $selected_screen_labels[] = $label;
                                }
                            }

                            foreach ($form_data as $key => $value) {
                                if (!empty($value) && preg_match('/^(home|settings|profile|login|register|dashboard|search|cart|checkout|payment|notifications|help|support|messages|map|location|orders|favorites|wishlist|custom_.+)_(title|content|features|media|media_type|media_data|media_path)$/', $key, $matches)) {
                                    $screen_name = strtolower($matches[1]);
                                    if (!isset($selected_screen_keys[$screen_name])) {
                                        $selected_screen_keys[$screen_name] = true;
                                        $label = formatScreenLabel($screen_name);
                                        if ($label !== '') {
                                            $selected_screen_labels[] = $label;
                                        }
                                    }
                                }
                            }

                            if (!empty($selected_screen_labels)) {
                                echo displayArray(array_values(array_unique($selected_screen_labels)));
                            } else {
                                echo displayValue('');
                            }
                            ?>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">User Flow / Navigation</div>
                        <div class="field-value">
                            <?php
                            if (!empty($form_data['app_user_flow'])) {
                                echo nl2br(htmlspecialchars($form_data['app_user_flow']));
                            } else {
                                echo displayValue('');
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">App Name</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_name'] ?? ''); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">App Tagline</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_tagline'] ?? ''); ?></div>
                    </div>
                </div>

                <?php if (!empty($form_data['addAppCompanyDetails'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 8px; margin-bottom: 18px;">🏢 Company Details</h4>
                    <div class="field-grid">
                        <div class="field-group">
                            <div class="field-label">Company Code</div>
                            <div class="field-value"><?php echo displayValue($form_data['app_companyCode'] ?? ''); ?></div>
                        </div>
                        <div class="field-group">
                            <div class="field-label">GST/VAT Number</div>
                            <div class="field-value"><?php echo displayValue($form_data['app_gstNumber'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Company Address</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_companyAddress'] ?? ''); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 8px; margin-bottom: 18px;">🎨 App Icon</h4>
                    <div class="field-group">
                        <div class="field-label">Has App Icon</div>
                        <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_hasIcon'] ?? '', [
                            'yes' => 'Yes - I have app icon',
                            'temporary' => 'No - Create a temporary icon',
                            'professional' => 'I want a professional paid icon'
                        ])); ?></div>
                    </div>
                    <?php if (!empty($form_data['app_iconLink'])): ?>
                    <div class="field-group">
                        <div class="field-label">Icon Link</div>
                        <div class="field-value"><a href="<?php echo htmlspecialchars($form_data['app_iconLink']); ?>" target="_blank"><?php echo htmlspecialchars($form_data['app_iconLink']); ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if (fieldAssetExists($form_data, 'app_iconFile')): ?>
                    <div class="field-group">
                        <div class="field-label">Icon File</div>
                        <div class="field-value">
                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 18px; color: #6c757d;">
                                    File: <?php echo htmlspecialchars($form_data['app_iconFile'] ?? 'app-icon.png'); ?>
                                </span>
                                <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_iconFile', true)); ?>" 
                                   download="<?php echo htmlspecialchars($form_data['app_iconFile'] ?? 'app-icon.png'); ?>"
                                   style="display: inline-block; padding: 10px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 600;">
                                   📥 Download Icon
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (fieldAssetExists($form_data, 'app_businessAssetsFile')): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e67e22; padding-bottom: 8px; margin-bottom: 18px;">📁 Business Assets Files</h4>
                    <div class="field-group">
                        <div class="field-label">Uploaded Business Files</div>
                        <div class="field-value">
                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 18px; color: #6c757d;">
                                    File: <?php echo htmlspecialchars($form_data['app_businessAssetsFile'] ?? 'business-assets.pdf'); ?>
                                </span>
                                <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_businessAssetsFile', true)); ?>" 
                                   download="<?php echo htmlspecialchars($form_data['app_businessAssetsFile'] ?? 'business-assets.pdf'); ?>"
                                   style="display: inline-block; padding: 10px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 1rem; font-weight: 600;">
                                   📥 Download Business Assets
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (fieldAssetExists($form_data, 'app_professionalIconReference')): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 8px; margin-bottom: 18px;">🎨 Professional Icon Reference</h4>
                    <div class="field-group">
                        <div class="field-label">Reference Icon/Image</div>
                        <div class="field-value">
                            <div style="margin-top: 10px;">
                                <div style="width: 150px; height: 150px; border-radius: 8px; overflow: hidden; margin-bottom: 10px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_professionalIconReference')); ?>" 
                                         style="max-width: 100%; max-height: 100%; object-fit: contain;" 
                                         alt="Professional Icon Reference">
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 18px; color: #6c757d;">
                                        File: <?php echo htmlspecialchars($form_data['app_professionalIconReference'] ?? 'icon-reference.jpg'); ?>
                                    </span>
                                    <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_professionalIconReference', true)); ?>" 
                                       download="<?php echo htmlspecialchars($form_data['app_professionalIconReference'] ?? 'icon-reference.jpg'); ?>"
                                       style="display: inline-block; padding: 8px 14px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                       📥 Download Reference
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #f39c12; padding-bottom: 8px; margin-bottom: 18px;">📝 Content Collection</h4>
                    <div class="field-group">
                        <div class="field-label">Content Ready</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_contentReady'] ?? ''); ?></div>
                    </div>
                    <?php if (!empty($form_data['app_contentSupport'])): ?>
                    <div class="field-group">
                        <div class="field-label">Content Support</div>
                        <div class="field-value">
                            <?php 
                            $contentSupport = is_array($form_data['app_contentSupport']) ? $form_data['app_contentSupport'] : [$form_data['app_contentSupport']];
                            $contentSupportLabels = [
                                'proofread' => 'Proofreading',
                                'write' => 'Content Writing',
                                'optimize' => 'SEO Optimization',
                                'translate' => 'Translation',
                                'format' => 'Content Formatting'
                            ];
                            $displayContentSupport = [];
                            foreach ($contentSupport as $support) {
                                $displayContentSupport[] = $contentSupportLabels[$support] ?? ucwords(str_replace('-', ' ', $support));
                            }
                            echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayContentSupport)) . '</span>';
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="field-group">
                        <div class="field-label">Media Support</div>
                        <div class="field-value">
                            <?php 
                            $mediaSupport = is_array($form_data['app_mediaSupport']) ? $form_data['app_mediaSupport'] : [$form_data['app_mediaSupport']];
                            $mediaSupportLabels = [
                                'video-embed' => 'Video Embedding',
                                'image-gallery' => 'Image Gallery',
                                'audio-player' => 'Audio Player',
                                'file-upload' => 'File Upload',
                                'live-streaming' => 'Live Streaming'
                            ];
                            $displayMediaSupport = [];
                            foreach ($mediaSupport as $support) {
                                $displayMediaSupport[] = $mediaSupportLabels[$support] ?? ucwords(str_replace('-', ' ', $support));
                            }
                            echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayMediaSupport)) . '</span>';
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($form_data['app_pageExtras'])): ?>
                    <div class="field-group">
                        <div class="field-label">Page Extras</div>
                        <div class="field-value">
                            <?php 
                            $pageExtras = is_array($form_data['app_pageExtras']) ? $form_data['app_pageExtras'] : [$form_data['app_pageExtras']];
                            $pageExtrasLabels = [
                                'gallery' => 'Image Gallery',
                                'testimonials' => 'Testimonials Section',
                                'blog' => 'Blog/News',
                                'portfolio' => 'Portfolio',
                                'team' => 'Team Section',
                                'faq' => 'FAQ Section',
                                'contact-form' => 'Contact Form'
                            ];
                            $displayPageExtras = [];
                            foreach ($pageExtras as $extra) {
                                $displayPageExtras[] = $pageExtrasLabels[$extra] ?? ucwords(str_replace('-', ' ', $extra));
                            }
                            echo '<span style="font-size: 18px;">' . htmlspecialchars(implode(', ', $displayPageExtras)) . '</span>';
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_screenContent'])): ?>
                    <div class="field-group">
                        <div class="field-label">General Screen Content</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_screenContent'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Display individual screen content - show ALL screens with content, not just selected ones
                    $screen_contents = [];
                    foreach ($form_data as $key => $value) {
                        if (preg_match('/^(home|settings|profile|login|register|dashboard|search|cart|checkout|payment|notifications|help|support|messages|map|location|orders|favorites|wishlist|custom_.+)_(title|content|features|media|media_type|media_data|media_path)$/', $key, $matches) && !empty($value)) {
                            $screen_name = $matches[1];
                            $field_type = $matches[2];
                            if (!isset($screen_contents[$screen_name])) {
                                $screen_contents[$screen_name] = [];
                            }
                            $screen_contents[$screen_name][$field_type] = $value;
                        }
                    }
                    
                    if (!empty($screen_contents)):
                    ?>
                    <div class="field-group">
                        <div class="field-label">Individual Screen Content</div>
                        <div class="field-value">
                            <?php foreach ($screen_contents as $screen_name => $content): ?>
                                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #3498db;">
                                    <h5 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 16px; text-transform: capitalize;">
                                        📱 <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $screen_name))); ?> Screen
                                    </h5>
                                    
                                    <?php if (!empty($content['title'])): ?>
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color: #555;">Title:</strong> 
                                        <span style="color: #2c3e50; font-weight: 600;"><?php echo htmlspecialchars($content['title']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($content['content'])): ?>
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color: #555;">Content:</strong><br>
                                        <span style="color: #2c3e50;"><?php echo nl2br(htmlspecialchars($content['content'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($content['features'])): ?>
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color: #555;">Features:</strong><br>
                                        <span style="color: #2c3e50;"><?php echo nl2br(htmlspecialchars($content['features'])); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($content['media_data']) || !empty($content['media_path'])): ?>
                                    <div style="margin-top: 12px;">
                                        <strong style="color: #555;">Media:</strong><br>
                                        <?php $mediaType = $content['media_type'] ?? 'application/octet-stream'; ?>
                                        <?php if (strpos($mediaType, 'image/') === 0): ?>
                                            <div style="margin: 10px 0; width: 100%; max-width: 320px; border-radius: 10px; overflow: hidden; background: #fff; border: 1px solid #e0e0e0;">
                                                <img src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $screen_name . '_media')); ?>" 
                                                     alt="<?php echo htmlspecialchars($content['media'] ?? $screen_name . '-media'); ?>"
                                                     style="display: block; width: 100%; height: auto;">
                                            </div>
                                        <?php elseif (strpos($mediaType, 'video/') === 0): ?>
                                            <div style="margin: 10px 0; max-width: 420px;">
                                                <video controls style="width: 100%; border-radius: 10px; background: #000;">
                                                    <source src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $screen_name . '_media')); ?>">
                                                </video>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin: 10px 0; padding: 12px; background: #fff; border-radius: 8px; border: 1px solid #e0e0e0;">
                                                <span style="font-size: 18px; color: #6c757d;">
                                                    File: <?php echo htmlspecialchars($content['media'] ?? 'media-file'); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], $screen_name . '_media', true)); ?>" 
                                           download="<?php echo htmlspecialchars($content['media'] ?? $screen_name . '-media'); ?>"
                                           style="display: inline-block; margin-top: 8px; padding: 8px 14px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                           📥 Download Media
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Additional Business Information -->
                <?php if (!empty($form_data['app_businessAddress']) || !empty($form_data['app_businessEmail']) || !empty($form_data['app_city']) || !empty($form_data['app_state']) || !empty($form_data['app_country']) || !empty($form_data['app_pincode'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #34495e; padding-bottom: 8px; margin-bottom: 18px;">🏢 Business Information</h4>
                    <?php if (!empty($form_data['app_businessAddress'])): ?>
                    <div class="field-group">
                        <div class="field-label">Business Address</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_businessAddress'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_businessEmail'])): ?>
                    <div class="field-group">
                        <div class="field-label">Business Email</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_businessEmail']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_city'])): ?>
                    <div class="field-group">
                        <div class="field-label">City</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_city']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_state'])): ?>
                    <div class="field-group">
                        <div class="field-label">State</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_state']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_country'])): ?>
                    <div class="field-group">
                        <div class="field-label">Country</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_country']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_pincode'])): ?>
                    <div class="field-group">
                        <div class="field-label">Pincode</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_pincode']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Banking Information -->
                <?php if (!empty($form_data['app_bankName']) || !empty($form_data['app_bankAccount']) || !empty($form_data['app_ifscCode'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #16a085; padding-bottom: 8px; margin-bottom: 18px;">🏦 Banking Information</h4>
                    <?php if (!empty($form_data['app_bankName'])): ?>
                    <div class="field-group">
                        <div class="field-label">Bank Name</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_bankName']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_bankAccount'])): ?>
                    <div class="field-group">
                        <div class="field-label">Bank Account</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_bankAccount']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_ifscCode'])): ?>
                    <div class="field-group">
                        <div class="field-label">IFSC Code</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_ifscCode']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Working Hours -->
                <?php if (!empty($form_data['app_workingHoursMode']) || !empty($form_data['app_openingTime']) || !empty($form_data['app_closingTime']) || !empty($form_data['app_openDays'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #8e44ad; padding-bottom: 8px; margin-bottom: 18px;">⏰ Working Hours</h4>
                    <?php if (!empty($form_data['app_workingHoursMode'])): ?>
                    <div class="field-group">
                        <div class="field-label">Working Hours Mode</div>
                        <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_workingHoursMode'], [
                            'always-open' => 'Always open',
                            'selected-hours' => 'Open for selected hours'
                        ])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_openingTime'])): ?>
                    <div class="field-group">
                        <div class="field-label">Opening Time</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_openingTime']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_closingTime'])): ?>
                    <div class="field-group">
                        <div class="field-label">Closing Time</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_closingTime']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_openDays'])): ?>
                    <div class="field-group">
                        <div class="field-label">Open Days</div>
                        <div class="field-value"><?php echo displayOpenDays($form_data['app_openDays']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Communication Preferences -->
                <?php if (!empty($form_data['app_preferredCommunication']) || !empty($form_data['app_bestContactTime'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #2980b9; padding-bottom: 8px; margin-bottom: 18px;">📞 Communication Preferences</h4>
                    <?php if (!empty($form_data['app_preferredCommunication'])): ?>
                    <div class="field-group">
                        <div class="field-label">Preferred Communication</div>
                        <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_preferredCommunication'], [
                            'whatsapp' => 'WhatsApp',
                            'email' => 'Email',
                            'call' => 'Phone call',
                            'video-call' => 'Video call',
                            'project-manager' => 'Through project manager'
                        ])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_bestContactTime'])): ?>
                    <div class="field-group">
                        <div class="field-label">Best Contact Time</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_bestContactTime']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Additional Information -->
                <?php if (!empty($form_data['app_assetLinks']) || !empty($form_data['app_reference_apps']) || !empty($form_data['app_additionalNotes'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #27ae60; padding-bottom: 8px; margin-bottom: 18px;">📋 Additional Information</h4>
                    <?php if (!empty($form_data['app_assetLinks'])): ?>
                    <div class="field-group">
                        <div class="field-label">Asset Links</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_assetLinks'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_reference_apps'])): ?>
                    <div class="field-group">
                        <div class="field-label">Reference Apps</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_reference_apps'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_additionalNotes'])): ?>
                    <div class="field-group">
                        <div class="field-label">Additional Notes</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_additionalNotes'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($form_data['app_galleryTitle']) || !empty($form_data['app_galleryDescription'])): ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #9b59b6; padding-bottom: 8px; margin-bottom: 18px;">🖼️ Gallery</h4>
                    <?php if (!empty($form_data['app_galleryTitle'])): ?>
                    <div class="field-group">
                        <div class="field-label">Gallery Title</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_galleryTitle']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($form_data['app_galleryDescription'])): ?>
                    <div class="field-group">
                        <div class="field-label">Description</div>
                        <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_galleryDescription'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php
                // Display testimonials
                $appTestimonials = [];
                foreach ($form_data as $key => $value) {
                    if (preg_match('/^app_testimonial_(\d+)_name$/', $key, $matches)) {
                        $index = $matches[1];
                        $appTestimonials[$index] = [
                            'name' => $value,
                            'date' => $form_data["app_testimonial_{$index}_date"] ?? '',
                            'text' => $form_data["app_testimonial_{$index}_text"] ?? '',
                            'image' => $form_data["app_testimonial_{$index}_image"] ?? '',
                            'image_type' => $form_data["app_testimonial_{$index}_image_type"] ?? 'image/jpeg',
                            'image_data' => $form_data["app_testimonial_{$index}_image_data"] ?? '',
                            'image_path' => $form_data["app_testimonial_{$index}_image_path"] ?? ''
                        ];
                    }
                }
                if (!empty($appTestimonials)): 
                ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #e67e22; padding-bottom: 8px; margin-bottom: 18px;">💬 Testimonials</h4>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <?php foreach ($appTestimonials as $index => $testimonial): ?>
                        <div class="field-group" style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; background: #fafafa;">
                            <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px;">
                                <?php if (!empty($testimonial['image_data']) || !empty($testimonial['image_path'])): ?>
                                <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: #f0f0f0;">
                                    <img src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_testimonial_' . $index . '_image')); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($testimonial['name']); ?>">
                                </div>
                                <?php endif; ?>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 16px; color: #2c3e50; margin-bottom: 5px;">
                                        <?php echo htmlspecialchars($testimonial['name'] ?: 'Anonymous'); ?>
                                    </div>
                                    <?php if (!empty($testimonial['date'])): ?>
                                    <div style="font-size: 13px; color: #6c757d;">
                                        <?php echo htmlspecialchars($testimonial['date']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($testimonial['text'])): ?>
                            <div style="font-style: italic; color: #555; padding: 15px; background: white; border-radius: 8px; border-left: 3px solid #e67e22;">
                                "<?php echo nl2br(htmlspecialchars($testimonial['text'])); ?>"
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($testimonial['image_data']) || !empty($testimonial['image_path'])): ?>
                            <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_testimonial_' . $index . '_image', true)); ?>" 
                               download="<?php echo htmlspecialchars($testimonial['image'] ?? 'testimonial-' . $index . '.jpg'); ?>"
                               style="display: inline-block; margin-top: 10px; padding: 8px 14px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600;">
                               📥 Download Image
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Display gallery images
                $appGalleryImages = [];
                foreach ($form_data as $key => $value) {
                    if (preg_match('/^app_gallery_(\d+)_image_(data|path)$/', $key, $matches)) {
                        $index = $matches[1];
                        $appGalleryImages[$index] = [
                            'image' => $form_data["app_gallery_{$index}_image"] ?? '',
                            'type' => $form_data["app_gallery_{$index}_image_type"] ?? 'image/jpeg',
                            'data' => $form_data["app_gallery_{$index}_image_data"] ?? '',
                            'path' => $form_data["app_gallery_{$index}_image_path"] ?? '',
                            'caption' => $form_data["app_gallery_{$index}_caption"] ?? ''
                        ];
                    }
                }
                if (!empty($appGalleryImages)): 
                ?>
                <div class="subsection">
                    <h4 style="color: #2c3e50; border-bottom: 2px solid #9b59b6; padding-bottom: 8px; margin-bottom: 18px;">🖼️ Gallery Images</h4>
                    <div class="field-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                        <?php foreach ($appGalleryImages as $index => $image): ?>
                        <div class="field-group" style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 15px; background: #fafafa;">
                            <div style="width: 100%; height: 200px; border-radius: 8px; overflow: hidden; margin-bottom: 12px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <img src="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_gallery_' . $index . '_image')); ?>" 
                                     style="max-width: 100%; max-height: 100%; object-fit: contain;" 
                                     alt="Gallery Image <?php echo $index; ?>">
                            </div>
                            <?php if (!empty($image['caption'])): ?>
                            <div style="font-size: 13px; color: #6c757d; margin-bottom: 10px; text-align: center;">
                                <?php echo htmlspecialchars($image['caption']); ?>
                            </div>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars(fieldAssetUrl($client['client_id'], 'app_gallery_' . $index . '_image', true)); ?>" 
                               download="<?php echo htmlspecialchars($image['image'] ?? 'gallery-image-' . $index . '.jpg'); ?>"
                               style="display: block; width: 100%; text-align: center; padding: 10px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 18px; font-weight: 600;">
                               📥 Download Image
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: Platform -->
        <div class="section">
            <div class="section-header">📱 Platform</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">Target Platforms</div>
                        <div class="field-value"><?php echo displayArray(formatMappedValues($form_data['app_target_platforms'] ?? [], [
                            'ios' => 'iOS',
                            'android' => 'Android',
                            'both' => 'Both iOS & Android',
                            'web' => 'Web App'
                        ])); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Development Type</div>
                        <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_development_type'] ?? '', [
                            'native' => 'Native Development',
                            'react-native' => 'React Native',
                            'flutter' => 'Flutter',
                            'ionic' => 'Ionic',
                            'pwa' => 'Progressive Web App',
                            'not-sure' => 'Not Sure - Need Recommendation'
                        ])); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Features -->
        <div class="section">
            <div class="section-header">📱 Features</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">Core Features</div>
                    <div class="field-value"><?php echo displayArray(formatAppFeatureValues($form_data['app_features'] ?? [])); ?></div>
                </div>
                <?php if (!empty($form_data['app_custom_features'])): ?>
                <div class="field-group">
                    <div class="field-label">Custom Features</div>
                    <div class="field-value"><?php echo nl2br(htmlspecialchars($form_data['app_custom_features'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section: Design -->
        <div class="section">
            <div class="section-header">📱 Design</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">Design Available</div>
                        <div class="field-value"><?php echo displayValue(formatMappedValue($form_data['app_design_available'] ?? '', [
                            'yes' => 'Yes - We have designs',
                            'no' => 'No - Need design',
                            'partial' => 'Partial - Some designs ready'
                        ])); ?></div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">UI Style</div>
                        <div class="field-value"><?php echo displayValue($form_data['app_ui_style'] ?? ''); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: User Roles -->
        <div class="section">
            <div class="section-header">📱 User Roles</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">User Types</div>
                    <div class="field-value">
                        <?php
                        $roles = normalizeArrayValues($form_data['app_user_roles'] ?? []);
                        $roleLabels = [
                            'user' => 'Regular User',
                            'admin' => 'Admin',
                            'vendor' => 'Vendor/Provider',
                            'moderator' => 'Moderator',
                            'guest' => 'Guest/Anonymous',
                            'premium' => 'Premium User'
                        ];
                        $displayRoles = [];
                        foreach ($roles as $role) {
                            $displayRoles[] = $roleLabels[$role] ?? ucwords(str_replace('-', ' ', $role));
                        }
                        echo displayArray($displayRoles);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Notifications -->
        <div class="section">
            <div class="section-header">📱 Notifications</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">Notification Types</div>
                    <div class="field-value">
                        <?php
                        $notifications = normalizeArrayValues($form_data['app_notification_types'] ?? []);
                        $notificationLabels = [
                            'email' => 'Email Notifications',
                            'sms' => 'SMS Notifications',
                            'push' => 'Push Notifications',
                            'in-app' => 'In-app Notifications'
                        ];
                        $displayNotifications = [];
                        foreach ($notifications as $notification) {
                            $displayNotifications[] = $notificationLabels[$notification] ?? ucwords(str_replace('-', ' ', $notification));
                        }
                        echo displayArray($displayNotifications);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Maintenance -->
        <div class="section">
            <div class="section-header">📱 Maintenance</div>
            <div class="section-content">
                <div class="field-grid">
                    <div class="field-group">
                        <div class="field-label">Updates Required</div>
                        <div class="field-value">
                            <?php
                            $updatesRequiredLabels = [
                                'regular' => 'Regular Updates',
                                'occasional' => 'Occasional Updates',
                                'minimal' => 'Minimal Updates'
                            ];
                            $updatesRequired = $form_data['app_updates_required'] ?? '';
                            echo displayValue($updatesRequiredLabels[$updatesRequired] ?? ($updatesRequired !== '' ? ucwords(str_replace('-', ' ', $updatesRequired)) : ''));
                            ?>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="field-label">Bug Fix Support</div>
                        <div class="field-value">
                            <?php
                            $bugSupportLabels = [
                                'yes' => 'Yes - Need ongoing support',
                                'no' => 'No - One-time delivery'
                            ];
                            $bugFixSupport = $form_data['app_bug_fix_support'] ?? '';
                            echo displayValue($bugSupportLabels[$bugFixSupport] ?? $bugFixSupport);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Final Details -->
        <div class="section">
            <div class="section-header">📱 Final Details</div>
            <div class="section-content">
                <div class="field-group">
                    <div class="field-label">Contact Information</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['app_contactName'])): ?>
                            <strong>Name:</strong> <?php echo displayValue($form_data['app_contactName']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_contactEmail'])): ?>
                            <strong>Email:</strong> <?php echo displayValue($form_data['app_contactEmail']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_contactPhone'])): ?>
                            <strong>Phone:</strong> <?php echo displayValue($form_data['app_contactPhone']); ?><br>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">Approval & Communication</div>
                    <div class="field-value">
                        <?php if (!empty($form_data['app_decisionMaker'])): ?>
                            <strong>Decision Maker:</strong> <?php echo displayValue($form_data['app_decisionMaker']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_reviewerCount'])): ?>
                            <strong>Reviewers:</strong> <?php echo displayValue($form_data['app_reviewerCount']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($form_data['app_deadlineReason'])): ?>
                            <strong>Deadline Reason:</strong> <?php echo displayValue($form_data['app_deadlineReason']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
        function generatePDF(evt) {
            const element = document.getElementById('pdfContent');
            const btn = evt?.currentTarget || evt?.target || document.querySelector('.btn-pdf');
            const originalText = btn ? btn.innerHTML : '';
            
            // Temporarily show all images and containers for PDF generation
            const hiddenImages = document.querySelectorAll('.section-content img, .client-info img, .content-card img, .subsection img');
            const hiddenContainers = document.querySelectorAll('div[style*="width: 80px"][style*="height: 80px"], div[style*="width: 100px"][style*="height: 100px"], div[style*="width: 150px"][style*="height: 150px"], div[style*="width: 100%"][style*="height: 200px"], div[style*="background: #f0f0f0"][style*="overflow: hidden"]');
            
            // Store original display values and show elements
            const originalDisplays = [];
            hiddenImages.forEach(img => {
                originalDisplays.push({element: img, display: img.style.display});
                img.style.display = '';
            });
            hiddenContainers.forEach(container => {
                originalDisplays.push({element: container, display: container.style.display});
                container.style.display = '';
            });
            
            const opt = {
                margin: [10, 10, 10, 10], // top, left, bottom, right
                filename: '<?php echo htmlspecialchars($client['client_id']); ?>_client_details.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    letterRendering: true,
                    logging: false,
                    allowTaint: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                },
                pagebreak: { mode: 'css' }
            };

            // Show loading state
            if (btn) {
                btn.innerHTML = 'Generating PDF...';
                btn.disabled = true;
            }

            html2pdf().set(opt).from(element).save().then(() => {
                // Restore original display values
                originalDisplays.forEach(item => {
                    item.element.style.display = item.display;
                });
                
                // Restore button state
                if (btn) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }).catch((error) => {
                // Restore original display values
                originalDisplays.forEach(item => {
                    item.element.style.display = item.display;
                });
                
                console.error('PDF generation failed:', error);
                alert('PDF generation failed. Please try again.');
                if (btn) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        }

        function deleteClient() {
            const clientId = '<?php echo htmlspecialchars($client['client_id']); ?>';
            const btn = event.target;
            const originalText = btn.innerHTML;
            
            // Store for later use
            window.clientToDelete = clientId;
            window.deleteBtn = btn;
            window.originalBtnText = originalText;
            
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            window.clientToDelete = null;
            window.deleteBtn = null;
            window.originalBtnText = null;
        }

        function confirmDelete() {
            if (!window.clientToDelete) return;

            const btn = window.deleteBtn;
            const originalText = window.originalBtnText;
            btn.innerHTML = 'Deleting...';
            btn.disabled = true;

            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'client_id=' + encodeURIComponent(window.clientToDelete)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDeleteModal();
                    document.getElementById('successModal').style.display = 'flex';
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    closeDeleteModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete client. Please try again.');
                btn.innerHTML = originalText;
                btn.disabled = false;
                closeDeleteModal();
            });
        }

        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            window.location.href = 'index.php';
        }

    </script>

    <!-- Custom Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-title">
                <span class="modal-icon">⚠️</span>
                Delete Client
            </div>
            <div class="modal-message">
                Are you sure you want to delete this client? This action cannot be undone and all data will be permanently removed.
            </div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-modal btn-modal-confirm" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal modal-success">
            <div class="modal-title">
                <span class="modal-icon">✅</span>
                Success
            </div>
            <div class="modal-message">
                Client deleted successfully!
            </div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-confirm" onclick="closeSuccessModal()">OK</button>
            </div>
        </div>
    </div>
</body>
</html>
