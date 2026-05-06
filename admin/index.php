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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Client Requirements</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-form input[type="text"],
        .filter-form select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            min-width: 200px;
        }

        .filter-form input[type="text"] {
            flex: 1;
            min-width: 250px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .client-id {
            font-weight: 600;
            color: #667eea;
        }

        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 120px;
            min-width: 120px;
            text-align: center;
        }

        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status.new {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status.in-progress {
            background: #fff3e0;
            color: #f57c00;
        }

        .status.completed {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .btn-view {
            background: #667eea;
            color: white;
            padding: 6px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }

        .btn-view:hover {
            background: #5a6fd8;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 6px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
            margin-left: 5px;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .actions-cell {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-icon {
            font-size: 1.5rem;
        }

        .modal-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-modal-cancel:hover {
            background: #5a6268;
        }

        .btn-modal-confirm {
            background: #dc3545;
            color: white;
        }

        .btn-modal-confirm:hover {
            background: #c82333;
        }

        /* Success Modal Styles */
        .modal-success .modal-title {
            color: #28a745;
        }

        .modal-success .btn-modal-confirm {
            background: #28a745;
        }

        .modal-success .btn-modal-confirm:hover {
            background: #218838;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-form input[type="text"],
            .filter-form select {
                min-width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Client Requirements Dashboard</h1>
            <form method="post" action="logout.php" style="display: inline;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </header>

    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($clients); ?></div>
                <div class="stat-label">Total Clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'New')); ?></div>
                <div class="stat-label">New</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'In Progress')); ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'Completed')); ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search by Client ID, Name, or Email..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="New" <?php echo $status_filter === 'New' ? 'selected' : ''; ?>>New</option>
                    <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
                <select name="project_type">
                    <option value="">All Types</option>
                    <option value="website" <?php echo $project_type_filter === 'website' ? 'selected' : ''; ?>>Website</option>
                    <option value="mobile-app" <?php echo $project_type_filter === 'mobile-app' ? 'selected' : ''; ?>>Mobile App</option>
                    <option value="both" <?php echo $project_type_filter === 'both' ? 'selected' : ''; ?>>Both</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="index.php" class="btn" style="background: #6c757d; color: white; text-decoration: none;">Clear</a>
            </form>
        </div>

        <!-- Clients Table -->
        <div class="table-container">
            <?php if (count($clients) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): 
                            // Decode form_data to get fallback values for mobile app
                            $form_data = json_decode($client['form_data'] ?? '{}', true);
                            
                            // Get project type
                            $project_type = $form_data['project_type'] ?? $form_data['projectType'] ?? 'Unknown';
                            $project_type_label = match($project_type) {
                                'website' => 'Website',
                                'mobile-app' => 'Mobile App',
                                'both' => 'Both',
                                default => 'Unknown'
                            };
                            
                            // Get name from client table or form_data fallback - prioritize contact name
                            $display_name = $client['name'];
                            if (empty($display_name) || $display_name === 'Unknown Company' || $display_name === 'Unknown Contact') {
                                // Use contact name from form (for both website and mobile app), fallback to business name
                                $display_name = $form_data['app_contactName'] ?? $form_data['contactName'] ?? $form_data['app_businessName'] ?? $form_data['companyName'] ?? '';
                            }
                            if (empty($display_name)) $display_name = 'Unknown';
                            
                            // Get email from client table or form_data fallback
                            $display_email = $client['email'];
                            if (empty($display_email)) {
                                $display_email = $form_data['app_contactEmail'] ?? $form_data['contactEmail'] ?? '';
                            }
                            if (empty($display_email)) $display_email = 'Not Provided';
                            
                            // Update database if name is still showing as Unknown but form_data has proper name
                            if (($client['name'] === 'Unknown Company' || empty($client['name'])) && !empty($display_name) && $display_name !== 'Unknown') {
                                // Reopen database connection for updates
                                $update_conn = getDBConnection();
                                $update_sql = "UPDATE clients SET name = ?, email = ? WHERE id = ?";
                                $update_stmt = $update_conn->prepare($update_sql);
                                $update_stmt->bind_param('ssi', $display_name, $display_email, $client['id']);
                                $update_stmt->execute();
                                $update_stmt->close();
                                $update_conn->close();
                                $client['name'] = $display_name; // Update current row data
                                $client['email'] = $display_email; // Update current row data
                            }
                        ?>
                            <tr>
                                <td class="client-id"><?php echo htmlspecialchars($client['client_id']); ?></td>
                                <td><?php echo htmlspecialchars($display_name); ?></td>
                                <td><?php echo htmlspecialchars($display_email); ?></td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 500; 
                                        <?php 
                                        if ($project_type === 'website') echo 'background: #e3f2fd; color: #1976d2;';
                                        elseif ($project_type === 'mobile-app') echo 'background: #f3e5f5; color: #7b1fa2;';
                                        elseif ($project_type === 'both') echo 'background: #e8f5e8; color: #2e7d32;';
                                        else echo 'background: #f5f5f5; color: #666;';
                                        ?>">
                                        <?php echo htmlspecialchars($project_type_label); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($client['created_at'])); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower(str_replace(' ', '-', $client['status'])); ?>">
                                        <?php echo htmlspecialchars($client['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="view.php?id=<?php echo urlencode($client['client_id']); ?>" class="btn-view">View</a>
                                        <button onclick="deleteClient('<?php echo htmlspecialchars($client['client_id']); ?>')" class="btn-delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <h3>No clients found</h3>
                    <p>Try adjusting your search criteria or check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let clientToDelete = null;
        let deleteBtn = null;

        function deleteClient(clientId) {
            clientToDelete = clientId;
            deleteBtn = event.target;
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
            deleteBtn.innerHTML = 'Deleting...';
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
