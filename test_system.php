<?php
/**
 * Logistics Document Management System - Automated Core Verification Test Suite
 * Asserts correctness of helper routines, database transactions, and date differentials.
 */

// Force CLI mode output headers if not already set
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "=============================================\n";
echo "LOGISTICS DMS: AUTOMATED TEST SUITE RUNNER\n";
echo "=============================================\n\n";

// 1. Load System configuration and files
require_once 'config.php';

$tests_failed = 0;

/**
 * Basic Assertion Helper
 */
function assert_test($description, $assertion) {
    global $tests_failed;
    if ($assertion) {
        echo "[ PASS ] $description\n";
    } else {
        echo "[ FAIL ] $description\n";
        $tests_failed++;
    }
}

// TEST 1: Directory initialization
echo "--- Testing Directory and Folder Configuration ---\n";
assert_test("data/ directory exists", is_dir(DATA_DIR));
assert_test("uploads/ directory exists", is_dir(UPLOAD_DIR));
assert_test("users.csv database initialized", file_exists(USERS_CSV));
assert_test("settings.csv database initialized", file_exists(SETTINGS_CSV));
assert_test("drivers.csv database initialized", file_exists(DRIVERS_CSV));
assert_test("trucks.csv database initialized", file_exists(TRUCKS_CSV));
echo "\n";


// TEST 2: Expiration Date Classification Engine
echo "--- Testing Compliance Date Classification Engine ---\n";
$today = date('Y-m-d');
$past_date = date('Y-m-d', strtotime('-5 days'));
$approaching_date = date('Y-m-d', strtotime('+15 days'));
$future_date = date('Y-m-d', strtotime('+60 days'));

$status_past = get_urgency_status($past_date);
assert_test("Expired Date check returns 'danger' (Red)", $status_past['class'] === 'danger');
assert_test("Expired Date check returns label 'Expired'", $status_past['label'] === 'Expired');

$status_approaching = get_urgency_status($approaching_date);
assert_test("Approaching Date check returns 'warning' (Yellow)", $status_approaching['class'] === 'warning');
assert_test("Approaching Date label indicates number of days", strpos($status_approaching['label'], 'Days Left') !== false);

$status_future = get_urgency_status($future_date);
assert_test("Safe Date check returns 'success' (Green)", $status_future['class'] === 'success');
assert_test("Safe Date check returns label 'Safe'", $status_future['label'] === 'Safe');
echo "\n";


// TEST 3: Secure CSV flat-file Transaction operations
echo "--- Testing Transactional CSV DB Operations ---\n";
// Create Mock Driver
$mock_id = 'test_drv_' . uniqid();
$mock_driver = [
    'id' => $mock_id,
    'name' => 'Tester Unit Driver',
    'license_number' => 'TEST-LICENSE-123',
    'license_class' => 'Class A',
    'phone' => '1234567890',
    'email' => 'test@test.com',
    'license_expiry' => $future_date,
    'medical_expiry' => $approaching_date,
    'drug_screen_expiry' => $past_date,
    'license_pdf' => '',
    'medical_pdf' => '',
    'drug_screen_pdf' => '',
    'status' => 'Active'
];

// Write mock
$write_ok = save_row(DRIVERS_CSV, $mock_driver, 'id');
assert_test("CSV save_row inserts a new record", $write_ok);

// Read Mock back
$retrieved = get_by_id(DRIVERS_CSV, $mock_id, 'id');
assert_test("CSV get_by_id returns matching row details", $retrieved !== null && $retrieved['name'] === 'Tester Unit Driver');
assert_test("CSV parsed fields map correctly to database layout", $retrieved['license_number'] === 'TEST-LICENSE-123');

// Modify Mock
$retrieved['name'] = 'Tester Unit Driver Edited';
$update_ok = save_row(DRIVERS_CSV, $retrieved, 'id');
assert_test("CSV save_row performs update on existing rows by ID", $update_ok);

$retrieved_edited = get_by_id(DRIVERS_CSV, $mock_id, 'id');
assert_test("CSV get_by_id retrieves modified row name correctly", $retrieved_edited !== null && $retrieved_edited['name'] === 'Tester Unit Driver Edited');

// Delete Mock
$delete_ok = delete_row(DRIVERS_CSV, $mock_id, 'id');
assert_test("CSV delete_row returns success", $delete_ok);

$retrieved_deleted = get_by_id(DRIVERS_CSV, $mock_id, 'id');
assert_test("CSV get_by_id returns null after row is deleted", $retrieved_deleted === null);
echo "\n";


// TEST 4: Settings Key-Value helpers
echo "--- Testing Settings Key-Value Store Helpers ---\n";
$original_title = get_setting('system_title', 'Logistics Document Management System');
$test_title = 'Antigravity Verified LMS ' . rand(100,999);

save_setting('system_title', $test_title);
$new_title = get_setting('system_title');
assert_test("Settings save_setting modifies value", $new_title === $test_title);

// Revert
save_setting('system_title', $original_title);
$reverted_title = get_setting('system_title');
assert_test("Settings revert restores original value", $reverted_title === $original_title);
echo "\n";


echo "=============================================\n";
if ($tests_failed === 0) {
    echo "TEST STATUS: ALL TESTS PASSED SUCCESSFULLY!\n";
} else {
    echo "TEST STATUS: $tests_failed TESTS FAILED. CHECK DETAILS ABOVE.\n";
}
echo "=============================================\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
?>
