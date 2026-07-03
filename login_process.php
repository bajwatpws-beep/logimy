<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password.';
        header('Location: index.php');
        exit;
    }

    // Load users from CSV
    $users = read_csv(USERS_CSV);
    $authenticated = false;

    foreach ($users as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            if (password_verify($password, $user['password_hash'])) {
                // Password match
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                $authenticated = true;
                break;
            }
        }
    }

    if ($authenticated) {
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['login_error'] = 'Invalid username or password.';
        header('Location: index.php');
        exit;
    }
} else {
    // Redirect if direct access attempted
    header('Location: index.php');
    exit;
}
?>
