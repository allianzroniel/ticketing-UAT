<?php
session_start();
ob_start();

// Prevent caching for homepage when signed in.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            header('Location: super_admin_dashboard.php');
            break;
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        default:
            header('Location: user_dashboard.php');
            break;
    }
    exit;
}

$isLoggedIn = false;
$username   = '';
$role       = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SyncDesk — Support, sorted</title>
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
    <style>
        :root {
            --bg: #0F172A;
            --bg-soft: #16213A;
            --accent: #F59E0B;
            --accent-soft: #FCD34D;
            --text: #E2E8F0;
            --text-dim: #94A3B8;
            --line: #2A3A56;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        h1, h2, h3 {
            font-family: 'Trebuchet MS', 'Segoe UI', sans-serif;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        a { color: inherit; text-decoration: none; }

        /* Header */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 48px;
            border-bottom: 1px solid var(--line);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
        }

        .logo-mark {
            width: 28px;
            height: 28px;
            background: var(--accent);
            border-radius: 6px;
            position: relative;
        }

        .logo-mark::before,
        .logo-mark::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            background: var(--bg);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }

        .logo-mark::before { left: -4px; }
        .logo-mark::after { right: -4px; }

        nav { display: flex; align-items: center; gap: 28px; }

        nav a { color: var(--text-dim); font-size: 15px; transition: color 0.2s; }
        nav a:hover, nav a:focus-visible { color: var(--text); outline: none; }

        .btn {
            display: inline-block;
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            transition: transform 0.15s, background 0.2s;
        }

        .btn-primary {
            background: var(--accent);
            color: #1A1300;
        }

        .btn-primary:hover, .btn-primary:focus-visible {
            background: var(--accent-soft);
            transform: translateY(-1px);
            outline: none;
        }

        .btn-ghost {
            border: 1px solid var(--line);
            color: var(--text);
        }

        .btn-ghost:hover, .btn-ghost:focus-visible {
            border-color: var(--accent);
            outline: none;
        }

        /* Hero */
        .hero {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 60px;
            align-items: center;
            padding: 90px 48px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .eyebrow {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 16px;
        }

        .hero h1 {
            font-size: clamp(36px, 5vw, 56px);
            line-height: 1.08;
            margin-bottom: 20px;
        }

        .hero h1 span { color: var(--accent); }

        .hero p {
            color: var(--text-dim);
            font-size: 17px;
            max-width: 460px;
            margin-bottom: 32px;
        }

        .hero-actions { display: flex; gap: 14px; }

        /* Ticket stack — signature visual */
        .stack {
            position: relative;
            height: 360px;
        }

        .ticket {
            position: absolute;
            width: 320px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 22px 24px;
            box-shadow: 0 20px 40px -20px rgba(0,0,0,0.6);
        }

        .ticket::before, .ticket::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            background: var(--bg);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }

        .ticket::before { left: -11px; }
        .ticket::after { right: -11px; }

        .ticket .tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .tag-open { background: rgba(245,158,11,0.18); color: var(--accent); }
        .tag-progress { background: rgba(96,165,250,0.18); color: #60A5FA; }
        .tag-resolved { background: rgba(74,222,128,0.18); color: #4ADE80; }

        .ticket h3 { font-size: 16px; margin-bottom: 6px; }
        .ticket p { color: var(--text-dim); font-size: 13px; margin: 0; }

        .ticket-1 { top: 0; left: 40px; transform: rotate(-4deg); z-index: 1; }
        .ticket-2 { top: 90px; left: 90px; transform: rotate(2deg); z-index: 2; }
        .ticket-3 { top: 195px; left: 30px; transform: rotate(-1deg); z-index: 3; }

        /* Features */
        .features {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 48px 100px;
        }

        .features-head { text-align: center; margin-bottom: 50px; }
        .features-head h2 { font-size: 32px; margin-bottom: 10px; }
        .features-head p { color: var(--text-dim); }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .feature-card {
            background: var(--bg-soft);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 28px;
        }

        .feature-card .icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(245,158,11,0.12);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 16px;
        }

        .feature-card h3 { font-size: 18px; margin-bottom: 8px; }
        .feature-card p { color: var(--text-dim); font-size: 14px; margin: 0; }

        /* Footer */
        footer {
            border-top: 1px solid var(--line);
            padding: 28px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-dim);
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 860px) {
            header { padding: 18px 24px; flex-wrap: wrap; gap: 14px; }
            nav { gap: 16px; flex-wrap: wrap; }
            .hero {
                grid-template-columns: 1fr;
                padding: 50px 24px;
                text-align: center;
            }
            .hero p { margin-left: auto; margin-right: auto; }
            .hero-actions { justify-content: center; }
            .stack { display: none; }
            .feature-grid { grid-template-columns: 1fr; }
            .features { padding: 20px 24px 70px; }
            footer { flex-direction: column; gap: 10px; text-align: center; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <header>
        <div class="logo">
            <span class="logo-mark"></span>
            <span>SyncDesk</span>
        </div>
        <nav>
            <a href="#features">Features</a>
            <?php if ($isLoggedIn): ?>
                <span style="color: var(--text-dim); font-size: 15px;">
                    Signed in as <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)
                </span>
                <?php
                    $dashboard = 'user_dashboard.php';
                    if ($role === 'admin') $dashboard = 'admin_dashboard.php';
                    if ($role === 'super_admin') $dashboard = 'super_admin_dashboard.php';
                ?>
                <a href="<?php echo $dashboard; ?>" class="btn btn-ghost">Dashboard</a>
                <a href="logout.php" class="btn btn-primary">Log out</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Log in</a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="hero">
        <div>
            <span class="eyebrow">Issue tracking, simplified</span>
            <h1>Every request, <span>tracked</span> from open to resolved.</h1>
            <p>
                Log issues, assign them to the right person, and follow progress
                in one place — built for teams who'd rather fix things than
                chase email threads.
            </p>
            <div class="hero-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo $dashboard; ?>" class="btn btn-primary">Go to dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Log in</a>
                <?php endif; ?>
                <a href="#features" class="btn btn-ghost">See how it works</a>
            </div>
        </div>

        <div class="stack" aria-hidden="true">
            <div class="ticket ticket-1">
                <span class="tag tag-open">Open</span>
                <h3>Printer offline — 3rd floor</h3>
                <p>Reported 2 hours ago</p>
            </div>
            <div class="ticket ticket-2">
                <span class="tag tag-progress">In progress</span>
                <h3>VPN access request</h3>
                <p>Assigned to IT Support</p>
            </div>
            <div class="ticket ticket-3">
                <span class="tag tag-resolved">Resolved</span>
                <h3>Password reset</h3>
                <p>Closed in 14 minutes</p>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="features-head">
            <h2>One queue. Three roles. Zero confusion.</h2>
            <p>Access adjusts automatically based on who's signed in.</p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="icon">U</div>
                <h3>Users</h3>
                <p>Submit tickets and track their status from open to resolved, without digging through email.</p>
            </div>
            <div class="feature-card">
                <div class="icon">A</div>
                <h3>Admins</h3>
                <p>Review incoming tickets, assign owners, and update statuses across the whole queue.</p>
            </div>
            <div class="feature-card">
                <div class="icon">S</div>
                <h3>Super Admins</h3>
                <p>Manage admin accounts, configure categories, and oversee the system end to end.</p>
            </div>
        </div>
    </section>

    <footer>
        <span>SyncDesk</span>
        <span>&copy; <?php echo date('Y'); ?> — Internal use only</span>
    </footer>
</body>
</html>
