<?php
require 'auth_check.php';
requireRole(['user', 'admin', 'super_admin']);
require 'db_config.php';
require 'site_config.php';

$error = '';
$success = false;

function buildTicketNumber($id) {
    return 'TNUM-' . str_pad((int) $id, 6, '0', STR_PAD_LEFT);
}

// Manila timezone defaults
$manilaTz = new DateTimeZone('Asia/Manila');
$nowManilaDisplay = (new DateTime('now', $manilaTz))->format('Y-m-d\TH:i');
$nowManilaDB = (new DateTime('now', $manilaTz))->format('Y-m-d H:i:s');

$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';
$userSite = $_SESSION['site'] ?? '';
$campaigns = $isSuperAdmin ? getAllCampaigns() : getSiteCampaigns($userSite);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $concern_type    = trim($_POST['concern_type'] ?? '');
    $concern_datetime = $nowManilaDB;
    $room_ws         = trim($_POST['room_ws'] ?? '');
    $reporter_name   = trim($_POST['reporter_name'] ?? '');
    $tl_name         = trim($_POST['tl_name'] ?? '');
    $campaign        = trim($_POST['campaign'] ?? '');
    $concern         = trim($_POST['concern'] ?? '');
    $troubleshooting = trim($_POST['troubleshooting'] ?? '');

    if ($concern_type === '' || $concern_datetime === '' || $room_ws === '' ||
        $reporter_name === '' || $tl_name === '' || $campaign === '' || $concern === '' || $troubleshooting === '') {
        $error = 'All fields are required.';
    } elseif (!$isSuperAdmin && $userSite === '') {
        $error = 'Your account is not assigned to a site yet. Please contact an administrator.';
    } elseif (!in_array($concern_type, ['PRIO', 'NON-PRIO'], true)) {
        $error = 'Invalid concern type selected.';
    } elseif (!in_array($campaign, $campaigns, true)) {
        $error = 'Selected campaign is not assigned to your site.';
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare(
            "INSERT INTO tickets
                (concern_type, concern_datetime, room_ws, reporter_name, tl_name, campaign, concern, troubleshooting, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Open', ?)"
        );
        $stmt->bind_param(
            "ssssssssi",
            $concern_type, $concern_datetime, $room_ws, $reporter_name,
            $tl_name, $campaign, $concern, $troubleshooting, $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            $ticketId = $stmt->insert_id;
            $ticketNumber = buildTicketNumber($ticketId);

            $updateTnum = $conn->prepare("UPDATE tickets SET tnum = ? WHERE id = ?");
            $updateTnum->bind_param("si", $ticketNumber, $ticketId);
            $updateTnum->execute();
            $updateTnum->close();

            $logStmt = $conn->prepare(
                "INSERT INTO ticket_logs (ticket_id, action, performed_by) VALUES (?, 'Created', ?)"
            );
            $logStmt->bind_param("ii", $ticketId, $_SESSION['user_id']);
            $logStmt->execute();
            $logStmt->close();

            $redirectTarget = ($_SESSION['role'] ?? 'user') === 'user'
                ? 'user_dashboard.php'
                : 'admin_dashboard.php';
            header('Location: ' . $redirectTarget);
            exit();
        } else {
            $error = 'Failed to submit ticket. Please try again.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<?php $concern_datetime_value = htmlspecialchars($nowManilaDisplay); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="assets/js/page-cache.js"></script>
    <title>Raise a Concern — SyncDesk</title>
    <style>
        main { max-width: 700px; margin: 0 auto; padding: 40px 20px; }
        h1 { font-size: 26px; margin-bottom: 6px; }
        .subtitle { color: var(--text-dim); font-size: 14px; margin-bottom: 28px; }

        form { background: var(--bg-soft); border: 1px solid var(--line); border-radius: 10px; padding: 28px; }

        .form-group { margin-bottom: 18px; }
        label .required { color: var(--accent); }

        .radio-group { display: flex; gap: 20px; }
        .radio-group label { display: flex; align-items: center; gap: 6px; font-weight: 500; margin-bottom: 0; }
        .radio-group input { width: auto; }

        button {
            width: 100%; padding: 12px; background: var(--accent); color: #1A1300;
            border: none; border-radius: 6px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background .2s, transform .15s;
        }
        button:hover { background: var(--accent-soft); transform: translateY(-1px); }

        .back-link { display: inline-block; margin-top: 16px; color: var(--text-dim); font-size: 14px; }
        .back-link:hover { color: var(--accent); }

        @media (max-width: 600px) {
            main { padding: 24px 16px; }
            form { padding: 20px; }
            .radio-group { gap: 14px; }
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
        </nav>
    </header>

    <main>
        <h1>Raise a Concern</h1>
        <p class="subtitle">All fields are required. Submitted tickets start with status <strong>Open</strong>.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Ticket submitted successfully. <a href="user_dashboard.php" style="color:#4ADE80; text-decoration: underline;">View my tickets</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Type of Concern <span class="required">*</span></label>
                <div class="radio-group">
                    <label><input type="radio" name="concern_type" value="PRIO" required
                        <?php echo (($_POST['concern_type'] ?? '') === 'PRIO') ? 'checked' : ''; ?>> PRIO</label>
                    <label><input type="radio" name="concern_type" value="NON-PRIO"
                        <?php echo (($_POST['concern_type'] ?? '') === 'NON-PRIO') ? 'checked' : ''; ?>> NON-PRIO</label>
                </div>
            </div>

            <div class="form-group">
                <label for="concern_datetime">Date &amp; Time <span class="required">*</span></label>
                <input type="datetime-local" id="concern_datetime" name="concern_datetime" required readonly
                    value="<?php echo $concern_datetime_value; ?>">
            </div>

            <div class="form-group">
                <label for="room_ws">Room &amp; WS # <span class="required">*</span></label>
                <input type="text" id="room_ws" name="room_ws" required placeholder="e.g. Room 204 - WS 12"
                    value="<?php echo htmlspecialchars($_POST['room_ws'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="reporter_name">Name <span class="required">*</span></label>
                <input type="text" id="reporter_name" name="reporter_name" required placeholder="Your full name"
                    value="<?php echo htmlspecialchars($_POST['reporter_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="tl_name">TL <span class="required">*</span></label>
                <input type="text" id="tl_name" name="tl_name" required placeholder="Team Leader's name"
                    value="<?php echo htmlspecialchars($_POST['tl_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="campaign">Campaign <span class="required">*</span></label>
                <select id="campaign" name="campaign" required>
                    <option value="" disabled <?php echo empty($_POST['campaign']) ? 'selected' : ''; ?>>— Select a campaign —</option>
                    <?php foreach ($campaigns as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"
                            <?php echo (($_POST['campaign'] ?? '') === $c) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="concern">Concern <span class="required">*</span></label>
                <textarea id="concern" name="concern" required placeholder="Describe the concern"><?php echo htmlspecialchars($_POST['concern'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="troubleshooting">Troubleshooting Made by the POC <span class="required">*</span></label>
                <textarea id="troubleshooting" name="troubleshooting" required placeholder="Steps already taken to troubleshoot"><?php echo htmlspecialchars($_POST['troubleshooting'] ?? ''); ?></textarea>
            </div>

            <button type="submit">Submit Concern</button>
        </form>

        <a href="user_dashboard.php" class="back-link">&larr; Back to my tickets</a>
    </main>
</body>
</html>
