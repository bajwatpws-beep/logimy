<?php
/**
 * Logistics Document Management System - Automated Expiration Notifications
 * Can be run via CLI cron (e.g. php cron_notify.php) or HTTP request
 */

require_once 'config.php';

// Check if running in CLI or Web
$is_cli = (php_sapi_name() === 'cli');

// 1. Gather all Expired and Warning documents
$alert_records = [];

// Parse Drivers
$drivers = read_csv(DRIVERS_CSV);
foreach ($drivers as $d) {
    if (isset($d['status']) && strcasecmp($d['status'], 'Active') !== 0) {
        continue; // Skip inactive profiles
    }

    $expiries = [
        'CNIC' => isset($d['cnic_expiry']) ? $d['cnic_expiry'] : '',
        'Commercial Driver License' => isset($d['license_expiry']) ? $d['license_expiry'] : '',
        'Police Verification' => isset($d['police_verification_expiry']) ? $d['police_verification_expiry'] : '',
        'Drug Test' => isset($d['drug_test_expiry']) ? $d['drug_test_expiry'] : '',
        'Vitamin A Test' => isset($d['vitamin_a_expiry']) ? $d['vitamin_a_expiry'] : '',
        'Medical Fitness Certificate' => isset($d['medical_fitness_expiry']) ? $d['medical_fitness_expiry'] : '',
        'Defensive Driving Training' => isset($d['defensive_driving_expiry']) ? $d['defensive_driving_expiry'] : ''
    ];

    foreach ($expiries as $doc_name => $date_val) {
        if (empty($date_val)) continue;

        $urgency = get_urgency_status($date_val);
        if ($urgency['class'] === 'danger' || $urgency['class'] === 'warning') {
            $alert_records[] = [
                'type' => 'Driver',
                'name' => $d['name'],
                'doc' => $doc_name,
                'date' => $date_val,
                'days' => $urgency['days'],
                'label' => $urgency['label'],
                'color' => $urgency['class'] === 'danger' ? '#ef4444' : '#f59e0b',
                'bg' => $urgency['class'] === 'danger' ? '#fee2e2' : '#fef3c7'
            ];
        }
    }
}

// Parse Trucks
$trucks = read_csv(TRUCKS_CSV);
foreach ($trucks as $t) {
    if (isset($t['status']) && strcasecmp($t['status'], 'Active') !== 0) {
        continue; // Skip inactive assets
    }

    $expiries = [
        'Route Permit' => isset($t['route_permit_expiry']) ? $t['route_permit_expiry'] : '',
        'Vehicle Inspection' => isset($t['inspection_expiry']) ? $t['inspection_expiry'] : '',
        'Token' => isset($t['token_expiry']) ? $t['token_expiry'] : ''
    ];

    foreach ($expiries as $doc_name => $date_val) {
        if (empty($date_val)) continue;

        $urgency = get_urgency_status($date_val);
        if ($urgency['class'] === 'danger' || $urgency['class'] === 'warning') {
            $alert_records[] = [
                'type' => 'Fleet Asset',
                'name' => 'Unit #' . $t['unit_number'] . ' (' . $t['license_plate'] . ')',
                'doc' => $doc_name,
                'date' => $date_val,
                'days' => $urgency['days'],
                'label' => $urgency['label'],
                'color' => $urgency['class'] === 'danger' ? '#ef4444' : '#f59e0b',
                'bg' => $urgency['class'] === 'danger' ? '#fee2e2' : '#fef3c7'
            ];
        }
    }
}

// Sort: Expired first
usort($alert_records, function($a, $b) {
    return $a['days'] <=> $b['days'];
});

$recipient = get_setting('notification_email', 'admin@example.com');
$system_title = get_setting('system_title', 'Logistics Document Management System');

$total_alerts = count($alert_records);

if ($total_alerts === 0) {
    $summary = "Checked all active records. No expired or expiring documents found. Notification skipped.\n";
    if ($is_cli) {
        echo $summary;
    } else {
        echo "<h3>Logistics DMS Cron Run</h3><p>" . htmlspecialchars($summary) . "</p>";
    }
    exit;
}

