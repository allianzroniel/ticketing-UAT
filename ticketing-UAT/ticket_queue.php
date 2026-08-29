<?php
require 'auth_check.php';
requireRole(['user', 'admin', 'super_admin']);
require 'db_config.php';

$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT t.id, t.tnum, t.concern_type, t.concern_datetime, t.room_ws, t.campaign, t.concern,
            t.status, t.created_at,
            (SELECT COUNT(*) FROM tickets x
             WHERE x.status = 'Open'
               AND (
                     (t.concern_type = 'PRIO' AND x.concern_type = 'PRIO' AND x.created_at < t.created_at)
                     OR
                     (t.concern_type = 'NON-PRIO' AND (x.concern_type = 'PRIO' OR (x.concern_type = 'NON-PRIO' AND x.created_at < t.created_at)))
                   )
            ) AS open_ahead
     FROM tickets t
     WHERE t.created_by = ?
     ORDER BY t.created_at DESC"
);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$tickets = $stmt->get_result();
$stmt->close();
$conn->close();

function statusBadge($status) {
    $map = [
        'Open' => 'badge-open',
        'In Progress' => 'badge-progress',
        'Resolved' => 'badge-resolved',
    ];
    $class = $map[$status] ?? 'badge-open';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="assets/js/page-cache.js"></script>
    <title>My Ticket Queue — SyncDesk</title>
    <style>
        main { max-width: 1100px; margin: 0 auto; padding: 40px; }
        .page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-head h1 { font-size: 26px; }
        .page-head p { color: var(--text-dim); font-size: 14px; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        th, td { padding: 12px 16px; text-align: left; font-size: 14px; border-bottom: 1px solid var(--line); }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(245,158,11,0.05); }

        .badge-open { background: rgba(245,158,11,0.18); color: var(--accent); }
        .badge-progress { background: rgba(96,165,250,0.18); color: #60A5FA; }
        .badge-resolved { background: rgba(74,222,128,0.18); color: #4ADE80; }

        .empty { text-align: center; padding: 60px 20px; color: var(--text-dim); }
        .note { margin-bottom: 18px; color: var(--text-dim); font-size: 14px; }

        @media (max-width: 720px) {
            main { padding: 20px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">SyncDesk<span>.</span></a>
        <nav>
            <span>Signed in as <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <a href="user_dashboard.php" class="btn btn-ghost">My Dashboard</a>
            <a href="logout.php" class="btn btn-ghost">Log out</a>
        </nav>
    </header>

    <main>
        <div class="page-head">
            <div>
                <h1>My Ticket Queue</h1>
                <p>See all tickets you created and how many open tickets are ahead of each one.</p>
            </div>
            <a href="create_ticket.php" class="btn btn-primary">Raise a new concern</a>
        </div>

        <p class="note">Only open tickets show an active queue position. PRIO tickets bypass open NON-PRIO tickets ahead of them, so a PRIO ticket with no earlier open PRIO ticket is next to be assisted.</p>

        <?php if ($tickets->num_rows === 0): ?>
            <div class="empty">
                <p>You haven't submitted any tickets yet. Create one to track its queue position.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Date &amp; Time</th>
                        <th>Room &amp; WS#</th>
                        <th>Campaign</th>
                        <th>Concern</th>
                        <th>Status</th>
                        <th>Queue Position</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($t = $tickets->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($t['tnum'] ?? ('TNUM-' . str_pad($t['id'], 6, '0', STR_PAD_LEFT))); ?></td>
                        <td><?php echo htmlspecialchars($t['concern_type']); ?></td>
                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($t['concern_datetime']))); ?></td>
                        <td><?php echo htmlspecialchars($t['room_ws']); ?></td>
                        <td><?php echo htmlspecialchars($t['campaign']); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($t['concern'], 0, 60, '…')); ?></td>
                        <td><?php echo statusBadge($t['status']); ?></td>
                        <td>
                            <?php
                                if ($t['status'] === 'Open') {
                                    if ($t['open_ahead'] > 0) {
                                        echo htmlspecialchars($t['open_ahead'] . ' ahead');
                                    } else {
                                        echo '<strong>Next</strong>';
                                    }
                                } else {
                                    echo '<span style="color: var(--text-dim);">N/A</span>';
                                }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($t['created_at']))); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
