<?php
require_once 'config.php';
check_auth();

$success = '';
$error = '';

$current_email = get_setting('notification_email', 'admin@example.com');
$system_title = get_setting('system_title', 'Logistics Document Management System');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $email = isset($_POST['notification_email']) ? trim($_POST['notification_email']) : '';
        $title = isset($_POST['system_title']) ? trim($_POST['system_title']) : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            save_setting('notification_email', $email);
            save_setting('system_title', $title);
            $current_email = $email;
            $system_title = $title;
            $success = 'System configuration updated successfully!';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_pass = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_pass = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_pass = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $error = 'All password fields are required.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            // Find logged in user details
            $username = $_SESSION['username'];
            $users = read_csv(USERS_CSV);
            $user_index = -1;
            $user_data = null;

            foreach ($users as $index => $user) {
                if ($user['username'] === $username) {
                    $user_index = $index;
                    $user_data = $user;
                    break;
                }
            }

            if ($user_data && password_verify($current_pass, $user_data['password_hash'])) {
                // Verified current password, update to new hash
                $users[$user_index]['password_hash'] = password_hash($new_pass, PASSWORD_DEFAULT);
                $headers = ['username', 'password_hash', 'created_at'];
                if (save_csv_transactional(USERS_CSV, $users, $headers)) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to write updated password to storage.';
                }
            } else {
                $error = 'Incorrect current password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Logistics DMS</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            --background-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --navbar-bg: #1e293b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-dark);
            color: #f8fafc;
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }

        .navbar-custom {
            background-color: var(--navbar-bg);
            border-bottom: 1px solid var(--card-border);
        }

        .navbar-brand {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-link {
            color: #94a3b8;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: #f8fafc;
        }

        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-control {
            background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            padding: 10px 14px;
            border-radius: 8px;
        }

        .form-control:focus {
            background-color: #0f172a;
            border-color: #6366f1;
            color: #f8fafc;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            font-weight: 500;
            border-radius: 8px;
            padding: 10px 20px;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-shield-lock-fill me-2 fs-4"></i>Logistics DMS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="drivers.php"><i class="bi bi-people me-1"></i> Drivers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="trucks.php"><i class="bi bi-truck me-1"></i> Fleet Assets</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link active me-3" href="settings.php"><i class="bi bi-gear me-1"></i> Settings</a>
                </li>
                <li class="nav-item">
                    <span class="navbar-text text-secondary me-3">Logged in: <strong><?= sanitize($_SESSION['username']) ?></strong></span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-danger btn-sm rounded-pill px-3" href="logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-12 mb-4">
            <h2 class="h3 font-weight-bold">System Configuration & Settings</h2>
            <p class="text-secondary">Manage notification preferences, system information, and administrator accounts.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= sanitize($success) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= sanitize($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Settings Panel -->
        <div class="col-md-6">
            <div class="glass-card">
                <h4 class="mb-4 d-flex align-items-center">
                    <i class="bi bi-envelope-at text-primary me-2"></i> Notification Settings
                </h4>
                <form action="settings.php" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="mb-3">
                        <label for="system_title" class="form-label text-secondary">System Title</label>
                        <input type="text" class="form-control" id="system_title" name="system_title" value="<?= sanitize($system_title) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="notification_email" class="form-label text-secondary">Notification Recipient Email</label>
                        <input type="email" class="form-control" id="notification_email" name="notification_email" value="<?= sanitize($current_email) ?>" required>
                        <div class="form-text text-secondary">This email address will receive daily digest reports generated by automated cron scripts detailing expired documents.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> Save Configurations
                    </button>
                </form>
            </div>
        </div>

        <!-- Password Panel -->
        <div class="col-md-6">
            <div class="glass-card">
                <h4 class="mb-4 d-flex align-items-center">
                    <i class="bi bi-shield-key text-primary me-2"></i> Update Password
                </h4>
                <form action="settings.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label text-secondary">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label text-secondary">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text text-secondary">Minimum length: 6 characters. Use alphanumeric keys.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label text-secondary">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-shield-check me-1"></i> Update Admin Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
