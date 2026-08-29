<?php
require 'auth_check.php';
require 'db_config.php';

$ticketId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($ticketId <= 0) {
    header('Location: ' . ($_SESSION['role'] === 'user' ? 'user_dashboard.php' : 'admin_dashboard.php'));
    exit;
}

$conn = getDBConnection();
$error = '';
$success = '';

// Fetch ticket
$stmt = $conn->prepare(
    "SELECT t.*, u.username AS submitted_by
     FROM tickets t JOIN users u ON u.id = t.created_by
     WHERE t.id = ?"
);
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header('Location: ' . ($_SESSION['role'] === 'user' ? 'user_dashboard.php' : 'admin_dashboard.php'));
    exit;
}

// Access: users may only view their own ticket; admins/super_admins can view all
$isAdmin = in_array($_SESSION['role'], ['admin', 'super_admin'], true);
if (!$isAdmin && $ticket['created_by'] != $_SESSION['user_id']) {
    header('Location: access_denied.php');
    exit;
}

// Handle admin actions
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'acknowledge' && $ticket['status'] === 'Open') {
        $stmt = $conn->prepare(
            "UPDATE tickets SET status = 'In Progress', acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param("ii", $_SESSION['user_id'], $ticketId);
        $stmt->execute();
        $stmt->close();

        $logStmt = $conn->prepare("INSERT INTO ticket_logs (ticket_id, action, performed_by) VALUES (?, 'Acknowledged', ?)");
        $logStmt->bind_param("ii", $ticketId, $_SESSION['user_id']);
        $logStmt->execute();
        $logStmt->close();

        $success = 'Ticket acknowledged. Status set to In Progress.';

    } elseif ($action === 'save_remarks') {
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $conn->prepare("UPDATE tickets SET remarks = ? WHERE id = ?");
        $stmt->bind_param("si", $remarks, $ticketId);
        $stmt->execute();
        $stmt->close();
        $success = 'Remarks saved.';

    } elseif ($action === 'resolve' && $ticket['status'] !== 'Resolved') {
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $conn->prepare(
            "UPDATE tickets SET status = 'Resolved', remarks = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param("sii", $remarks, $_SESSION['user_id'], $ticketId);
        $stmt->execute();
        $stmt->close();

        $logStmt = $conn->prepare("INSERT INTO ticket_logs (ticket_id, action, performed_by) VALUES (?, 'Resolved', ?)");
        $logStmt->bind_param("ii", $ticketId, $_SESSION['user_id']);
        $logStmt->execute();
        $logStmt->close();

        $success = 'Ticket marked as Resolved.';
    }

    // Refresh ticket data after update
    $stmt = $conn->prepare(
        "SELECT t.*, u.username AS submitted_by
         FROM tickets t JOIN users u ON u.id = t.created_by
         WHERE t.id = ?"
    );
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch logs
$logStmt = $conn->prepare(
    "SELECT l.action, l.performed_at, u.username
     FROM ticket_logs l JOIN users u ON u.id = l.performed_by
     WHERE l.ticket_id = ? ORDER BY l.performed_at ASC"
);
$logStmt->bind_param("i", $ticketId);
$logStmt->execute();
$logs = $logStmt->get_result();
$logStmt->close();
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

$backLink = $isAdmin ? 'admin_dashboard.php' : 'user_dashboard.php';
$displayTicketNumber = htmlspecialchars($ticket['tnum'] ?? ('TNUM-' . str_pad($ticket['id'], 6, '0', STR_PAD_LEFT)));
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
            if (event.persisted) {
                window.location.reload(true);
            }
        });
        window.addEventListener('unload', function() {});
    </script>
    <title><?php echo $displayTicketNumber; ?> — SyncDesk</title>
    <style>
        :root {
            --bg: #0F172A; --bg-soft: #16213A; --accent: #F59E0B;
            --accent-soft: #FCD34D; --text: #E2E8F0; --text-dim: #94A3B8; --line: #2A3A56;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, Arial, sans-serif; background: var(--bg); color: var(--text); }
        a { text-decoration: none; color: inherit; }

        header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 40px; border-bottom: 1px solid var(--line); flex-wrap: wrap; gap: 12px;
        }
        .logo { font-size: 18px; font-weight: 800; }
        .logo span { color: var(--accent); }
        nav { display: flex; align-items: center; gap: 16px; font-size: 14px; color: var(--text-dim); }

        main { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .back-link { display: inline-block; margin-bottom: 16px; color: var(--text-dim); font-size: 14px; }
        .back-link:hover { color: var(--accent); }

        .ticket-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .ticket-head h1 { font-size: 26px; }
        .ticket-head .sub { color: var(--text-dim); font-size: 13px; margin-top: 4px; }

        .badge { display: inline-block; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: 700; }
        .badge-open { background: rgba(245,158,11,0.18); color: var(--accent); }
        .badge-progress { background: rgba(96,165,250,0.18); color: #60A5FA; }
        .badge-resolved { background: rgba(74,222,128,0.18); color: #4ADE80; }

        .card { background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; padding: 24px; margin-bottom: 20px; }
        .card h2 { font-size: 16px; margin-bottom: 14px; }

        .detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px 24px; margin-bottom: 14px; }
        .detail-grid .full { grid-column: 1 / -1; }
        .detail-item .label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-dim); margin-bottom: 4px; }
        .detail-item .value { font-size: 14px; }
        .detail-item .value.pre { white-space: pre-wrap; }

        textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 6px;
            font-size: 14px; background: var(--bg); color: var(--text); outline: none;
            font-family: inherit; resize: vertical; min-height: 90px; margin-bottom: 14px;
        }
        textarea:focus { border-color: var(--accent); }

        .btn {
            display: inline-block; padding: 10px 20px; border-radius: 6px;
            font-size: 14px; font-weight: 700; border: none; cursor: pointer;
            transition: background .2s, transform .15s;
        }
        .btn-primary { background: var(--accent); color: #1A1300; }
        .btn-primary:hover { background: var(--accent-soft); transform: translateY(-1px); }
        .btn-ghost { border: 1px solid var(--line); color: var(--text); background: transparent; }
        .btn-ghost:hover { border-color: var(--accent); }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }

        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
        .alert-error { background: rgba(248,113,113,0.12); color: #F87171; border: 1px solid rgba(248,113,113,0.3); }
        .alert-success { background: rgba(74,222,128,0.12); color: #4ADE80; border: 1px solid rgba(74,222,128,0.3); }

        .timeline { list-style: none; }
        .timeline li {
            display: flex; justify-content: space-between; padding: 10px 0;
            border-bottom: 1px solid var(--line); font-size: 14px;
        }
        .timeline li:last-child { border-bottom: none; }
        .timeline .action { font-weight: 600; }
        .timeline .meta { color: var(--text-dim); font-size: 13px; }

        @media (max-width: 600px) {
            header { padding: 16px 20px; }
            main { padding: 24px 16px; }
            .detail-grid { grid-template-columns: 1fr; }
            .card { padding: 18px; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">SyncDesk<span>.</span></a>
        <nav>
            <span>Signed in as <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
        </nav>
    </header>

    <main>
        <a href="<?php echo $backLink; ?>" class="back-link">&larr; Back</a>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="ticket-head">
            <div>
                <h1><?php echo $displayTicketNumber; ?></h1>
                <div class="sub">Submitted by <?php echo htmlspecialchars($ticket['submitted_by']); ?> on <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($ticket['created_at']))); ?></div>
            </div>
            <?php echo statusBadge($ticket['status']); ?>
        </div>

        <div class="card">
            <h2>Concern Details</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label">Type of Concern</div>
                    <div class="value"><?php echo htmlspecialchars($ticket['concern_type']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Date &amp; Time</div>
                    <div class="value"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($ticket['concern_datetime']))); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Room &amp; WS #</div>
                    <div class="value"><?php echo htmlspecialchars($ticket['room_ws']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Name</div>
                    <div class="value"><?php echo htmlspecialchars($ticket['reporter_name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">TL</div>
                    <div class="value"><?php echo htmlspecialchars($ticket['tl_name']); ?></div>
                </div>
                <div class="detail-item full">
                    <div class="label">Concern</div>
                    <div class="value pre"><?php echo htmlspecialchars($ticket['concern']); ?></div>
                </div>
                <div class="detail-item full">
                    <div class="label">Troubleshooting Made by the POC</div>
                    <div class="value pre"><?php echo htmlspecialchars($ticket['troubleshooting']); ?></div>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="card">
            <h2>Admin Actions</h2>
            <form method="POST" action="">
                <label for="remarks" style="display:block; font-size:13px; color:var(--text-dim); margin-bottom:6px;">Remarks</label>
                <textarea id="remarks" name="remarks" placeholder="Add notes about this ticket"><?php echo htmlspecialchars($ticket['remarks'] ?? ''); ?></textarea>

                <div class="btn-row">
                    <?php if ($ticket['status'] === 'Open'): ?>
                        <button type="submit" name="action" value="acknowledge" class="btn btn-primary">Acknowledge (set to In Progress)</button>
                    <?php endif; ?>

                    <button type="submit" name="action" value="save_remarks" class="btn btn-ghost">Save Remarks</button>

                    <?php if ($ticket['status'] !== 'Resolved'): ?>
                        <button type="submit" name="action" value="resolve" class="btn btn-primary">Mark as Resolved</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php elseif (!empty($ticket['remarks'])): ?>
        <div class="card">
            <h2>Remarks</h2>
            <p style="white-space: pre-wrap; color: var(--text-dim);"><?php echo htmlspecialchars($ticket['remarks']); ?></p>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Activity Log</h2>
            <ul class="timeline">
                <?php while ($log = $logs->fetch_assoc()): ?>
                <li>
                    <span class="action"><?php echo htmlspecialchars($log['action']); ?></span>
                    <span class="meta"><?php echo htmlspecialchars($log['username']); ?> &middot; <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($log['performed_at']))); ?></span>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </main>
</body>
</html>
