<?php
require_once 'config.php';
check_auth();

// Load Drivers and Trucks
$drivers = read_csv(DRIVERS_CSV);
$trucks = read_csv(TRUCKS_CSV);
$users = read_csv(USERS_CSV);

// Check if admin is still using default password
$has_default_password = false;
foreach ($users as $user) {
    if ($user['username'] === 'admin' && password_verify('admin123', $user['password_hash'])) {
        $has_default_password = true;
        break;
    }
}

// Calculate Statistics
$total_drivers = count($drivers);
$active_drivers = 0;
foreach ($drivers as $d) {
    if (isset($d['status']) && strcasecmp($d['status'], 'Active') === 0) {
        $active_drivers++;
    }
}

$total_trucks = count($trucks);
$active_trucks = 0;
foreach ($trucks as $t) {
    if (isset($t['status']) && strcasecmp($t['status'], 'Active') === 0) {
        $active_trucks++;
    }
}

// Expiration tracking
$expired_count = 0;
$warning_count = 0;
$safe_count = 0;

$critical_expirations = [];

// Parse Driver Expirations
foreach ($drivers as $d) {
    $expiries = [
        'CNIC' => [
            'date' => isset($d['cnic_expiry']) ? $d['cnic_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ],
        'Commercial Driver License' => [
            'date' => isset($d['license_expiry']) ? $d['license_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ],
        'Police Verification' => [
            'date' => isset($d['police_verification_expiry']) ? $d['police_verification_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ],
        'Drug Test' => [
            'date' => isset($d['drug_test_expiry']) ? $d['drug_test_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ],
        'Vitamin A Test' => [
            'date' => isset($d['vitamin_a_expiry']) ? $d['vitamin_a_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ],
        'Medical Fitness Certificate' => [
            'date' => isset($d['medical_fitness_expiry']) ? $d['medical_fitness_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ],
        'Defensive Driving Training' => [
            'date' => isset($d['defensive_driving_expiry']) ? $d['defensive_driving_expiry'] : '',
            'pdf' => isset($d['documents_pdf']) ? $d['documents_pdf'] : '',
            'token' => isset($d['pdf_token']) ? $d['pdf_token'] : ''
        ]
    ];

    foreach ($expiries as $doc_name => $info) {
        if (empty($info['date'])) continue;
        
        $urgency = get_urgency_status($info['date']);
        if ($urgency['class'] === 'danger') {
            $expired_count++;
        } elseif ($urgency['class'] === 'warning') {
            $warning_count++;
        } else {
            $safe_count++;
        }

        // Add to critical queue if expired or within 30 days
        if ($urgency['class'] === 'danger' || $urgency['class'] === 'warning') {
            $critical_expirations[] = [
                'entity_type' => 'Driver',
                'entity_name' => $d['name'],
                'entity_id' => $d['id'],
                'document_type' => $doc_name,
                'expiry_date' => $info['date'],
                'urgency' => $urgency,
                'pdf' => $info['pdf'],
                'token' => $info['token']
            ];
        }
    }
}

// Parse Truck Expirations
foreach ($trucks as $t) {
    $expiries = [
        'Route Permit' => [
            'date' => isset($t['route_permit_expiry']) ? $t['route_permit_expiry'] : '',
            'pdf' => isset($t['documents_pdf']) ? $t['documents_pdf'] : '',
            'token' => isset($t['pdf_token']) ? $t['pdf_token'] : ''
        ],
        'Vehicle Inspection' => [
            'date' => isset($t['inspection_expiry']) ? $t['inspection_expiry'] : '',
            'pdf' => isset($t['documents_pdf']) ? $t['documents_pdf'] : '',
            'token' => isset($t['pdf_token']) ? $t['pdf_token'] : ''
        ],
        'Token Tax' => [
            'date' => isset($t['token_expiry']) ? $t['token_expiry'] : '',
            'pdf' => isset($t['documents_pdf']) ? $t['documents_pdf'] : '',
            'token' => isset($t['pdf_token']) ? $t['pdf_token'] : ''
        ]
    ];

    foreach ($expiries as $doc_name => $info) {
        if (empty($info['date'])) continue;

        $urgency = get_urgency_status($info['date']);
        if ($urgency['class'] === 'danger') {
            $expired_count++;
        } elseif ($urgency['class'] === 'warning') {
            $warning_count++;
        } else {
            $safe_count++;
        }

        // Add to critical queue if expired or within 30 days
        if ($urgency['class'] === 'danger' || $urgency['class'] === 'warning') {
            $critical_expirations[] = [
                'entity_type' => 'Asset (Truck)',
                'entity_name' => 'Unit #' . $t['unit_number'] . ' (' . $t['license_plate'] . ')',
                'entity_id' => $t['id'],
                'document_type' => $doc_name,
                'expiry_date' => $info['date'],
                'urgency' => $urgency,
                'pdf' => $info['pdf'],
                'token' => $info['token']
            ];
        }
    }
}

// Sort critical expirations: Expired first (ascending by days remaining, i.e., most expired first)
usort($critical_expirations, function($a, $b) {
    return $a['urgency']['days'] <=> $b['urgency']['days'];
});

$system_title = get_setting('system_title', 'Logistics Document Management System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Logistics DMS</title>
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
            padding: 24px;
            margin-bottom: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-card:hover {
            box-shadow: 0 8px 30px rgba(79, 70, 229, 0.15);
        }

        .metric-card {
            border-left: 4px solid #6366f1;
        }

        .metric-card.danger {
            border-left-color: #ef4444;
        }

        .metric-card.warning {
            border-left-color: #f59e0b;
        }

        .metric-card.success {
            border-left-color: #10b981;
        }

        .metric-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .badge-custom {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .table-custom {
            color: #e2e8f0;
        }

        .table-custom th {
            color: #94a3b8;
            font-weight: 600;
            border-bottom-color: var(--card-border);
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom-color: rgba(255, 255, 255, 0.05);
        }

        .alert-warning-custom {
            background-color: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fef3c7;
            border-radius: 12px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-shield-lock-fill me-2 fs-4"></i><?= sanitize($system_title) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
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
                    <a class="nav-link me-3" href="settings.php"><i class="bi bi-gear me-1"></i> Settings</a>
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
    <?php if ($has_default_password): ?>
        <div class="alert alert-warning-custom d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-3 text-warning"></i>
            <div>
                <strong>Security Action Required:</strong> You are currently using the default administrator password. To prevent unauthorized database access, please update your credentials on the <a href="settings.php" class="alert-link text-warning fw-bold text-decoration-underline">Settings page</a> immediately.
            </div>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="h3 font-weight-bold">Compliance Status Terminal</h2>
            <p class="text-secondary">Real-time indicators tracking expired operational certifications and fleet logistics metadata.</p>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="glass-card metric-card success d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-secondary uppercase mb-1">Total Drivers</h6>
                    <h3 class="mb-0 fw-bold"><?= $active_drivers ?> <span class="text-muted fs-6">/ <?= $total_drivers ?> Active</span></h3>
                </div>
                <div class="metric-icon text-success"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card metric-card success d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-secondary uppercase mb-1">Fleet Assets</h6>
                    <h3 class="mb-0 fw-bold"><?= $active_trucks ?> <span class="text-muted fs-6">/ <?= $total_trucks ?> Active</span></h3>
                </div>
                <div class="metric-icon text-success"><i class="bi bi-truck-flatbed"></i></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card metric-card danger d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-secondary uppercase mb-1">Expired (Action Required)</h6>
                    <h3 class="mb-0 fw-bold text-danger"><?= $expired_count ?></h3>
                </div>
                <div class="metric-icon text-danger"><i class="bi bi-x-octagon-fill"></i></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card metric-card warning d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-secondary uppercase mb-1">Expiring (&lt;30 Days)</h6>
                    <h3 class="mb-0 fw-bold text-warning"><?= $warning_count ?></h3>
                </div>
                <div class="metric-icon text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
        </div>
    </div>

    <!-- Critical Document Expirations Table -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <h4 class="mb-4 d-flex align-items-center">
                    <i class="bi bi-shield-exclamation text-danger me-2"></i> Unified Expirations Alert Queue
                </h4>
                
                <?php if (empty($critical_expirations)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-shield-check text-success display-4 mb-3"></i>
                        <h5>No Urgent Certifications Found</h5>
                        <p class="mb-0">All logistics profile documentation and assets indicators are currently safe.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Record Category</th>
                                    <th>Identifier / Profile</th>
                                    <th>Document Type</th>
                                    <th>Expiration Date</th>
                                    <th class="text-center">Days Remaining</th>
                                    <th class="text-center">Urgency State</th>
                                    <th class="text-end">Verification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($critical_expirations as $item): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark border border-secondary text-secondary">
                                                <?= sanitize($item['entity_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php if ($item['entity_type'] === 'Driver'): ?>
                                                    <a href="drivers.php?id=<?= $item['entity_id'] ?>" class="text-white text-decoration-none">
                                                        <?= sanitize($item['entity_name']) ?> <i class="bi bi-box-arrow-in-up-right ms-1 text-secondary" style="font-size: 0.8rem;"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="trucks.php?id=<?= $item['entity_id'] ?>" class="text-white text-decoration-none">
                                                        <?= sanitize($item['entity_name']) ?> <i class="bi bi-box-arrow-in-up-right ms-1 text-secondary" style="font-size: 0.8rem;"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </strong>
                                        </td>
                                        <td><?= sanitize($item['document_type']) ?></td>
                                        <td><?= date('M d, Y', strtotime($item['expiry_date'])) ?></td>
                                        <td class="text-center">
                                            <?php if ($item['urgency']['days'] < 0): ?>
                                                <span class="text-danger fw-bold"><?= abs($item['urgency']['days']) ?> Days Overdue</span>
                                            <?php elseif ($item['urgency']['days'] === 0): ?>
                                                <span class="text-danger fw-bold">Expires Today</span>
                                            <?php else: ?>
                                                <span class="text-warning fw-bold"><?= $item['urgency']['days'] ?> Days Left</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $item['urgency']['badge_class'] ?> rounded-pill px-3 py-2">
                                                <?= sanitize($item['urgency']['label']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!empty($item['pdf'])): ?>
                                                <a href="view.php?token=<?= urlencode($item['token']) ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-file-pdf me-1"></i> View Document
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No Document</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
