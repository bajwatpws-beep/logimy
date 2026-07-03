<?php
require_once 'config.php';

// Secure Unauthenticated PDF Streaming via unique Token
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    if (!empty($token)) {
        $entity = null;
        $drivers = read_csv(DRIVERS_CSV);
        foreach ($drivers as $d) {
            if (isset($d['pdf_token']) && $d['pdf_token'] === $token) {
                $entity = $d;
                break;
            }
        }
        if (!$entity) {
            $trucks = read_csv(TRUCKS_CSV);
            foreach ($trucks as $t) {
                if (isset($t['pdf_token']) && $t['pdf_token'] === $token) {
                    $entity = $t;
                    break;
                }
            }
        }

        if ($entity && !empty($entity['documents_pdf'])) {
            $filename = basename($entity['documents_pdf']);
            $filepath = UPLOAD_DIR . '/' . $filename;
            if (file_exists($filepath)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . htmlspecialchars($filename) . '"');
                header('Content-Length: ' . filesize($filepath));
                header('Cache-Control: private, max-age=86400');
                readfile($filepath);
                exit;
            }
        }
    }
    http_response_code(404);
    echo "Error: Requested document was not found or token is invalid.";
    exit;
}

// Secure Dynamic PDF Streaming Controller (for legacy or direct admin links)
if (isset($_GET['file'])) {
    $filename = trim($_GET['file']);
    
    // Mitigate directory traversal by forcing filename basename
    $filename = basename($filename);
    $filepath = UPLOAD_DIR . '/' . $filename;

    if (empty($filename) || !file_exists($filepath)) {
        http_response_code(404);
        echo "Error: Requested document was not found.";
        exit;
    }

    // Verify file extension is PDF
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        http_response_code(403);
        echo "Error: Forbidden file type access.";
        exit;
    }

    // Double-check MIME Type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);

    if ($mime !== 'application/pdf') {
        http_response_code(403);
        echo "Error: File is not a valid PDF.";
        exit;
    }

    // Stream PDF directly to browser safely
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . htmlspecialchars($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private, max-age=86400');
    
    // Output content securely using readfile
    readfile($filepath);
    exit;
}

// Render Public Profile Verification Details
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

$entity = null;
$entity_title = '';
$metrics = [];

if ($type === 'drivers' && !empty($id)) {
    $entity = get_by_id(DRIVERS_CSV, $id, 'id');
    if ($entity) {
        $entity_title = 'Driver Employee Verification';
        $metrics = [
            'CNIC' => isset($entity['cnic_expiry']) ? $entity['cnic_expiry'] : '',
            'Commercial Driver License' => isset($entity['license_expiry']) ? $entity['license_expiry'] : '',
            'Police Verification' => isset($entity['police_verification_expiry']) ? $entity['police_verification_expiry'] : '',
            'Drug Test' => isset($entity['drug_test_expiry']) ? $entity['drug_test_expiry'] : '',
            'Vitamin A Test' => isset($entity['vitamin_a_expiry']) ? $entity['vitamin_a_expiry'] : '',
            'Medical Fitness Certificate' => isset($entity['medical_fitness_expiry']) ? $entity['medical_fitness_expiry'] : '',
            'Defensive Driving Training' => isset($entity['defensive_driving_expiry']) ? $entity['defensive_driving_expiry'] : ''
        ];
    }
} elseif ($type === 'trucks' && !empty($id)) {
    $entity = get_by_id(TRUCKS_CSV, $id, 'id');
    if ($entity) {
        $entity_title = 'Fleet Asset Verification';
        $metrics = [
            'Route Permit' => isset($entity['route_permit_expiry']) ? $entity['route_permit_expiry'] : '',
            'Vehicle Inspection' => isset($entity['inspection_expiry']) ? $entity['inspection_expiry'] : '',
            'Token' => isset($entity['token_expiry']) ? $entity['token_expiry'] : ''
        ];
    }
}

// Compute general status
$overall_status = 'COMPLIANT';
$status_color = 'success';
$status_icon = 'bi-patch-check-fill';

if ($entity) {
    // If entity itself is Inactive, it is not compliant
    if (isset($entity['status']) && strcasecmp($entity['status'], 'Active') !== 0) {
        $overall_status = 'OUT OF SERVICE (INACTIVE)';
        $status_color = 'danger';
        $status_icon = 'bi-slash-circle-fill';
    } else {
        foreach ($metrics as $m_date) {
            if (empty($m_date)) continue;
            $urg = get_urgency_status($m_date);
            if ($urg['class'] === 'danger') {
                $overall_status = 'NON-COMPLIANT (EXPIRED DOCUMENT)';
                $status_color = 'danger';
                $status_icon = 'bi-x-octagon-fill';
                break;
            } elseif ($urg['class'] === 'warning') {
                $overall_status = 'COMPLIANT (WARNING: EXPIRING SOON)';
                $status_color = 'warning';
                $status_icon = 'bi-exclamation-triangle-fill';
            }
        }
    }
}

