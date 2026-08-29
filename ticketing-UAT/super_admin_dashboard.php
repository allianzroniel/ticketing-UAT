<?php
require 'auth_check.php';
requireRole(['super_admin']);
require 'db_config.php';

$conn = getDBConnection();

// Status counts
$statusCounts = ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM tickets GROUP BY status");
while ($row = $res->fetch_assoc()) {
    $statusCounts[$row['status']] = (int) $row['c'];
}
$totalTickets = array_sum($statusCounts);

// Type counts (PRIO vs NON-PRIO)
$typeCounts = ['PRIO' => 0, 'NON-PRIO' => 0];
$res = $conn->query("SELECT concern_type, COUNT(*) AS c FROM tickets GROUP BY concern_type");
while ($row = $res->fetch_assoc()) {
    $typeCounts[$row['concern_type']] = (int) $row['c'];
}

// Date range for chart — default: current month
$manilaTz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $manilaTz);

$chartFrom = $_GET['chart_from'] ?? $now->format('Y-m') . '-01';
$chartTo   = $_GET['chart_to']   ?? $now->format('Y-m-t');

// Validate dates
function isValidDate($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
if (!isValidDate($chartFrom)) $chartFrom = $now->format('Y-m') . '-01';
if (!isValidDate($chartTo))   $chartTo   = $now->format('Y-m-t');

// Build per-day array for the selected range
$dtFrom = new DateTime($chartFrom);
$dtTo   = new DateTime($chartTo);
if ($dtFrom > $dtTo) { $dtTo = clone $dtFrom; }

$byDay = [];
$cursor = clone $dtFrom;
while ($cursor <= $dtTo) {
    $byDay[$cursor->format('Y-m-d')] = 0;
    $cursor->modify('+1 day');
}

$chartFromFull = $chartFrom . ' 00:00:00';
$chartToFull   = $chartTo   . ' 23:59:59';

$stmt = $conn->prepare(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM tickets
     WHERE created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at)"
);
$stmt->bind_param("ss", $chartFromFull, $chartToFull);
$stmt->execute();
$chartRes = $stmt->get_result();
while ($row = $chartRes->fetch_assoc()) {
    if (isset($byDay[$row['d']])) {
        $byDay[$row['d']] = (int) $row['c'];
    }
}
$stmt->close();
$maxByDay = max(1, max($byDay ?: [0]));

// Average resolution time
$avgResolutionHours = null;
$res = $conn->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) AS avg_minutes FROM tickets WHERE resolved_at IS NOT NULL");
if ($row = $res->fetch_assoc()) {
    if ($row['avg_minutes'] !== null) {
        $avgResolutionHours = round($row['avg_minutes'] / 60, 1);
    }
}

// Recent tickets
$res = $conn->query(
    "SELECT t.id, t.tnum, t.concern_type, t.room_ws, t.campaign, t.reporter_name, t.concern, t.remarks, t.status, t.created_at, u.username AS submitted_by
     FROM tickets t JOIN users u ON u.id = t.created_by
     ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Resolved'), FIELD(t.concern_type, 'PRIO', 'NON-PRIO'), t.id DESC LIMIT 10"
);
$recentTickets = $res;

// Per-admin resolved counts
$res = $conn->query(
    "SELECT u.username, COUNT(*) AS resolved_count
     FROM tickets t JOIN users u ON u.id = t.resolved_by
     WHERE t.status = 'Resolved'
     GROUP BY u.username ORDER BY resolved_count DESC LIMIT 5"
);
$adminPerformance = $res;

$conn->close();

