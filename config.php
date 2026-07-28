<?php
// MySQL Database Configuration
// Note: Using '127.0.0.1' instead of 'localhost' prevents slow DNS/IPv6 resolution delays on live/production servers.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'client_requirements-form');
define('DB_USER', 'client_requirements');
define('DB_PASS', 'client_requirements');

// Security settings
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Change this in production

function getDBConnection() {
    $conn = mysqli_init();
    if (!$conn) {
        die("Connection failed: mysqli_init failed");
    }
    
    // Set 5 seconds connection timeout
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    // Suppress warning with @ and connect
    if (!@$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        die("Database connection failed. Please verify your DB credentials (DB_HOST, DB_USER, DB_PASS, DB_NAME) in config.php. Error: " . mysqli_connect_error());
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

// Call initialization (Commented out for live production to avoid connection overhead on every request. 
// You can uncomment this if you need to re-initialize the database tables).
// initializeDatabase();
?>