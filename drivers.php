<?php
require_once 'config.php';
check_auth();

$success = '';
$error = '';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_id = isset($_GET['id']) ? trim($_GET['id']) : '';

// 1. Process Actions (Delete, Add, Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'save') {
        $id = isset($_POST['id']) ? trim($_POST['id']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $license_number = isset($_POST['license_number']) ? trim($_POST['license_number']) : '';
        $license_class = isset($_POST['license_class']) ? trim($_POST['license_class']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        $cnic_expiry = isset($_POST['cnic_expiry']) ? trim($_POST['cnic_expiry']) : '';
        $license_expiry = isset($_POST['license_expiry']) ? trim($_POST['license_expiry']) : '';
        $police_verification_expiry = isset($_POST['police_verification_expiry']) ? trim($_POST['police_verification_expiry']) : '';
        $drug_test_expiry = isset($_POST['drug_test_expiry']) ? trim($_POST['drug_test_expiry']) : '';
        $vitamin_a_expiry = isset($_POST['vitamin_a_expiry']) ? trim($_POST['vitamin_a_expiry']) : '';
        $medical_fitness_expiry = isset($_POST['medical_fitness_expiry']) ? trim($_POST['medical_fitness_expiry']) : '';
        $defensive_driving_expiry = isset($_POST['defensive_driving_expiry']) ? trim($_POST['defensive_driving_expiry']) : '';
        
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';

        if (empty($name) || empty($license_number)) {
            $error = 'Driver Name and License Number are required.';
        } else {
            // Is it new or update?
            $is_new = empty($id);
            if ($is_new) {
                $id = 'drv_' . bin2hex(random_bytes(6));
                $created_at = date('Y-m-d H:i:s');
                $pdf_token = bin2hex(random_bytes(16));
            } else {
                $existing = get_by_id(DRIVERS_CSV, $id);
                $created_at = isset($existing['created_at']) ? $existing['created_at'] : date('Y-m-d H:i:s');
                $pdf_token = !empty($existing['pdf_token']) ? $existing['pdf_token'] : bin2hex(random_bytes(16));
            }

            // Load current file names
            $documents_pdf = !$is_new && isset($existing['documents_pdf']) ? $existing['documents_pdf'] : '';

            // Handle PDF File Uploads
            $upload_err = false;
            if (isset($_FILES['documents_pdf_file']) && $_FILES['documents_pdf_file']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['documents_pdf_file']['tmp_name'];
                $original_name = $_FILES['documents_pdf_file']['name'];
                $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                // Verify extension is PDF
                if ($extension !== 'pdf') {
                    $error = 'File upload failed. Only PDF files are allowed.';
                    $upload_err = true;
                } else {
                    // Verify MIME Type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $tmp_name);
                    finfo_close($finfo);

                    if ($mime !== 'application/pdf') {
                        $error = 'File verification failed. Uploaded document is not a valid PDF.';
                        $upload_err = true;
                    } else {
                        // Delete existing PDF if updating
                        if (!empty($documents_pdf)) {
                            $old_path = UPLOAD_DIR . '/' . $documents_pdf;
                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }

                        // Define clean ID-based string filename
                        $new_filename = $id . '_documents_' . time() . '.pdf';
                        if (move_uploaded_file($tmp_name, UPLOAD_DIR . '/' . $new_filename)) {
                            $documents_pdf = $new_filename;
                        } else {
                            $error = 'Failed to save uploaded document.';
                            $upload_err = true;
                        }
                    }
                }
            }

            if (!$upload_err) {
                $driver_data = [
                    'id' => $id,
                    'name' => $name,
                    'license_number' => $license_number,
                    'license_class' => $license_class,
                    'phone' => $phone,
                    'email' => $email,
                    'cnic_expiry' => $cnic_expiry,
                    'license_expiry' => $license_expiry,
                    'police_verification_expiry' => $police_verification_expiry,
                    'drug_test_expiry' => $drug_test_expiry,
                    'vitamin_a_expiry' => $vitamin_a_expiry,
                    'medical_fitness_expiry' => $medical_fitness_expiry,
                    'defensive_driving_expiry' => $defensive_driving_expiry,
                    'documents_pdf' => $documents_pdf,
                    'pdf_token' => $pdf_token,
                    'status' => $status,
                    'created_at' => $created_at
                ];

                if (save_row(DRIVERS_CSV, $driver_data, 'id')) {
                    $success = 'Driver record saved successfully.';
                    $action = 'list';
                } else {
                    $error = 'Failed to write driver record to storage.';
                }
            }
        }
    }
}

