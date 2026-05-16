<?php
// login_action.php
session_start();
require 'db.php'; // Include the connection

header('Content-Type: application/json');

// Get the JSON data sent from the JavaScript fetch request
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['username']) && isset($data['password'])) {
    $username = trim($data['username']);
    $password = trim($data['password']);

    // Look up the user in the database
    $stmt = $pdo->prepare('SELECT user_id, username, password, full_name, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    /* * NOTE: In your SQL dump, the password is saved as plain text ('test').
     * For production, you should use password_hash() when creating users 
     * and password_verify() here. But this matches your current database state.
     */
    if ($user && $user['password'] === $password) {
        
        // Success! Save user data into session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role']; // e.g., 'admin' or 'staff'

        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'full_name' => $user['full_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please provide both fields.']);
}
?>