// 2. Build HTML Table Body
$rows_html = '';
foreach ($alert_records as $rec) {
    $days_str = '';
    if ($rec['days'] < 0) {
        $days_str = abs($rec['days']) . ' Days Overdue';
    } elseif ($rec['days'] === 0) {
        $days_str = 'Expires Today';
    } else {
        $days_str = $rec['days'] . ' Days Left';
    }

    $rows_html .= "
    <tr style='border-bottom: 1px solid #e2e8f0;'>
        <td style='padding: 12px; font-size: 14px; color: #475569;'><strong>" . htmlspecialchars($rec['type']) . "</strong></td>
        <td style='padding: 12px; font-size: 14px; color: #1e293b; font-weight: bold;'>" . htmlspecialchars($rec['name']) . "</td>
        <td style='padding: 12px; font-size: 14px; color: #475569;'>" . htmlspecialchars($rec['doc']) . "</td>
        <td style='padding: 12px; font-size: 14px; color: #64748b;'>" . date('M d, Y', strtotime($rec['date'])) . "</td>
        <td style='padding: 12px; font-size: 14px; color: " . $rec['color'] . "; font-weight: bold;'>" . $days_str . "</td>
        <td style='padding: 12px; text-align: center;'>
            <span style='background-color: " . $rec['bg'] . "; color: " . $rec['color'] . "; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;'>
                " . htmlspecialchars($rec['label']) . "
            </span>
        </td>
    </tr>";
}

// 3. Formulate Full HTML Mail Body
$mail_body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Daily Compliance Alert</title>
</head>
<body style='font-family: Arial, sans-serif; background-color: #f1f5f9; padding: 20px; margin: 0; -webkit-font-smoothing: antialiased;'>
    <div style='max-width: 680px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;'>
        
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); padding: 30px; text-align: center; color: #ffffff;'>
            <h2 style='margin: 0; font-size: 24px; font-weight: bold; letter-spacing: -0.5px;'>" . htmlspecialchars($system_title) . "</h2>
            <p style='margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;'>Automated Expiration Alert Service</p>
        </div>
        
        <!-- Content Body -->
        <div style='padding: 30px;'>
            <h3 style='color: #0f172a; margin-top: 0;'>Compliance Expiration Digest</h3>
            <p style='color: #475569; font-size: 15px; line-height: 1.5;'>
                The logistics records check-engine found <strong>{$total_alerts}</strong> active profile items that are currently expired or approaching expiration thresholds. Please login to the dashboard portal to update details or audit files.
            </p>
            
            <!-- Table -->
            <div style='overflow-x: auto; margin: 25px 0;'>
                <table style='width: 100%; border-collapse: collapse; text-align: left;'>
                    <thead>
                        <tr style='background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;'>
                            <th style='padding: 12px; font-size: 13px; text-transform: uppercase; color: #64748b;'>Category</th>
                            <th style='padding: 12px; font-size: 13px; text-transform: uppercase; color: #64748b;'>Identifier / Name</th>
                            <th style='padding: 12px; font-size: 13px; text-transform: uppercase; color: #64748b;'>Document Type</th>
                            <th style='padding: 12px; font-size: 13px; text-transform: uppercase; color: #64748b;'>Expiry Date</th>
                            <th style='padding: 12px; font-size: 13px; text-transform: uppercase; color: #64748b;'>Remaining</th>
                            <th style='padding: 12px; font-size: 13px; text-transform: uppercase; color: #64748b; text-align: center;'>State</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rows_html}
                    </tbody>
                </table>
            </div>
            
            <div style='text-align: center; margin-top: 30px;'>
                <a href='" . get_base_url() . "/dashboard.php' style='background-color: #4f46e5; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-block;'>
                    Go to Administration Dashboard
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style='background-color: #f8fafc; padding: 20px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8;'>
            This email was automatically generated. Please do not reply directly to this message. <br>
            Checking executed on " . date('Y-m-d H:i:s T') . "
        </div>
    </div>
</body>
</html>
";

// 4. Send the Email
$subject = "Compliance Alert: {$total_alerts} Action Required Expirations - " . $system_title;

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Logistics Compliance Service <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$mail_sent = mail($recipient, $subject, $mail_body, $headers);

// 5. Output Summary
if ($mail_sent) {
    $summary = "Checked all active records. Identified {$total_alerts} warnings/expirations. Digest email successfully sent to: {$recipient}\n";
} else {
    $summary = "Checked all active records. Identified {$total_alerts} warnings/expirations. PHP mail() invocation returned FALSE. Verify local host mail setup configurations.\n";
}

if ($is_cli) {
    echo $summary;
} else {
    echo "<h3>Logistics DMS Cron Run</h3><p>" . nl2br(htmlspecialchars($summary)) . "</p>";
}
?>