if ($action === 'delete' && !empty($edit_id)) {
    if (delete_row(DRIVERS_CSV, $edit_id, 'id')) {
        $success = 'Driver record deleted successfully.';
    } else {
        $error = 'Failed to delete driver record.';
    }
    $action = 'list';
}

// 2. Fetch records
$drivers = read_csv(DRIVERS_CSV);
$edit_driver = null;
if (($action === 'edit' || $action === 'view') && !empty($edit_id)) {
    $edit_driver = get_by_id(DRIVERS_CSV, $edit_id, 'id');
    if (!$edit_driver) {
        $error = 'Driver record not found.';
        $action = 'list';
    }
}

$system_title = get_setting('system_title', 'Logistics Document Management System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers Registry - Logistics DMS</title>
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
        }

        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            padding: 10px 14px;
            border-radius: 8px;
        }

        .form-control:focus, .form-select:focus {
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

        .highlight-row {
            background-color: rgba(99, 102, 241, 0.12) !important;
            border-left: 4px solid #6366f1;
        }

        /* Printable QR Style */
        @media print {
            body * {
                visibility: hidden;
            }
            #printable-qr-area, #printable-qr-area * {
                visibility: visible;
            }
            #printable-qr-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                text-align: center;
            }
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
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="drivers.php"><i class="bi bi-people me-1"></i> Drivers</a>
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
    <!-- Success/Error Banners -->
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

    <?php if ($action === 'list'): ?>
        <!-- DRIVERS TABLE LIST VIEW -->
        <div class="row mb-4 align-items-center">
            <div class="col-sm-6">
                <h2 class="h3 font-weight-bold">Driver Employees Directory</h2>
                <p class="text-secondary">Track, update, and audit commercial drivers status and documents expiration dates.</p>
            </div>
            <div class="col-sm-6 text-sm-end">
                <a href="drivers.php?action=add" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Register New Driver
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Driver Profile</th>
                                    <th>License Number</th>
                                    <th>Phone / Email</th>
                                    <th>Compliance Link</th>
                                    <th>Documents PDF</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($drivers)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-secondary">
                                            <i class="bi bi-people display-4 mb-3 d-block text-muted"></i>
                                            <h5>No Drivers Registered</h5>
                                            <p class="mb-0">Click the button above to add your first commercial driver.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($drivers as $row): ?>
                                        <?php 
                                        $highlight = (isset($_GET['id']) && $_GET['id'] === $row['id']) ? 'highlight-row' : '';
                                        $pdf_url = !empty($row['documents_pdf']) ? 'view.php?token=' . urlencode($row['pdf_token']) : '';
                                        $verification_url = 'view.php?type=drivers&id=' . urlencode($row['id']);
                                        ?>
                                        <tr class="<?= $highlight ?>" id="row_<?= $row['id'] ?>">
                                            <td>
                                                <div class="fw-bold text-white"><?= sanitize($row['name']) ?></div>
                                                <small class="text-secondary">Class: <?= sanitize($row['license_class']) ?></small>
                                            </td>
                                            <td><code><?= sanitize($row['license_number']) ?></code></td>
                                            <td>
                                                <div><?= sanitize($row['phone']) ?></div>
                                                <small class="text-secondary"><?= sanitize($row['email']) ?></small>
                                            </td>
                                            <td>
                                                <a href="<?= $verification_url ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-shield-check"></i> Public Gate
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (!empty($pdf_url)): ?>
                                                    <a href="<?= $pdf_url ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                                        <i class="bi bi-file-pdf"></i> View PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">No PDF</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= strcasecmp($row['status'], 'Active') === 0 ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?> px-2 py-1">
                                                    <?= sanitize($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-outline-light btn-sm me-1" onclick="showQrModal('<?= $row['id'] ?>', '<?= sanitize($row['name']) ?>')" title="View QR Verification Code">
                                                    <i class="bi bi-qr-code"></i>
                                                </button>
                                                <a href="drivers.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline-warning btn-sm me-1" title="Edit Profile">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="drivers.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete Profile" onclick="return confirm('Are you sure you want to delete this driver employee? This will also delete all uploaded documents.');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- ADD / EDIT FORM VIEW -->
        <div class="row mb-4 align-items-center">
            <div class="col-sm-6">
                <h2 class="h3 font-weight-bold"><?= $action === 'add' ? 'Register Commercial Driver' : 'Update Driver Profile' ?></h2>
                <p class="text-secondary"><?= $action === 'add' ? 'Configure a new driver employee profile with associated licensing and medical records.' : 'Edit existing metadata and manage compliance file attachments.' ?></p>
            </div>
            <div class="col-sm-6 text-sm-end">
                <a href="drivers.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Directory
                </a>
            </div>
        </div>

        <div class="glass-card">
            <form action="drivers.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="save">
                <input type="hidden" name="id" value="<?= $edit_driver ? sanitize($edit_driver['id']) : '' ?>">

                <div class="row">
                    <!-- Column 1: Core Metadata -->
                    <div class="col-md-6 border-end border-secondary border-opacity-25 pe-md-4">
                        <h4 class="h5 mb-4 text-primary"><i class="bi bi-person-badge me-2"></i> Employee Details</h4>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label text-secondary">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= $edit_driver ? sanitize($edit_driver['name']) : '' ?>" placeholder="e.g. John Doe" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="license_number" class="form-label text-secondary">CDL Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number" value="<?= $edit_driver ? sanitize($edit_driver['license_number']) : '' ?>" placeholder="e.g. DL-1294812" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="license_class" class="form-label text-secondary">License Class</label>
                                <select class="form-select" id="license_class" name="license_class">
                                    <option value="Class A" <?= ($edit_driver && $edit_driver['license_class'] === 'Class A') ? 'selected' : '' ?>>Class A CDL (Tractor-Trailer)</option>
                                    <option value="Class B" <?= ($edit_driver && $edit_driver['license_class'] === 'Class B') ? 'selected' : '' ?>>Class B CDL (Straight Truck)</option>
                                    <option value="Class C" <?= ($edit_driver && $edit_driver['license_class'] === 'Class C') ? 'selected' : '' ?>>Class C CDL (Hazardous/Bus)</option>
                                    <option value="Non-CDL" <?= ($edit_driver && $edit_driver['license_class'] === 'Non-CDL') ? 'selected' : '' ?>>Non-CDL Driver</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label text-secondary">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= $edit_driver ? sanitize($edit_driver['phone']) : '' ?>" placeholder="e.g. +1 555-0199">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label text-secondary">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= $edit_driver ? sanitize($edit_driver['email']) : '' ?>" placeholder="e.g. jdoe@logistics.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label text-secondary">Registry Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Active" <?= ($edit_driver && strcasecmp($edit_driver['status'], 'Active') === 0) ? 'selected' : '' ?>>Active Employee</option>
                                <option value="Inactive" <?= ($edit_driver && strcasecmp($edit_driver['status'], 'Inactive') === 0) ? 'selected' : '' ?>>Inactive (Suspended/Resigned)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Column 2: Document Compliance and Expirations -->
                    <div class="col-md-6 ps-md-4 mt-4 mt-md-0">
                        <h4 class="h5 mb-4 text-primary"><i class="bi bi-file-earmark-check me-2"></i> Compliance Dates & PDF Upload</h4>

                        <!-- Combined PDF upload -->
                        <div class="mb-4 p-3 rounded bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                            <label for="documents_pdf_file" class="form-label text-secondary fw-bold">Combined Documents PDF (CNIC, License, Police, Medical, etc.)</label>
                            <input type="file" class="form-control" id="documents_pdf_file" name="documents_pdf_file" accept=".pdf">
                            <?php if ($edit_driver && !empty($edit_driver['documents_pdf'])): ?>
                                <div class="mt-2">
                                    <a href="view.php?token=<?= urlencode($edit_driver['pdf_token']) ?>" target="_blank" class="text-info text-decoration-none small">
                                        <i class="bi bi-file-pdf"></i> View Current Combined PDF
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cnic_expiry" class="form-label text-secondary">CNIC Expiry Date</label>
                                <input type="date" class="form-control" id="cnic_expiry" name="cnic_expiry" value="<?= $edit_driver ? sanitize($edit_driver['cnic_expiry']) : '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="license_expiry" class="form-label text-secondary">License Expiry Date</label>
                                <input type="date" class="form-control" id="license_expiry" name="license_expiry" value="<?= $edit_driver ? sanitize($edit_driver['license_expiry']) : '' ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="police_verification_expiry" class="form-label text-secondary">Police Verification Expiry</label>
                                <input type="date" class="form-control" id="police_verification_expiry" name="police_verification_expiry" value="<?= $edit_driver ? sanitize($edit_driver['police_verification_expiry']) : '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="drug_test_expiry" class="form-label text-secondary">Drug Test Expiry</label>
                                <input type="date" class="form-control" id="drug_test_expiry" name="drug_test_expiry" value="<?= $edit_driver ? sanitize($edit_driver['drug_test_expiry']) : '' ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vitamin_a_expiry" class="form-label text-secondary">Vitamin A Test Expiry</label>
                                <input type="date" class="form-control" id="vitamin_a_expiry" name="vitamin_a_expiry" value="<?= $edit_driver ? sanitize($edit_driver['vitamin_a_expiry']) : '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="medical_fitness_expiry" class="form-label text-secondary">Medical Fitness Expiry</label>
                                <input type="date" class="form-control" id="medical_fitness_expiry" name="medical_fitness_expiry" value="<?= $edit_driver ? sanitize($edit_driver['medical_fitness_expiry']) : '' ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="defensive_driving_expiry" class="form-label text-secondary">Defensive Driving Expiry</label>
                                <input type="date" class="form-control" id="defensive_driving_expiry" name="defensive_driving_expiry" value="<?= $edit_driver ? sanitize($edit_driver['defensive_driving_expiry']) : '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-25 my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="drivers.php" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5">
                        <i class="bi bi-check2-circle me-1"></i> Save Driver Profile
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- CLIENT SIDE QR MODAL (Bootstrap 5) -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true" style="color: #0f172a;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: #f8fafc; border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="qrModalTitle" style="color: #1e293b;"><i class="bi bi-qr-code-scan me-2 text-primary"></i> Verification QR Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4" id="printable-qr-area">
                <h6 class="fw-bold mb-1" id="qrDriverName" style="color: #1e293b;">-</h6>
                <p class="text-muted small mb-4">Static Verification QR. Scan to open compliance verification page.</p>
                
                <!-- QR Container -->
                <div class="d-flex justify-content-center mb-4">
                    <div id="qrcode-container" class="p-3 bg-white border rounded shadow-sm">
                        <div id="qrcode"></div>
                    </div>
                </div>
                
                <code class="d-block text-break small text-muted px-3" id="qrLinkText">-</code>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="window.print();">
                    <i class="bi bi-printer me-1"></i> Print QR Code
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Client-side QRCode Generator Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    var qrGenerator = null;

    function showQrModal(driverId, driverName) {
        document.getElementById('qrDriverName').innerText = driverName;
        
        // Dynamically compute the absolute verification path
        var baseUrl = "<?= get_base_url() ?>";
        var verificationUrl = baseUrl + "/view.php?type=drivers&id=" + driverId;

        document.getElementById('qrLinkText').innerText = verificationUrl;

        // Clear previous QR Code if any
        document.getElementById("qrcode").innerHTML = "";

        // Generate QR code
        qrGenerator = new QRCode(document.getElementById("qrcode"), {
            text: verificationUrl,
            width: 200,
            height: 200,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });

        // Show Modal
        var myModal = new bootstrap.Modal(document.getElementById('qrModal'));
        myModal.show();
    }
</script>
</body>
</html>
