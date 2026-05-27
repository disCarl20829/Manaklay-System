<?php
session_start();
require 'db.php';

$error = '';
if (isset($_SESSION['user_id'])) {
    header("Location: logbook.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // UPDATED: Used 'user_id' instead of 'id' to match your table columns
    $stmt = $pdo->prepare("SELECT user_id, username, password FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password (plain text comparison as before)
    if ($user && ($password === $user['password'])) {
        // UPDATED: Use the correct column name 'user_id'
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        
        header("Location: logbook.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Customer System - Login</title>
    <link rel="icon" type="image/x-icon" href="./../resources/logo.jpg">
    <style>
        :root {
            --sidebar-bg: #0A192F;
            --primary-blue: #1E3A8A;
            --accent-orange: #F59E0B;
            --bg-light: #F4F7F6;
            --text-dark: #1F2937;
            --text-muted: #6B7280;
            --danger-red: #EF4444;
            --card-bg: #FFFFFF;
        }

        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--sidebar-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            width: 100%;
            max-width: 350px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .login-card h2 {
            color: var(--sidebar-bg);
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            box-sizing: border-box;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary-blue);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background-color: var(--primary-blue);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background-color: var(--accent-orange);
            color: var(--sidebar-bg);
        }

        .error-msg {
            background: #FEE2E2;
            color: var(--danger-red);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <h2>Manaklay Beach & Park Resort</h2>
        <h3>Customer Logbook System</h3>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login to Dashboard</button>
        </form>
    </div>

</body>

</html>