<?php
/**
 * Logistics Document Management System - Configuration & Utilities
 * Principal Full-Stack Engineer Lightweight Secure PHP Architecture
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define Directory Paths
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('UPLOAD_DIR', BASE_DIR . '/uploads');

define('USERS_CSV', DATA_DIR . '/users.csv');
define('SETTINGS_CSV', DATA_DIR . '/settings.csv');
define('DRIVERS_CSV', DATA_DIR . '/drivers.csv');
define('TRUCKS_CSV', DATA_DIR . '/trucks.csv');

// Auto-create directories if they do not exist
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// System Seeding & Initialization
initialize_system();

/**
 * Initialize CSV databases with headers and default values if missing
 */
function initialize_system() {
    // 1. Users CSV
    if (!file_exists(USERS_CSV)) {
        $headers = ['username', 'password_hash', 'created_at'];
        // Default admin:admin123
        $default_admin = [
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];
        save_csv_transactional(USERS_CSV, [$default_admin], $headers);
    }

    // 2. Settings CSV
    if (!file_exists(SETTINGS_CSV)) {
        $headers = ['setting_key', 'setting_value'];
        $default_settings = [
            ['setting_key' => 'notification_email', 'setting_value' => 'admin@example.com'],
            ['setting_key' => 'system_title', 'setting_value' => 'Logistics Document Management System']
        ];
        save_csv_transactional(SETTINGS_CSV, $default_settings, $headers);
    }

    // 3. Drivers CSV
    if (!file_exists(DRIVERS_CSV)) {
        $headers = [
            'id', 'name', 'license_number', 'license_class', 'phone', 'email',
            'cnic_expiry', 'license_expiry', 'police_verification_expiry',
            'drug_test_expiry', 'vitamin_a_expiry', 'medical_fitness_expiry',
            'defensive_driving_expiry', 'documents_pdf', 'pdf_token', 'status', 'created_at'
        ];
        save_csv_transactional(DRIVERS_CSV, [], $headers);
    }

    // 4. Trucks CSV
    if (!file_exists(TRUCKS_CSV)) {
        $headers = [
            'id', 'unit_number', 'license_plate', 'make', 'model', 'year',
            'route_permit_expiry', 'inspection_expiry', 'token_expiry',
            'documents_pdf', 'pdf_token', 'status', 'created_at'
        ];
        save_csv_transactional(TRUCKS_CSV, [], $headers);
    }
}

/**
 * Read CSV file securely and map rows into associative arrays using the headers
 */
function read_csv($file_path) {
    if (!file_exists($file_path)) {
        return [];
    }

    $data = [];
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // Obtain a shared lock (read lock)
        flock($handle, LOCK_SH);

        $headers = fgetcsv($handle);
        if ($headers === FALSE || empty($headers)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return [];
        }

        // Clean headers of UTF-8 BOM if present
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        while (($row = fgetcsv($handle)) !== FALSE) {
            // Check if the row length matches headers
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            } else {
                // If mismatch, pad or truncate to prevent errors
                $padded_row = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
                $data[] = array_combine($headers, $padded_row);
            }
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }
    return $data;
}

/**
 * Write CSV file transactionally using a temp file and exclusive locks to prevent corruption
 */
function save_csv_transactional($file_path, $data, $headers) {
    $temp_path = $file_path . '.tmp';
    $dir = dirname($file_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (($handle = fopen($temp_path, "w")) !== FALSE) {
        // Obtain exclusive lock on the temp file (mostly procedural safety)
        flock($handle, LOCK_EX);

        // Write header
        fputcsv($handle, $headers);

        // Write rows
        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = isset($row[$header]) ? $row[$header] : '';
            }
            fputcsv($handle, $line);
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        // Rename temp file to target file (atomic replace in most OS environment configurations)
        if (file_exists($file_path)) {
            // On Windows, rename fails if destination exists, so we delete it first
            unlink($file_path);
        }
        return rename($temp_path, $file_path);
    }
    return false;
}

/**
 * Find record by ID
 */
function get_by_id($file_path, $id, $id_col = 'id') {
    $rows = read_csv($file_path);
    foreach ($rows as $row) {
        if (isset($row[$id_col]) && $row[$id_col] == $id) {
            return $row;
        }
    }
    return null;
}

/**
 * Save (Insert or Update) a row in a CSV file
 */