function statusBadge($status) {
    $map = ['Open' => 'badge-open', 'In Progress' => 'badge-progress', 'Resolved' => 'badge-resolved'];
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
    <script>
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) { window.location.reload(true); }
        });
        window.addEventListener('unload', function() {});
    </script>
    <title>Analytics — SyncDesk</title>
    <style>
        :root {
            --bg: #0F172A; --bg-soft: #16213A; --accent: #F59E0B;
            --accent-soft: #FCD34D; --text: #E2E8F0; --text-dim: #94A3B8; --line: #2A3A56;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, Arial, sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }

        header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 40px; border-bottom: 1px solid var(--line); flex-wrap: wrap; gap: 12px;
        }
        .logo { font-size: 18px; font-weight: 800; }
        .logo span { color: var(--accent); }
        nav { display: flex; align-items: center; justify-content: flex-end; gap: 10px 16px; min-width: 0; font-size: 14px; color: var(--text-dim); flex-wrap: wrap; }

        .btn {
            display: inline-block; padding: 9px 18px; border-radius: 6px;
            font-size: 14px; font-weight: 600; transition: background .2s, transform .15s; border: none; cursor: pointer;
        }
        .btn-primary { background: var(--accent); color: #1A1300; }
        .btn-primary:hover { background: var(--accent-soft); transform: translateY(-1px); }
        .btn-ghost { border: 1px solid var(--line); color: var(--text); background: transparent; }
        .btn-ghost:hover { border-color: var(--accent); }

        main { width: 100%; max-width: 1200px; margin: 0 auto; padding: 40px; }
        .page-head h1 { font-size: 26px; margin-bottom: 4px; }
        .page-head p { color: var(--text-dim); font-size: 14px; margin-bottom: 28px; }

        .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; padding: 18px; }
        .stat-card .label { color: var(--text-dim); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .stat-card .value { font-size: 28px; font-weight: 800; }
        .stat-card .value.accent { color: var(--accent); }

        .grid-2 { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr); gap: 20px; margin-bottom: 28px; }

        .card { min-width: 0; background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; padding: 24px; }
        .card h2 { font-size: 16px; margin-bottom: 14px; }

        /* Date range filter */
        .chart-filter {
            display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap;
            margin-bottom: 20px; padding: 16px; background: var(--bg); border: 1px solid var(--line);
            border-radius: 8px;
        }
        .chart-filter .fg { display: flex; flex: 1 1 140px; min-width: 0; flex-direction: column; gap: 5px; }
        .chart-filter label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-dim); }
        .chart-filter input[type="date"] {
            width: 100%; min-width: 0; padding: 8px 11px; border: 1px solid var(--line); border-radius: 6px;
            background: var(--bg-soft); color: var(--text); font-size: 13px; font-family: inherit; outline: none;
        }
        .chart-filter input[type="date"]:focus { border-color: var(--accent); }

        /* Bar chart */
        .bars-wrap { overflow-x: auto; }
        .bars { display: flex; align-items: flex-end; gap: 6px; height: 160px; min-width: max-content; padding-bottom: 4px; }
        .bar-col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 30px; }
        .bar {
            width: 24px; background: var(--accent); border-radius: 4px 4px 0 0;
            min-height: 4px; transition: filter .2s;
        }
        .bar:hover { filter: brightness(1.2); }
        .bar-label { font-size: 10px; color: var(--text-dim); margin-top: 6px; writing-mode: horizontal-tb; white-space: nowrap; }
        .bar-value { font-size: 11px; color: var(--text); margin-bottom: 4px; font-weight: 700; }

        /* Split bars */
        .split-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .split-label { width: 90px; font-size: 13px; font-weight: 600; }
        .split-track { flex: 1; height: 10px; background: var(--bg); border-radius: 6px; overflow: hidden; }
        .split-fill { height: 100%; border-radius: 6px; }
        .split-fill.prio { background: var(--accent); }
        .split-fill.nonprio { background: #60A5FA; }
        .split-value { width: 40px; text-align: right; font-size: 13px; color: var(--text-dim); }

        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        .recent-table { min-width: 900px; }
        th, td { padding: 10px 12px; text-align: left; font-size: 13px; border-bottom: 1px solid var(--line); }
        th { color: var(--text-dim); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; }
        .badge-open { background: rgba(245,158,11,0.18); color: var(--accent); }
        .badge-progress { background: rgba(96,165,250,0.18); color: #60A5FA; }
        .badge-resolved { background: rgba(74,222,128,0.18); color: #4ADE80; }

        .view-link { color: var(--accent); font-weight: 600; font-size: 13px; }
        .view-link:hover { text-decoration: underline; }

        .empty { color: var(--text-dim); font-size: 14px; padding: 20px 0; text-align: center; }

        @media (max-width: 1000px) {
            header { padding-left: 28px; padding-right: 28px; }
            nav { flex: 1 1 100%; justify-content: flex-start; }
            .grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            header { padding: 16px 20px; }
            main { padding: 20px; }
            nav { gap: 8px; }
            nav > span { flex-basis: 100%; }
            nav .btn { flex: 1 1 140px; text-align: center; }
            .page-head h1 { font-size: 23px; }
            .stat-row { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-card .value { font-size: 24px; }
            .card { padding: 18px; }
            .chart-filter { align-items: stretch; gap: 10px; }
            .chart-filter .btn { width: 100%; }
            .split-row { gap: 8px; }
            .split-label { width: 76px; }
            .table-wrap { margin-right: -18px; padding-right: 18px; }
        }
        @media (max-width: 390px) {
            main { padding: 16px; }
            .stat-row { grid-template-columns: 1fr; }
            .card { padding: 16px; }
            .table-wrap { margin-right: -16px; padding-right: 16px; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">SyncDesk<span>.</span></a>
        <nav>
            <span>Signed in as <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <a href="admin_dashboard.php" class="btn btn-ghost">Ticket Queue</a>
            <a href="campaign_report.php" class="btn btn-ghost">Campaign Report</a>
            <a href="manage_users.php" class="btn btn-ghost">Manage Users</a>
            <a href="logout.php" class="btn btn-ghost">Log out</a>
        </nav>
    </header>

    <main>
        <div class="page-head">
            <h1>Analytics &amp; Reports</h1>
            <p>System-wide overview of all concerns raised in the SyncDesk system.</p>
        </div>

        <div class="stat-row">
            <div class="stat-card">
                <div class="label">Total Tickets</div>
                <div class="value"><?php echo $totalTickets; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Open</div>
                <div class="value accent"><?php echo $statusCounts['Open']; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">In Progress</div>
                <div class="value"><?php echo $statusCounts['In Progress']; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Resolved</div>
                <div class="value"><?php echo $statusCounts['Resolved']; ?></div>
            </div>
            <div class="stat-card" style="display: none;">
                <div class="label">Avg. Resolution</div>
                <div class="value"><?php echo $avgResolutionHours !== null ? $avgResolutionHours . 'h' : '—'; ?></div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2>Tickets Created — Custom Date Range</h2>
                <form class="chart-filter" method="GET" action="">
                    <div class="fg">
                        <label for="chart_from">From</label>
                        <input type="date" id="chart_from" name="chart_from" value="<?php echo htmlspecialchars($chartFrom); ?>">
                    </div>
                    <div class="fg">
                        <label for="chart_to">To</label>
                        <input type="date" id="chart_to" name="chart_to" value="<?php echo htmlspecialchars($chartTo); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:8px 16px; font-size:13px;">Apply</button>
                </form>
                <div class="bars-wrap">
                    <div class="bars">
                        <?php foreach ($byDay as $date => $count): ?>
                            <div class="bar-col">
                                <div class="bar-value"><?php echo $count ?: ''; ?></div>
                                <div class="bar" style="height: <?php echo $count > 0 ? max(6, ($count / $maxByDay) * 130) : 2; ?>px;"></div>
                                <div class="bar-label"><?php echo date('M d', strtotime($date)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Concern Type Split</h2>
                <?php
                    $totalTypes = max(1, array_sum($typeCounts));
                    $prioPct = round(($typeCounts['PRIO'] / $totalTypes) * 100);
                    $nonPrioPct = round(($typeCounts['NON-PRIO'] / $totalTypes) * 100);
                ?>
                <div class="split-row">
                    <div class="split-label">PRIO</div>
                    <div class="split-track"><div class="split-fill prio" style="width: <?php echo $prioPct; ?>%;"></div></div>
                    <div class="split-value"><?php echo $typeCounts['PRIO']; ?></div>
                </div>
                <div class="split-row">
                    <div class="split-label">NON-PRIO</div>
                    <div class="split-track"><div class="split-fill nonprio" style="width: <?php echo $nonPrioPct; ?>%;"></div></div>
                    <div class="split-value"><?php echo $typeCounts['NON-PRIO']; ?></div>
                </div>

                <h2 style="margin-top: 24px;">Top Resolvers</h2>
                <?php if ($adminPerformance->num_rows === 0): ?>
                    <div class="empty">No resolved tickets yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Admin</th><th>Resolved</th></tr></thead>
                            <tbody>
                                <?php while ($row = $adminPerformance->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo $row['resolved_count']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Recent Concerns</h2>
            <?php if ($recentTickets->num_rows === 0): ?>
                <div class="empty">No tickets have been submitted yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Type</th><th>Room &amp; WS#</th><th>Campaign</th>
                            <th>Reporter</th><th>Concern</th><th>Remarks</th><th>Status</th><th>Submitted By</th><th>Created</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($t = $recentTickets->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['tnum'] ?? ('TNUM-' . str_pad($t['id'], 6, '0', STR_PAD_LEFT))); ?></td>
                            <td><?php echo htmlspecialchars($t['concern_type']); ?></td>
                            <td><?php echo htmlspecialchars($t['room_ws']); ?></td>
                            <td><?php echo htmlspecialchars($t['campaign']); ?></td>
                            <td><?php echo htmlspecialchars($t['reporter_name']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($t['concern'], 0, 40, '…')); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($t['remarks'], 0, 40, '…')); ?></td>
                            <td><?php echo statusBadge($t['status']); ?></td>
                            <td><?php echo htmlspecialchars($t['submitted_by']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, H:i', strtotime($t['created_at']))); ?></td>
                            <td><a href="ticket_view.php?id=<?php echo $t['id']; ?>" class="view-link">View &rarr;</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
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
                    data.tickets.forEach(function(t, i){
                        setTimeout(function(){
                            showToast(t);
                            showDesktopNotif(t);
                        }, i * 500);
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
