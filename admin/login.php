<?php
session_start();
require_once '../config.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Client Requirements</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Inter, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(900px 500px at 10% -10%, rgba(160, 32, 240, 0.12), transparent 55%),
                radial-gradient(700px 400px at 100% 0%, rgba(123, 65, 179, 0.1), transparent 50%),
                #f9f9f9;
            color: #1b1b1b;
        }

        .login-container {
            background: #ffffff;
            padding: 40px;
            border-radius: 24px;
            border: 1px solid #d1c1d7;
            box-shadow: 0 12px 40px rgba(128, 0, 198, 0.08);
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-header h1 {
            font-family: "Space Grotesk", sans-serif;
            color: #8000c6;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #4e4355;
            font-size: 0.95rem;
            line-height: 1.45;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #4e4355;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1c1d7;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #fff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #a020f0;
            box-shadow: 0 0 0 3px rgba(160, 32, 240, 0.15);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .btn-primary {
            background: #a020f0;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #8000c6;
            box-shadow: 0 8px 24px rgba(160, 32, 240, 0.35);
        }

        .btn-primary:active {
            transform: scale(0.99);
        }

        .error-message {
            background: #ffdad6;
            color: #93000a;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            border: 1px solid #ffc4bf;
        }

        .security-note {
            margin-top: 22px;
            padding: 14px 16px;
            background: #f3daff;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #4e4355;
            text-align: center;
            border: 1px solid #e3b5ff;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Admin Login</h1>
            <p>Client Requirements Management System</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <div class="security-note">
            <strong>Security Notice:</strong> This is a restricted area. Unauthorized access is prohibited.
        </div>
    </div>
</body>
</html>