function save_row($file_path, $new_row_data, $id_col = 'id') {
    $rows = read_csv($file_path);
    $headers = [];
    
    // Determine headers
    if (!empty($rows)) {
        $headers = array_keys($rows[0]);
    } else {
        // Fallback or seed config if empty
        if ($file_path === DRIVERS_CSV) {
            $headers = ['id', 'name', 'license_number', 'license_class', 'phone', 'email', 'cnic_expiry', 'license_expiry', 'police_verification_expiry', 'drug_test_expiry', 'vitamin_a_expiry', 'medical_fitness_expiry', 'defensive_driving_expiry', 'documents_pdf', 'pdf_token', 'status', 'created_at'];
        } elseif ($file_path === TRUCKS_CSV) {
            $headers = ['id', 'unit_number', 'license_plate', 'make', 'model', 'year', 'route_permit_expiry', 'inspection_expiry', 'token_expiry', 'documents_pdf', 'pdf_token', 'status', 'created_at'];
        } elseif ($file_path === SETTINGS_CSV) {
            $headers = ['setting_key', 'setting_value'];
        } elseif ($file_path === USERS_CSV) {
            $headers = ['username', 'password_hash', 'created_at'];
        }
    }

    $updated = false;
    foreach ($rows as $index => $row) {
        if (isset($row[$id_col]) && $row[$id_col] == $new_row_data[$id_col]) {
            // Keep unchanged columns
            $rows[$index] = array_merge($row, $new_row_data);
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        // Ensure ID is generated if not provided
        if (!isset($new_row_data[$id_col]) || empty($new_row_data[$id_col])) {
            $new_row_data[$id_col] = uniqid('ld_', true);
        }
        if (!isset($new_row_data['created_at']) && in_array('created_at', $headers)) {
            $new_row_data['created_at'] = date('Y-m-d H:i:s');
        }
        $rows[] = $new_row_data;
    }

    return save_csv_transactional($file_path, $rows, $headers);
}

/**
 * Delete a row by ID
 */
function delete_row($file_path, $id, $id_col = 'id') {
    $rows = read_csv($file_path);
    if (empty($rows)) {
        return false;
    }
    
    $headers = array_keys($rows[0]);
    $new_rows = [];
    $found = false;

    foreach ($rows as $row) {
        if (isset($row[$id_col]) && $row[$id_col] == $id) {
            $found = true;
            // Clean files associated if deleting driver/truck
            cleanup_associated_files($row);
            continue;
        }
        $new_rows[] = $row;
    }

    if ($found) {
        return save_csv_transactional($file_path, $new_rows, $headers);
    }
    return false;
}

/**
 * Cleanup PDFs when a record is deleted
 */
function cleanup_associated_files($row) {
    $pdf_fields = ['documents_pdf'];
    foreach ($pdf_fields as $field) {
        if (!empty($row[$field])) {
            $file_path = UPLOAD_DIR . '/' . $row[$field];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
}

/**
 * Get system setting
 */
function get_setting($key, $default = '') {
    $settings = read_csv(SETTINGS_CSV);
    foreach ($settings as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting['setting_value'];
        }
    }
    return $default;
}

/**
 * Save system setting
 */
function save_setting($key, $value) {
    $settings = read_csv(SETTINGS_CSV);
    $headers = ['setting_key', 'setting_value'];
    $found = false;
    foreach ($settings as $index => $setting) {
        if ($setting['setting_key'] === $key) {
            $settings[$index]['setting_value'] = $value;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $settings[] = ['setting_key' => $key, 'setting_value' => $value];
    }
    return save_csv_transactional(SETTINGS_CSV, $settings, $headers);
}

/**
 * Compare dates and return urgency array: class (bootstrap color) and urgency label
 */
function get_urgency_status($expiry_date_str) {
    if (empty($expiry_date_str)) {
        return [
            'class' => 'secondary',
            'label' => 'No Date Set',
            'days' => 9999,
            'badge_class' => 'bg-secondary'
        ];
    }

    $expiry = strtotime($expiry_date_str);
    $today = strtotime(date('Y-m-d'));
    
    // Calculate the difference in days
    $diff_seconds = $expiry - $today;
    $diff_days = round($diff_seconds / (60 * 60 * 24));

    if ($diff_days <= 0) {
        return [
            'class' => 'danger',
            'label' => 'Expired',
            'days' => $diff_days,
            'badge_class' => 'bg-danger text-white'
        ];
    } elseif ($diff_days <= 30) {
        return [
            'class' => 'warning',
            'label' => $diff_days . ' Days Left',
            'days' => $diff_days,
            'badge_class' => 'bg-warning text-dark'
        ];
    } else {
        return [
            'class' => 'success',
            'label' => 'Safe',
            'days' => $diff_days,
            'badge_class' => 'bg-success text-white'
        ];
    }
}

/**
 * Gate pages forcing login. Redirect to index.php if not logged in.
 */
function check_auth() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Clean user inputs for secure output rendering
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Determine base URL dynamically
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $domainName = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Get subfolders if any
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $dir = dirname($scriptName);
    
    // Clean up directory slashes
    $dir = str_replace('\\', '/', $dir);
    if ($dir === '/') {
        $dir = '';
    }
    
    return $protocol . $domainName . $dir;
}
?>
