<?php
// MySQL Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'client_requirements');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security settings
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Change this in production

// Create database connection
function getDBConnection() {
    // First try to connect without specifying database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null; // Return null instead of dying
    }
    
    // Try to create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    
    // Select the database
    if (!$conn->select_db(DB_NAME)) {
        error_log("Failed to select database: " . $conn->error);
        return null;
    }
    
    return $conn;
}

// Initialize database tables
function initializeDatabase() {
    $conn = getDBConnection();
    
    // Create clients table
    $sql = "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(50),
        company_name VARCHAR(255),
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('New', 'In Progress', 'Completed') DEFAULT 'New',
        form_data LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("Error creating clients table: " . $conn->error);
    }

    // Keep the status enum in sync with the admin UI.
    $statusColumn = $conn->query("SHOW COLUMNS FROM clients LIKE 'status'");
    if ($statusColumn && $statusColumn->num_rows > 0) {
        $statusInfo = $statusColumn->fetch_assoc();
        $type = $statusInfo['Type'] ?? '';
        if (strpos($type, "Hold") === false) {
            $alterSql = "ALTER TABLE clients MODIFY status ENUM('New', 'In Progress', 'Hold', 'Completed') DEFAULT 'New'";
            if (!$conn->query($alterSql)) {
                die("Error updating clients status enum: " . $conn->error);
            }
        }
    }
    
    // Create admin_users table
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("Error creating admin_users table: " . $conn->error);
    }
    
    $conn->close();
}

// Call initialization
initializeDatabase();
?>
