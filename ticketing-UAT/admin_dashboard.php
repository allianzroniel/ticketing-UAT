<?php
require 'auth_check.php';
requireRole(['admin', 'super_admin']);
require 'db_config.php';
require 'site_config.php';

$conn = getDBConnection();
$currentRole = $_SESSION['role'] ?? '';
$currentSite = $_SESSION['site'] ?? '';
$isSiteScopedUser = $currentRole !== 'super_admin';

// Optional status filter
$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['Open', 'In Progress', 'Resolved'];

$sql = "SELECT t.id, t.tnum, t.concern_type, t.concern_datetime, t.room_ws, t.reporter_name, t.tl_name, t.campaign,
           t.concern, t.remarks, t.status, t.created_at, u.username AS submitted_by
        FROM tickets t
        JOIN users u ON u.id = t.created_by";

if ($isSiteScopedUser && $currentSite !== '') {
    $sql .= " WHERE u.site = ?";
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= $isSiteScopedUser && $currentSite !== '' ? " AND t.status = ?" : " WHERE t.status = ?";
    $sql .= " ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Resolved'), FIELD(t.concern_type, 'PRIO', 'NON-PRIO'), t.id DESC";
    $stmt = $conn->prepare($sql);
    if ($isSiteScopedUser && $currentSite !== '') {
        $stmt->bind_param("ss", $currentSite, $statusFilter);
    } else {
        $stmt->bind_param("s", $statusFilter);
    }
} else {
    $sql .= " ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Resolved'), FIELD(t.concern_type, 'PRIO', 'NON-PRIO'), t.id DESC";
    $stmt = $conn->prepare($sql);
    if ($isSiteScopedUser && $currentSite !== '') {
        $stmt->bind_param("s", $currentSite);
    }
}

$stmt->execute();
$tickets = $stmt->get_result();

