<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get database connection
$conn = getDBConnection();

// Handle search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$project_type_filter = $_GET['project_type'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

// Only apply search filter if search is provided
if (!empty($search)) {
    $where_conditions[] = "(client_id LIKE ? OR name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($project_type_filter)) {
    $where_conditions[] = "JSON_EXTRACT(form_data, '$.project_type') = ?";
    $params[] = $project_type_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get clients
$sql = "SELECT * FROM clients $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$clients = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
// Don't close connection here, it's needed later for updates

function kv_initials($name) {
    $name = trim((string) $name);
    if ($name === '' || $name === 'Unknown') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    $chars = [];
    foreach (array_slice($parts, 0, 2) as $p) {
        $chars[] = strtoupper(substr($p, 0, 1));
    }
    return implode('', $chars) ?: '?';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Client Requirements</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <style>
        :root {
            --kv-bg: #f9f9f9;
            --kv-on-bg: #1b1b1b;
            --kv-on-variant: #4e4355;
            --kv-surface: #ffffff;
            --kv-surface-low: #f3f3f3;
            --kv-outline-variant: #d1c1d7;
            --kv-outline: #807286;
            --kv-primary: #8000c6;
            --kv-primary-bright: #a020f0;
            --kv-on-primary: #ffffff;
            --kv-secondary: #7b41b3;
            --kv-secondary-fixed: #f0dbff;
            --kv-primary-fixed: #f3daff;
            --kv-primary-fixed-dim: #e3b5ff;
            --kv-tertiary-fixed: #eadef7;
            --kv-tertiary-on: #4b4357;
            --kv-lavender: #f2e6ff;
            --kv-error: #ba1a1a;
            --kv-error-bg: #ffdad6;
            --kv-success: #2e7d32;
            --kv-warn: #f57c00;
            --kv-shadow: 0 12px 40px rgba(128, 0, 198, 0.08);
            --kv-radius: 8px;
            --kv-radius-xl: 24px;
            --kv-sidebar: 256px;
            --kv-top: 64px;
            --kv-gutter: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body.kv-body {
            font-family: Inter, system-ui, sans-serif;
            background: var(--kv-bg);
            color: var(--kv-on-bg);
            min-height: 100vh;
        }

        .kv-headline { font-family: "Space Grotesk", sans-serif; }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 22px;
            vertical-align: middle;
        }

        .kv-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--kv-sidebar);
            z-index: 40;
            padding-top: calc(var(--kv-top) + 8px);
            padding-bottom: 16px;
            background: var(--kv-surface-low);
            border-right: 1px solid var(--kv-outline-variant);
            display: flex;
            flex-direction: column;
        }

        .kv-sidebar__brand {
            padding: 0 24px 24px;
        }

        .kv-sidebar__brand h2 {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--kv-primary);
        }

        .kv-sidebar__brand p {
            font-size: 0.9rem;
            color: var(--kv-on-variant);
            margin-top: 4px;
        }

        .kv-nav {
            flex: 1;
            padding: 0 12px;
        }

        .kv-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 4px;
            border-radius: var(--kv-radius);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            color: var(--kv-on-variant);
            transition: background 0.2s, color 0.2s;
        }

        .kv-nav a:hover {
            background: rgba(243, 218, 255, 0.45);
            color: var(--kv-on-bg);
        }

        .kv-nav a.kv-nav--active {
            color: var(--kv-primary);
            background: rgba(240, 219, 255, 0.55);
            border-left: 4px solid var(--kv-primary-bright);
            margin-left: -4px;
            padding-left: 16px;
        }

        .kv-nav--footer {
            padding: 12px;
            border-top: 1px solid var(--kv-outline-variant);
        }

        .kv-nav--footer a.kv-logout {
            color: var(--kv-error);
        }

        .kv-nav--footer a.kv-logout:hover {
            background: rgba(255, 218, 214, 0.5);
        }

        .kv-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--kv-top);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--kv-gutter);
            padding-left: calc(var(--kv-sidebar) + var(--kv-gutter));
            background: var(--kv-surface-low);
            border-bottom: 1px solid var(--kv-outline-variant);
        }

        .kv-topbar__title {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--kv-primary);
        }

        .kv-topbar__right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .kv-user {
            text-align: right;
            display: none;
        }

        @media (min-width: 640px) {
            .kv-user { display: block; }
        }

        .kv-user__name {
            font-size: 14px;
            font-weight: 600;
        }

        .kv-user__role {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--kv-on-variant);
        }

        .kv-main {
            margin-left: var(--kv-sidebar);
            padding-top: var(--kv-top);
            min-height: 100vh;
        }

        .kv-main__inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px var(--kv-gutter) 48px;
        }

        .kv-page-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 32px;
        }

        .kv-page-head h1 {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.75rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .kv-page-head p {
            font-size: 1.05rem;
            color: var(--kv-on-variant);
            max-width: 520px;
            line-height: 1.5;
        }

        .kv-bento {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--kv-gutter);
            margin-bottom: 32px;
        }

        .kv-metric {
            background: var(--kv-surface);
            border: 1px solid var(--kv-outline-variant);
            border-radius: var(--kv-radius-xl);
            padding: var(--kv-gutter);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px;
            transition: border-color 0.2s;
        }

        .kv-metric:hover {
            border-color: rgba(160, 32, 240, 0.45);
        }

        .kv-metric__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .kv-metric__icon {
            padding: 8px;
            border-radius: var(--kv-radius);
            background: var(--kv-primary-fixed);
            color: var(--kv-primary);
        }

        .kv-metric__icon--secondary {
            background: var(--kv-secondary-fixed);
            color: var(--kv-secondary);
        }

        .kv-metric__icon--muted {
            background: #e8e8e8;
            color: var(--kv-on-variant);
        }

        .kv-metric__icon--done {
            background: var(--kv-tertiary-fixed);
            color: var(--kv-tertiary-on);
        }

        .kv-metric__label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--kv-on-variant);
            margin-bottom: 4px;
        }

        .kv-metric__value {
            font-family: "Space Grotesk", sans-serif;
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .kv-metric__value--accent {
            color: var(--kv-primary-bright);
        }

        .kv-card {
            background: var(--kv-surface);
            border: 1px solid var(--kv-outline-variant);
            border-radius: var(--kv-radius-xl);
            overflow: hidden;
            margin-bottom: 32px;
        }

        .kv-filters {
            padding: var(--kv-gutter);
            border-bottom: 1px solid var(--kv-outline-variant);
            background: rgba(243, 243, 243, 0.5);
        }

        .kv-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .kv-filter-form input[type="text"],
        .kv-filter-form select {
            padding: 10px 14px;
            border: 1px solid var(--kv-outline-variant);
            border-radius: var(--kv-radius);
            font-family: inherit;
            font-size: 0.9rem;
            background: var(--kv-surface);
            color: var(--kv-on-bg);
        }

        .kv-filter-form input[type="text"] {
            flex: 1;
            min-width: 220px;
        }

        .kv-filter-form input:focus,
        .kv-filter-form select:focus {
            outline: none;
            border-color: var(--kv-primary-bright);
            box-shadow: 0 0 0 3px rgba(160, 32, 240, 0.15);
        }

        .kv-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: var(--kv-radius);
            font-size: 0.9rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
        }

        .kv-btn:active { transform: scale(0.98); }

        .kv-btn--primary {
            background: var(--kv-primary-bright);
            color: var(--kv-on-primary);
        }

        .kv-btn--primary:hover {
            background: var(--kv-primary);
            box-shadow: 0 6px 20px rgba(160, 32, 240, 0.3);
        }

        .kv-btn--ghost {
            background: var(--kv-secondary);
            color: var(--kv-on-primary);
        }

        .kv-btn--ghost:hover {
            filter: brightness(1.08);
        }

        .kv-table-wrap {
            overflow-x: auto;
        }

        .kv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .kv-table th {
            text-align: left;
            padding: 16px var(--kv-gutter);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--kv-on-variant);
            background: rgba(243, 243, 243, 0.35);
            border-bottom: 1px solid var(--kv-outline-variant);
        }

        .kv-table td {
            padding: 16px var(--kv-gutter);
            border-bottom: 1px solid var(--kv-outline-variant);
            vertical-align: middle;
        }

        .kv-table tbody tr {
            transition: background 0.15s;
        }

        .kv-table tbody tr:hover {
            background: rgba(243, 218, 255, 0.2);
        }

        .kv-cid {
            font-family: ui-monospace, monospace;
            font-weight: 700;
            color: var(--kv-primary);
            font-size: 0.9rem;
        }

        .kv-person {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .kv-avatar {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
            background: var(--kv-secondary-fixed);
            color: var(--kv-secondary);
        }

        .kv-avatar--alt {
            background: #c588fe;
            color: #54118a;
        }

        .kv-avatar--tert {
            background: var(--kv-tertiary-fixed);
            color: var(--kv-tertiary-on);
        }

        .kv-person__name {
            font-weight: 600;
        }

        .kv-person__email {
            font-size: 12px;
            color: var(--kv-on-variant);
        }

        .type-chip {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .type-chip--website {
            background: var(--kv-secondary-fixed);
            color: #622599;
        }

        .type-chip--app {
            background: var(--kv-primary-fixed);
            color: #6e00ab;
        }

        .type-chip--both {
            background: #e8f5e9;
            color: var(--kv-success);
        }

        .type-chip--unknown {
            background: #eeeeee;
            color: var(--kv-on-variant);
        }

        .kv-date {
            color: var(--kv-on-variant);
        }

        .kv-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 14px;
        }

        .kv-status__dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .kv-status--new { color: var(--kv-primary-bright); }
        .kv-status--new .kv-status__dot { background: var(--kv-primary-bright); }

        .kv-status--progress { color: var(--kv-warn); }
        .kv-status--progress .kv-status__dot { background: var(--kv-warn); }

        .kv-status--done { color: var(--kv-success); }
        .kv-status--done .kv-status__dot { background: var(--kv-success); }

        .kv-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .kv-icon-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: var(--kv-radius);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s, color 0.2s;
        }

        .kv-icon-btn--view {
            background: rgba(160, 32, 240, 0.1);
            color: var(--kv-primary);
        }

        .kv-icon-btn--view:hover {
            background: var(--kv-primary-bright);
            color: #fff;
        }

        .kv-icon-btn--del {
            background: #f3f3f3;
            color: var(--kv-on-variant);
        }

        .kv-icon-btn--del:hover {
            background: var(--kv-error-bg);
            color: var(--kv-error);
        }

        .kv-section-title {
            padding: var(--kv-gutter);
            border-bottom: 1px solid var(--kv-outline-variant);
            background: rgba(243, 243, 243, 0.5);
        }

        .kv-section-title h3 {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.15rem;
            font-weight: 600;
        }

        .kv-empty {
            text-align: center;
            padding: 48px var(--kv-gutter);
            color: var(--kv-on-variant);
        }

        .kv-empty h3 {
            font-family: "Space Grotesk", sans-serif;
            color: var(--kv-on-bg);
            margin-bottom: 8px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(27, 27, 27, 0.45);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal {
            background: var(--kv-surface);
            border-radius: var(--kv-radius-xl);
            padding: 28px;
            max-width: 420px;
            width: 90%;
            box-shadow: var(--kv-shadow);
            border: 1px solid var(--kv-outline-variant);
            animation: kvModalIn 0.28s ease;
        }

        @keyframes kvModalIn {
            from { transform: translateY(-16px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-message {
            color: var(--kv-on-variant);
            margin-bottom: 22px;
            line-height: 1.5;
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
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
        }

        .btn-modal-cancel {
            background: var(--kv-surface-low);
            color: var(--kv-on-variant);
        }

        .btn-modal-confirm {
            background: var(--kv-error);
            color: #fff;
        }

        .modal-success .modal-title { color: var(--kv-success); }
        .modal-success .btn-modal-confirm {
            background: var(--kv-success);
            color: #fff;
        }

        @media (max-width: 900px) {
            .kv-sidebar {
                width: 100%;
                position: relative;
                height: auto;
                padding-top: 16px;
            }
            .kv-topbar {
                position: relative;
                padding-left: var(--kv-gutter);
                height: auto;
                min-height: 56px;
                flex-wrap: wrap;
                padding-top: 12px;
                padding-bottom: 12px;
            }
            .kv-main {
                margin-left: 0;
                padding-top: 0;
            }
            .kv-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .kv-nav a.kv-nav--active {
                border-left: none;
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="kv-body">
    <aside class="kv-sidebar" aria-label="Main navigation">
        <div class="kv-sidebar__brand">
            <h2>Admin Console</h2>
            <p>Client requirements</p>
        </div>
        <nav class="kv-nav">
            <a class="kv-nav--active" href="index.php">
                <span class="material-symbols-outlined" style="font-size:20px;">dashboard</span>
                Dashboard
            </a>
        </nav>
        <div class="kv-nav kv-nav--footer">
            <a class="kv-logout" href="logout.php">
                <span class="material-symbols-outlined" style="font-size:20px;">logout</span>
                Logout
            </a>
        </div>
    </aside>

    <header class="kv-topbar">
        <span class="kv-topbar__title">Recharge Admin</span>
        <div class="kv-topbar__right">
            <div class="kv-user">
                <div class="kv-user__name">Administrator</div>
                <div class="kv-user__role">Management</div>
            </div>
        </div>
    </header>

    <main class="kv-main">
        <div class="kv-main__inner">
            <div class="kv-page-head">
                <div>
                    <h1>Client Requirements</h1>
                    <p>Review and manage incoming project submissions from active and new clients.</p>
                </div>
            </div>

            <div class="kv-bento">
                <div class="kv-metric">
                    <div class="kv-metric__top">
                        <div class="kv-metric__icon"><span class="material-symbols-outlined">group</span></div>
                    </div>
                    <div>
                        <div class="kv-metric__label">Total clients</div>
                        <div class="kv-metric__value"><?php echo count($clients); ?></div>
                    </div>
                </div>
                <div class="kv-metric">
                    <div class="kv-metric__top">
                        <div class="kv-metric__icon kv-metric__icon--secondary"><span class="material-symbols-outlined">inbox</span></div>
                    </div>
                    <div>
                        <div class="kv-metric__label">New</div>
                        <div class="kv-metric__value kv-metric__value--accent"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'New')); ?></div>
                    </div>
                </div>
                <div class="kv-metric">
                    <div class="kv-metric__top">
                        <div class="kv-metric__icon kv-metric__icon--muted"><span class="material-symbols-outlined">pending_actions</span></div>
                    </div>
                    <div>
                        <div class="kv-metric__label">In progress</div>
                        <div class="kv-metric__value"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'In Progress')); ?></div>
                    </div>
                </div>
                <div class="kv-metric">
                    <div class="kv-metric__top">
                        <div class="kv-metric__icon kv-metric__icon--done"><span class="material-symbols-outlined">check_circle</span></div>
                    </div>
                    <div>
                        <div class="kv-metric__label">Completed</div>
                        <div class="kv-metric__value"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'Completed')); ?></div>
                    </div>
                </div>
            </div>

            <div class="kv-card">
                <div class="kv-filters">
                    <form method="GET" class="kv-filter-form">
                        <input type="text" name="search" placeholder="Search by Client ID, Name, or Email…" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="New" <?php echo $status_filter === 'New' ? 'selected' : ''; ?>>New</option>
                            <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Hold" <?php echo $status_filter === 'Hold' ? 'selected' : ''; ?>>Hold</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                        <select name="project_type">
                            <option value="">All Types</option>
                            <option value="website" <?php echo $project_type_filter === 'website' ? 'selected' : ''; ?>>Website</option>
                            <option value="mobile-app" <?php echo $project_type_filter === 'mobile-app' ? 'selected' : ''; ?>>Mobile App</option>
                            <option value="both" <?php echo $project_type_filter === 'both' ? 'selected' : ''; ?>>Both</option>
                        </select>
                        <button type="submit" class="kv-btn kv-btn--primary">Search</button>
                        <a href="index.php" class="kv-btn kv-btn--ghost">Clear</a>
                    </form>
                </div>

                <div class="kv-section-title">
                    <h3>Submissions</h3>
                </div>

                <div class="kv-table-wrap">
                    <?php if (count($clients) > 0): ?>
                        <table class="kv-table">
                            <thead>
                                <tr>
                                    <th>Client ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client):
                                    $form_data = json_decode($client['form_data'] ?? '{}', true);
                                    $project_type = $form_data['project_type'] ?? $form_data['projectType'] ?? 'Unknown';
                                    $project_type_label = match($project_type) {
                                        'website' => 'Website',
                                        'mobile-app' => 'Mobile App',
                                        'both' => 'Both',
                                        default => 'Unknown'
                                    };
                                    $type_chip_class = match($project_type) {
                                        'website' => 'type-chip type-chip--website',
                                        'mobile-app' => 'type-chip type-chip--app',
                                        'both' => 'type-chip type-chip--both',
                                        default => 'type-chip type-chip--unknown'
                                    };
                                    $display_name = $client['name'];
                                    if (empty($display_name) || $display_name === 'Unknown Company' || $display_name === 'Unknown Contact') {
                                        $display_name = $form_data['app_contactName'] ?? $form_data['contactName'] ?? $form_data['app_businessName'] ?? $form_data['companyName'] ?? $form_data['app_name'] ?? '';
                                    }
                                    if (empty($display_name)) {
                                        $display_name = 'Unknown';
                                    }
                                    $display_email = $client['email'];
                                    if (empty($display_email)) {
                                        $display_email = $form_data['app_contactEmail'] ?? $form_data['contactEmail'] ?? $form_data['businessEmail'] ?? $form_data['app_businessEmail'] ?? '';
                                    }
                                    if (empty($display_email)) {
                                        $display_email = 'Not Provided';
                                    }
                                    if ((empty($client['name']) || in_array($client['name'], ['Unknown Company', 'Unknown Contact'], true)) && !empty($display_name) && $display_name !== 'Unknown') {
                                        $update_conn = getDBConnection();
                                        $update_sql = "UPDATE clients SET name = ?, email = ? WHERE id = ?";
                                        $update_stmt = $update_conn->prepare($update_sql);
                                        $update_stmt->bind_param('ssi', $display_name, $display_email, $client['id']);
                                        $update_stmt->execute();
                                        $update_stmt->close();
                                        $update_conn->close();
                                        $client['name'] = $display_name;
                                        $client['email'] = $display_email;
                                    }
                                    $initials = kv_initials($display_name);
                                    $avatar_class = 'kv-avatar';
                                    $hash = crc32($client['client_id']) % 3;
                                    if ($hash === 1) {
                                        $avatar_class .= ' kv-avatar--alt';
                                    } elseif ($hash === 2) {
                                        $avatar_class .= ' kv-avatar--tert';
                                    }
                                    $status_slug = strtolower(str_replace(' ', '-', $client['status']));
                                    $status_class = 'kv-status';
                                    if ($status_slug === 'new') {
                                        $status_class .= ' kv-status--new';
                                    } elseif ($status_slug === 'in-progress') {
                                        $status_class .= ' kv-status--progress';
                                    } elseif ($status_slug === 'completed') {
                                        $status_class .= ' kv-status--done';
                                    }
                                ?>
                                <tr>
                                    <td><span class="kv-cid"><?php echo htmlspecialchars($client['client_id']); ?></span></td>
                                    <td>
                                        <div class="kv-person">
                                            <span class="<?php echo $avatar_class; ?>"><?php echo htmlspecialchars($initials); ?></span>
                                            <div class="kv-person__name"><?php echo htmlspecialchars($display_name); ?></div>
                                        </div>
                                    </td>
                                    <td class="kv-person__email"><?php echo htmlspecialchars($display_email); ?></td>
                                    <td><span class="<?php echo $type_chip_class; ?>"><?php echo htmlspecialchars($project_type_label); ?></span></td>
                                    <td class="kv-date"><?php echo date('M j, Y', strtotime($client['created_at'])); ?></td>
                                    <td>
                                        <span class="<?php echo $status_class; ?>">
                                            <span class="kv-status__dot" aria-hidden="true"></span>
                                            <?php echo htmlspecialchars($client['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="kv-actions">
                                            <a href="view.php?id=<?php echo urlencode($client['client_id']); ?>" class="kv-icon-btn kv-icon-btn--view">
                                                <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                                                View
                                            </a>
                                            <button type="button" onclick="deleteClient('<?php echo htmlspecialchars($client['client_id']); ?>')" class="kv-icon-btn kv-icon-btn--del">
                                                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="kv-empty">
                            <h3>No clients found</h3>
                            <p>Try adjusting your search criteria or check back later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        let clientToDelete = null;
        let deleteBtn = null;

        function deleteClient(clientId) {
            clientToDelete = clientId;
            deleteBtn = event.target.closest('button');
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            clientToDelete = null;
            deleteBtn = null;
        }

        function confirmDelete() {
            if (!clientToDelete) return;

            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = 'Deleting…';
            deleteBtn.disabled = true;

            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'client_id=' + encodeURIComponent(clientToDelete)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDeleteModal();
                    document.getElementById('successModal').style.display = 'flex';
                } else {
                    alert('Error: ' + data.message);
                    deleteBtn.innerHTML = originalText;
                    deleteBtn.disabled = false;
                    closeDeleteModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete client. Please try again.');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
                closeDeleteModal();
            });
        }

        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            location.reload();
        }
    </script>

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
                <button class="btn-modal btn-modal-cancel" type="button" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-modal btn-modal-confirm" type="button" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

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
                <button class="btn-modal btn-modal-confirm" type="button" onclick="closeSuccessModal()">OK</button>
            </div>
        </div>
    </div>
</body>
</html>