$system_title = get_setting('system_title', 'Logistics Document Management System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Compliance Gate - Logistics DMS</title>
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-dark);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }

        .verification-container {
            width: 100%;
            max-width: 580px;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            padding: 35px 25px;
        }

        .status-header {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .status-header.success {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        .status-header.warning {
            background-color: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }

        .status-header.danger {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .doc-row {
            background-color: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-stream {
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .logo-footer {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 30px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="verification-container">
    <div class="glass-card">
        <?php if (!$entity): ?>
            <!-- Error State: Not Found -->
            <div class="text-center py-5">
                <i class="bi bi-shield-slash-fill text-danger display-3 mb-3"></i>
                <h4 class="fw-bold">Invalid Verification Code</h4>
                <p class="text-secondary">The requested verification record link is invalid or has been deleted from the database registry.</p>
                <hr class="border-secondary border-opacity-25 my-4">
                <small class="text-muted"><?= sanitize($system_title) ?></small>
            </div>
        <?php else: ?>
            <!-- Found Verification Profile -->
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-1"><?= sanitize($entity_title) ?></h3>
                <p class="text-secondary small">Official System Certification Timestamp: <?= date('M d, Y H:i T') ?></p>
            </div>

            <!-- Compliance Urgency Banner -->
            <div class="status-header <?= $status_color ?>">
                <i class="bi <?= $status_icon ?> fs-2 mb-2 d-block"></i>
                <span class="d-block small text-uppercase fw-bold tracking-wider mb-1">Status Report</span>
                <h4 class="mb-0 fw-bold"><?= $overall_status ?></h4>
            </div>

            <!-- Profile Details Metadata -->
            <div class="mb-4">
                <h5 class="h6 text-secondary text-uppercase mb-3 fw-bold">Entity Information</h5>
                <div class="p-3 rounded border border-secondary border-opacity-10 bg-dark bg-opacity-20">
                    <?php if ($type === 'drivers'): ?>
                        <div class="row mb-2">
                            <div class="col-5 text-secondary">Driver Name:</div>
                            <div class="col-7 fw-bold text-white"><?= sanitize($entity['name']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-secondary">License Class:</div>
                            <div class="col-7 fw-bold text-white"><?= sanitize($entity['license_class']) ?></div>
                        </div>
                        <div class="row">
                            <div class="col-5 text-secondary">License Number:</div>
                            <div class="col-7 fw-bold text-white"><code><?= sanitize(substr($entity['license_number'], 0, 4)) ?>-****</code> <small class="text-muted">(Masked for Privacy)</small></div>
                        </div>
                    <?php else: ?>
                        <div class="row mb-2">
                            <div class="col-5 text-secondary">Unit Identifier:</div>
                            <div class="col-7 fw-bold text-white">Unit #<?= sanitize($entity['unit_number']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-secondary">License Plate:</div>
                            <div class="col-7 fw-bold text-white"><code><?= sanitize($entity['license_plate']) ?></code></div>
                        </div>
                        <div class="row">
                            <div class="col-5 text-secondary">Vehicle Make/Model:</div>
                            <div class="col-7 fw-bold text-white"><?= sanitize($entity['year']) ?> <?= sanitize($entity['make']) ?> <?= sanitize($entity['model']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Compliance Checklist and PDF Stream links -->
            <div>
                <h5 class="h6 text-secondary text-uppercase mb-3 fw-bold">Documentation Compliance Details</h5>
                <?php foreach ($metrics as $doc_label => $date_val): ?>
                    <?php 
                    $urgency = get_urgency_status($date_val); 
                    ?>
                    <div class="doc-row">
                        <div>
                            <div class="fw-bold small text-white"><?= sanitize($doc_label) ?></div>
                            <small class="text-secondary">
                                Expire: <?= !empty($date_val) ? date('M d, Y', strtotime($date_val)) : 'Not Set' ?>
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= $urgency['badge_class'] ?> rounded-pill px-2 py-1 small">
                                <?= sanitize($urgency['label']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($entity['documents_pdf'])): ?>
                    <div class="mt-4 text-center">
                        <a href="view.php?token=<?= urlencode($entity['pdf_token']) ?>" target="_blank" class="btn btn-outline-info w-100 py-2">
                            <i class="bi bi-file-pdf me-1"></i> Verify All Documents PDF
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mt-4 text-center">
                        <button class="btn btn-secondary w-100 py-2" disabled>No Documents PDF Uploaded</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="logo-footer">
                <i class="bi bi-shield-fill-check text-success"></i> Secure Verification Certificate powered by<br>
                <strong><?= sanitize($system_title) ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