// Quick counts for the filter tabs
$counts = ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0];
$countSql = "SELECT t.status, COUNT(*) AS c FROM tickets t JOIN users u ON u.id = t.created_by";
if ($isSiteScopedUser && $currentSite !== '') {
    $countSql .= " WHERE u.site = ?";
}
$countSql .= " GROUP BY t.status";
$countStmt = $conn->prepare($countSql);
if ($isSiteScopedUser && $currentSite !== '') {
    $countStmt->bind_param("s", $currentSite);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
while ($row = $countResult->fetch_assoc()) {
    $counts[$row['status']] = (int) $row['c'];
}
$countStmt->close();
$totalCount = array_sum($counts);

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
    <title>Admin Dashboard — SyncDesk</title>
    <style>
        main { max-width: 1200px; margin: 0 auto; padding: 40px; }
        .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; padding: 18px; }
        .stat-card .label { color: var(--text-dim); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .stat-card .value { font-size: 28px; font-weight: 800; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .tab {
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600;
            border: 1px solid var(--line); color: var(--text-dim);
        }
        .tab.active { background: var(--accent); color: #1A1300; border-color: var(--accent); }
        .tab:hover:not(.active) { border-color: var(--accent); color: var(--text); }

        table { width: 100%; border-collapse: collapse; background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        th, td { padding: 12px 16px; text-align: left; font-size: 14px; border-bottom: 1px solid var(--line); }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(245,158,11,0.05); }

        .badge-open { background: rgba(245,158,11,0.18); color: var(--accent); }
        .badge-progress { background: rgba(96,165,250,0.18); color: #60A5FA; }
        .badge-resolved { background: rgba(74,222,128,0.18); color: #4ADE80; }

        .empty { text-align: center; padding: 60px 20px; color: var(--text-dim); }
        .report-box {
            background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px;
            padding: 20px; margin-bottom: 28px; display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;
        }
        .report-box .form-group label { display: block; font-size: 12px; color: var(--text-dim); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
        .report-box input[type="date"] {
            padding: 9px 12px; border: 1px solid var(--line); border-radius: 6px;
            background: var(--bg); color: var(--text); font-size: 14px; font-family: inherit;
        }

        @media (max-width: 900px) {
            .stat-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 720px) {
            main { padding: 20px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            .stat-row { grid-template-columns: 1fr 1fr; }
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
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="super_admin_dashboard.php" class="btn btn-ghost">Analytics</a>
            <?php endif; ?>
            <?php if (in_array($_SESSION['role'], ['admin','super_admin'], true)): ?>
                <a href="campaign_report_admin.php" class="btn btn-ghost">Campaign Report</a>
            <?php endif; ?>
            <a href="manage_users.php" class="btn btn-ghost">Manage Users</a>
            <a href="logout.php" class="btn btn-ghost">Log out</a>
        </nav>
    </header>

    <main>
        <div class="page-head">
            <h1>Admin Dashboard</h1>
            <p>Review, acknowledge, and resolve concerns raised across the system.</p>
        </div>

        <div class="stat-row">
            <div class="stat-card">
                <div class="label">Total Tickets</div>
                <div class="value"><?php echo $totalCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Open</div>
                <div class="value"><?php echo $counts['Open']; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">In Progress</div>
                <div class="value"><?php echo $counts['In Progress']; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Resolved</div>
                <div class="value"><?php echo $counts['Resolved']; ?></div>
            </div>
        </div>

        <form class="report-box" method="GET" action="download_report.php">
            <div class="form-group">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Download CSV Report</button>
        </form>

        <div class="toolbar">
            <div class="tabs">
                <a href="admin_dashboard.php" class="tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="admin_dashboard.php?status=Open" class="tab <?php echo $statusFilter === 'Open' ? 'active' : ''; ?>">Open</a>
                <a href="admin_dashboard.php?status=In Progress" class="tab <?php echo $statusFilter === 'In Progress' ? 'active' : ''; ?>">In Progress</a>
                <a href="admin_dashboard.php?status=Resolved" class="tab <?php echo $statusFilter === 'Resolved' ? 'active' : ''; ?>">Resolved</a>
            </div>
        </div>

        <?php if ($tickets->num_rows === 0): ?>
            <div class="empty">
                <p>No tickets found for this filter.</p>
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
                        <th>Reporter</th>
                        <th>Concern</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Submitted By</th>
                        <th></th>
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
                        <td><?php echo htmlspecialchars($t['reporter_name']); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($t['concern'], 0, 40, '…')); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($t['remarks'], 0, 40, '…')); ?></td>
                        <td><?php echo statusBadge($t['status']); ?></td>
                        <td><?php echo htmlspecialchars($t['submitted_by']); ?></td>
                        <td><a href="ticket_view.php?id=<?php echo $t['id']; ?>" class="view-link">View &rarr;</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

<script>
// ── Ticketing — Realtime Notification Engine ─────────────────────────────────
(function () {
    'use strict';

    /* ---- Permission ---- */
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    /* ---- MP3 Notification Sound ---- */
    var notificationAudio;
    function initNotificationAudio() {
        if (notificationAudio) return;
        var audioSrc = new URL('you-are-impostor-sound.mp3', window.location.href).href;
        notificationAudio = new Audio(audioSrc);
        notificationAudio.preload = 'auto';
        notificationAudio.volume = 1.0;
        notificationAudio.muted = false;
        notificationAudio.playsInline = true;
        notificationAudio.load();
        function unlockAudio() {
            notificationAudio.play().then(function () {
                notificationAudio.pause();
                notificationAudio.currentTime = 0;
            }).catch(function () {});
        }
        window.addEventListener('click', unlockAudio, { once: true, capture: true });
        window.addEventListener('keydown', unlockAudio, { once: true, capture: true });
    }
    function playBeep() {
        try {
            initNotificationAudio();
            var sound = new Audio(notificationAudio.src);
            sound.preload = 'auto';
            sound.volume = 1.0;
            sound.muted = false;
            sound.playsInline = true;
            sound.currentTime = 0;
            sound.play().catch(function () {});
            setTimeout(function () {
                if (!sound.paused) {
                    sound.pause();
                    sound.currentTime = 0;
                }
            }, 5000);
        } catch (e) {}
    }

    /* ---- In-page toast (works even when tab is hidden) ---- */
    function showToast(ticket) {
        var existing = document.getElementById('notif-toast-container');
        if (!existing) {
            existing = document.createElement('div');
            existing.id = 'notif-toast-container';
            existing.style.cssText = [
                'position:fixed','bottom:24px','right:24px','z-index:99999',
                'display:flex','flex-direction:column','gap:10px',
                'max-width:340px','pointer-events:none'
            ].join(';');
            document.body.appendChild(existing);
        }
        var toast = document.createElement('div');
        toast.style.cssText = [
            'background:#1E3A5F','border:1px solid #F59E0B',
            'border-left:4px solid #F59E0B','border-radius:8px',
            'padding:14px 16px','color:#E2E8F0','font-family:Segoe UI,sans-serif',
            'font-size:13px','box-shadow:0 8px 24px rgba(0,0,0,0.5)',
            'pointer-events:auto','cursor:pointer','opacity:0',
            'transform:translateY(12px)',
            'transition:opacity .3s,transform .3s'
        ].join(';');
        var badge = ticket.concern_type === 'PRIO'
            ? '<span style="background:rgba(245,158,11,.25);color:#F59E0B;font-size:11px;font-weight:700;padding:2px 7px;border-radius:3px;margin-right:6px;">PRIO</span>'
            : '<span style="background:rgba(96,165,250,.2);color:#60A5FA;font-size:11px;font-weight:700;padding:2px 7px;border-radius:3px;margin-right:6px;">NON-PRIO</span>';
        toast.innerHTML =
            '<div style="font-weight:700;margin-bottom:4px;display:flex;align-items:center;">' +
            badge + 'Ticket #' + ticket.id + ' — ' + (ticket.campaign || '') + '</div>' +
            '<div style="color:#94A3B8;margin-bottom:6px;font-size:12px;">By <strong style="color:#E2E8F0;">' + ticket.submitted_by + '</strong></div>' +
            '<div style="color:#CBD5E1;">' + ticket.concern + '</div>' +
            '<div style="margin-top:8px;color:#F59E0B;font-size:12px;font-weight:600;">Click to view →</div>';
        toast.onclick = function () { window.location.href = 'ticket_view.php?id=' + ticket.id; };
        existing.appendChild(toast);
        requestAnimationFrame(function(){
            requestAnimationFrame(function(){
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            });
        });
        setTimeout(function(){
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
            setTimeout(function(){ if(toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
        }, 9000);
    }

    /* ---- Desktop OS Notification ---- */
    function showDesktopNotif(ticket) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        var title = (ticket.concern_type === 'PRIO' ? '🔴 PRIO' : '🔵 NON-PRIO') +
                    ' — New Ticket #' + ticket.id +
                    (ticket.campaign ? ' [' + ticket.campaign + ']' : '');
        var body  = 'Submitted by ' + ticket.submitted_by + '\n' + ticket.concern;
        try {
            var n = new Notification(title, { body: body, requireInteraction: false, tag: 'tkt-' + ticket.id });
            n.onclick = function () { window.focus(); window.location.href = 'ticket_view.php?id=' + ticket.id; n.close(); };
            setTimeout(function(){ n.close(); }, 9000);
        } catch(e) {}
    }

    /* ---- Polling ---- */
    var lastId      = 0;
    var initialized = false;

    function poll() {
        fetch('ticket_notify.php?mode=notify&last_id=' + lastId, { credentials: 'same-origin' })
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(data){
                if (!data) return;
                if (!initialized) { lastId = data.maxId; initialized = true; return; }
                if (data.newCount > 0) {
                    lastId = data.maxId;
                    playBeep();
                    data.tickets.forEach(function(t){
                        showToast(t);
                        showDesktopNotif(t);
                    });
                }
            })
            .catch(function(){});
    }

    poll();
    setInterval(poll, 10000); // every 10 seconds
})();
</script>

</body>
</html>
