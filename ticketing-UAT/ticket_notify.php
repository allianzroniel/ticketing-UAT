<?php
// ticket_notify.php
// Polling endpoint for admin/super_admin desktop notifications.
// GET params:
//   last_id   = highest ticket ID the client already knows about
//   mode      = "notify" (default) | "campaign_data"
//
// mode=notify   → returns new tickets since last_id + maxId
// mode=campaign_data → returns full campaign breakdown for live refresh

require 'auth_check.php';
requireRole(['admin', 'super_admin']);
require 'db_config.php';
require 'site_config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

$mode   = $_GET['mode']    ?? 'notify';
$lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$currentSite = $_SESSION['site'] ?? '';
$isSiteScopedUser = $_SESSION['role'] !== 'super_admin';

$conn = getDBConnection();

if ($mode === 'campaign_data') {
    // Full campaign breakdown
    $campaignSql = "SELECT t.campaign, t.concern_type, t.status, COUNT(*) AS c
         FROM tickets t
         JOIN users u ON u.id = t.created_by";
    if ($isSiteScopedUser && $currentSite !== '') {
        $campaignSql .= " WHERE u.site = ?";
    }
    $campaignSql .= " GROUP BY t.campaign, t.concern_type, t.status ORDER BY t.campaign ASC";
    $stmt = $conn->prepare($campaignSql);
    if ($isSiteScopedUser && $currentSite !== '') {
        $stmt->bind_param('s', $currentSite);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $camp   = $row['campaign'];
        $type   = $row['concern_type'];
        $status = $row['status'];
        if (!isset($data[$camp])) {
            $data[$camp] = [
                'PRIO'     => ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0],
                'NON-PRIO' => ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0],
            ];
        }
        $data[$camp][$type][$status] = (int) $row['c'];
    }

    // Also get maxId for notification tracking
    $maxIdSql = "SELECT MAX(t.id) AS max_id FROM tickets t JOIN users u ON u.id = t.created_by";
    if ($isSiteScopedUser && $currentSite !== '') {
        $maxIdSql .= " WHERE u.site = ?";
    }
    $maxStmt = $conn->prepare($maxIdSql);
    if ($isSiteScopedUser && $currentSite !== '') {
        $maxStmt->bind_param('s', $currentSite);
    }
    $maxStmt->execute();
    $maxIdRes = $maxStmt->get_result();
    $maxId = (int) ($maxIdRes->fetch_assoc()['max_id'] ?? 0);
    $maxStmt->close();

    $stmt->close();
    $conn->close();
    echo json_encode(['campaigns' => $data, 'maxId' => $maxId]);
    exit;
}

// Default: notify mode
$stmt = $conn->prepare(
    "SELECT t.id, t.tnum, t.concern_type, t.campaign, t.reporter_name, t.concern, t.created_at, u.username AS submitted_by
     FROM tickets t
     JOIN users u ON u.id = t.created_by
     WHERE t.id > ?" . ($isSiteScopedUser && $currentSite !== '' ? " AND u.site = ?" : "") . "
     ORDER BY t.id ASC"
);
if ($isSiteScopedUser && $currentSite !== '') {
    $stmt->bind_param("is", $lastId, $currentSite);
} else {
    $stmt->bind_param("i", $lastId);
}
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];
while ($row = $result->fetch_assoc()) {
    $tickets[] = [
        'id'           => (int) $row['id'],
        'tnum'         => $row['tnum'] ?? ('TNUM-' . str_pad((int) $row['id'], 6, '0', STR_PAD_LEFT)),
        'concern_type' => $row['concern_type'],
        'campaign'     => $row['campaign'],
        'reporter'     => $row['reporter_name'],
        'concern'      => mb_strimwidth($row['concern'], 0, 80, '…'),
        'submitted_by' => $row['submitted_by'],
        'created_at'   => $row['created_at'],
    ];
}
$stmt->close();

$maxIdSql = "SELECT MAX(t.id) AS max_id FROM tickets t JOIN users u ON u.id = t.created_by";
if ($isSiteScopedUser && $currentSite !== '') {
    $maxIdSql .= " WHERE u.site = ?";
}
$maxStmt = $conn->prepare($maxIdSql);
if ($isSiteScopedUser && $currentSite !== '') {
    $maxStmt->bind_param('s', $currentSite);
}
$maxStmt->execute();
$maxIdRes = $maxStmt->get_result();
$maxId = (int) ($maxIdRes->fetch_assoc()['max_id'] ?? 0);
$maxStmt->close();
$conn->close();

echo json_encode([
    'newCount' => count($tickets),
    'maxId'    => $maxId,
    'tickets'  => $tickets,
]);
exit;
