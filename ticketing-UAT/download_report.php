<?php
require 'auth_check.php';
requireRole(['admin', 'super_admin']);
require 'db_config.php';
require 'site_config.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

// Basic validation of date format
function isValidDate($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

if (!isValidDate($dateFrom) || !isValidDate($dateTo)) {
    header('Location: admin_dashboard.php');
    exit;
}

// Make the "to" date inclusive of the whole day
$dateToInclusive = $dateTo . ' 23:59:59';
$dateFromInclusive = $dateFrom . ' 00:00:00';

$conn = getDBConnection();
$currentSite = $_SESSION['site'] ?? '';
$isSiteScopedUser = $_SESSION['role'] !== 'super_admin';

$sql = "SELECT t.id, t.concern_type, t.concern_datetime, t.room_ws, t.reporter_name, t.tl_name, t.campaign,
            t.concern, t.troubleshooting, t.status, t.remarks,
            u1.username AS created_by, u2.username AS acknowledged_by, u3.username AS resolved_by,
            t.created_at, t.acknowledged_at, t.resolved_at
     FROM tickets t
     JOIN users u1 ON u1.id = t.created_by
     LEFT JOIN users u2 ON u2.id = t.acknowledged_by
     LEFT JOIN users u3 ON u3.id = t.resolved_by
     WHERE t.created_at BETWEEN ? AND ?";
if ($isSiteScopedUser && $currentSite !== '') {
    $sql .= " AND u1.site = ?";
}
$sql .= " ORDER BY t.created_at ASC";

$stmt = $conn->prepare($sql);
if ($isSiteScopedUser && $currentSite !== '') {
    $stmt->bind_param("sss", $dateFromInclusive, $dateToInclusive, $currentSite);
} else {
    $stmt->bind_param("ss", $dateFromInclusive, $dateToInclusive);
}
$stmt->execute();
$result = $stmt->get_result();

// Output CSV
$filename = "{$dateFrom}-{$dateTo}-Consolidated concern.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, [
    'Ticket ID', 'Type of Concern', 'Date & Time', 'Room & WS #', 'Name', 'TL',
    'Campaign', 'Concern', 'Troubleshooting Made by POC', 'Status', 'Remarks',
    'Submitted By', 'Acknowledged By', 'Resolved By',
    'Time Created', 'Time Acknowledged', 'Time Resolved'
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['concern_type'],
        $row['concern_datetime'],
        $row['room_ws'],
        $row['reporter_name'],
        $row['tl_name'],
        $row['campaign'],
        $row['concern'],
        $row['troubleshooting'],
        $row['status'],
        $row['remarks'],
        $row['created_by'],
        $row['acknowledged_by'] ?? '',
        $row['resolved_by'] ?? '',
        $row['created_at'],
        $row['acknowledged_at'] ?? '',
        $row['resolved_at'] ?? '',
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
exit;
