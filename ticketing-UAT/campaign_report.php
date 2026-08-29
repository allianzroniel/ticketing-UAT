<?php
require 'auth_check.php';
requireRole(['admin', 'super_admin']);
require 'db_config.php';

// Initial server-side render for fast first paint
$conn = getDBConnection();
$res = $conn->query(
    "SELECT campaign, concern_type, status, COUNT(*) AS c
     FROM tickets
     GROUP BY campaign, concern_type, status
     ORDER BY campaign ASC, concern_type ASC, status ASC"
);
$initData = [];
while ($row = $res->fetch_assoc()) {
    $camp   = $row['campaign'];
    $type   = $row['concern_type'];
    $status = $row['status'];
    if (!isset($initData[$camp])) {
        $initData[$camp] = [
            'PRIO'     => ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0],
            'NON-PRIO' => ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0],
        ];
    }
    $initData[$camp][$type][$status] = (int) $row['c'];
}
$maxIdRes = $conn->query("SELECT MAX(id) AS max_id FROM tickets");
$initMaxId = (int) ($maxIdRes->fetch_assoc()['max_id'] ?? 0);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Campaign Report — SyncDesk</title>
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
        nav { display: flex; align-items: center; gap: 16px; font-size: 14px; color: var(--text-dim); flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: 9px 18px; border-radius: 6px;
            font-size: 14px; font-weight: 600; transition: background .2s, transform .15s; border: none; cursor: pointer;
        }
        .btn-ghost { border: 1px solid var(--line); color: var(--text); background: transparent; }
        .btn-ghost:hover { border-color: var(--accent); }

        main { max-width: 1140px; margin: 0 auto; padding: 40px; }

        .page-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; margin-bottom: 28px;
        }
        .page-head h1 { font-size: 26px; margin-bottom: 4px; }
        .page-head p { color: var(--text-dim); font-size: 14px; }

        /* Live indicator */
        .live-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(74,222,128,0.12); border: 1px solid rgba(74,222,128,0.3);
            color: #4ADE80; font-size: 12px; font-weight: 700;
            padding: 5px 12px; border-radius: 20px;
        }
        .live-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: #4ADE80; animation: pulse 1.6s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.75); }
        }
        .last-updated { font-size: 11px; color: var(--text-dim); margin-top: 4px; }

        /* Grid */
        .campaigns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
        }

        .campaign-card {
            background: var(--bg-soft); border: 1px solid var(--line);
            border-radius: 10px; overflow: hidden;
            transition: border-color .3s;
        }
        .campaign-card.updated { border-color: var(--accent); }

        .campaign-header {
            background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05));
            border-bottom: 1px solid var(--line);
            padding: 14px 18px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .campaign-header h2 { font-size: 16px; font-weight: 800; color: var(--accent); }
        .campaign-total {
            font-size: 12px; color: var(--text-dim);
            background: var(--bg); border: 1px solid var(--line);
            border-radius: 20px; padding: 3px 10px;
        }

        .campaign-body { padding: 14px 18px; }

        .type-section { margin-bottom: 12px; }
        .type-section:last-child { margin-bottom: 0; }

        .type-label {
            display: inline-block; font-size: 11px; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase;
            padding: 3px 8px; border-radius: 4px; margin-bottom: 8px;
        }
        .type-label.prio   { background: rgba(245,158,11,0.18); color: var(--accent); }
        .type-label.nonprio{ background: rgba(96,165,250,0.18);  color: #60A5FA; }

        .status-rows { display: flex; flex-direction: column; gap: 5px; }
        .status-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 7px 11px; border-radius: 6px;
            background: var(--bg); border: 1px solid var(--line);
        }
        .status-name  { font-size: 13px; color: var(--text-dim); }
        .status-count { font-size: 15px; font-weight: 800; transition: color .4s; }
        .status-count.open { color: var(--accent); }
        .status-count.prog { color: #60A5FA; }
        .status-count.res  { color: #4ADE80; }
        .status-count.bump { animation: numBump .5s ease; }
        @keyframes numBump {
            0%  { transform: scale(1); }
            40% { transform: scale(1.35); }
            100%{ transform: scale(1); }
        }

        .divider { height: 1px; background: var(--line); margin: 10px 0; }

        .empty { text-align: center; padding: 60px 20px; color: var(--text-dim); font-size: 15px; }

        @media (max-width: 720px) {
            header { padding: 16px 20px; }
            main   { padding: 20px; }
            .campaigns-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <header>
        <a href="index.php" class="logo">SyncDesk<span>.</span></a>
        <nav>
            <span>Signed in as <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <a href="super_admin_dashboard.php" class="btn btn-ghost">Analytics</a>
            <a href="admin_dashboard.php" class="btn btn-ghost">Ticket Queue</a>
            <a href="manage_users.php" class="btn btn-ghost">Manage Users</a>
            <a href="logout.php" class="btn btn-ghost">Log out</a>
        </nav>
    </header>

    <main>
        <div class="page-head">
            <div>
                <h1>Concerns by Campaign</h1>
                <p>Only campaigns with at least one ticket are shown. Updates live every 10 seconds.</p>
            </div>
            <div style="text-align:right;">
                <div class="live-pill"><span class="live-dot"></span> LIVE</div>
                <div class="last-updated" id="last-updated">Initialising…</div>
            </div>
        </div>

        <div id="campaigns-grid" class="campaigns-grid">
            <!-- rendered by JS from initData -->
        </div>
        <div id="empty-msg" class="empty" style="display:none;">No tickets have been submitted yet.</div>
    </main>

    <script>
    // ── Seed data from PHP (fast first paint) ─────────────────────────────────
    var state = <?php echo json_encode([
        'campaigns' => $initData,
        'maxId'     => $initMaxId,
    ]); ?>;

    // ── Notification + audio engine ──────────────────────────────────────────
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

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

    function showToast(ticket) {
        var wrap = document.getElementById('notif-toast-container');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'notif-toast-container';
            wrap.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;max-width:340px;pointer-events:none;';
            document.body.appendChild(wrap);
        }
        var toast = document.createElement('div');
        toast.style.cssText = 'background:#1E3A5F;border:1px solid #F59E0B;border-left:4px solid #F59E0B;border-radius:8px;padding:14px 16px;color:#E2E8F0;font-family:Segoe UI,sans-serif;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,0.5);pointer-events:auto;cursor:pointer;opacity:0;transform:translateY(12px);transition:opacity .3s,transform .3s;';
        var badge = ticket.concern_type === 'PRIO'
            ? '<span style="background:rgba(245,158,11,.25);color:#F59E0B;font-size:11px;font-weight:700;padding:2px 7px;border-radius:3px;margin-right:6px;">PRIO</span>'
            : '<span style="background:rgba(96,165,250,.2);color:#60A5FA;font-size:11px;font-weight:700;padding:2px 7px;border-radius:3px;margin-right:6px;">NON-PRIO</span>';
        toast.innerHTML =
            '<div style="font-weight:700;margin-bottom:4px;display:flex;align-items:center;">' + badge +
            'Ticket #' + ticket.id + (ticket.campaign ? ' — ' + ticket.campaign : '') + '</div>' +
            '<div style="color:#94A3B8;margin-bottom:6px;font-size:12px;">By <strong style="color:#E2E8F0;">' + ticket.submitted_by + '</strong></div>' +
            '<div style="color:#CBD5E1;">' + ticket.concern + '</div>' +
            '<div style="margin-top:8px;color:#F59E0B;font-size:12px;font-weight:600;">Click to view →</div>';
        toast.onclick = function(){ window.location.href = 'ticket_view.php?id=' + ticket.id; };
        wrap.appendChild(toast);
        requestAnimationFrame(function(){ requestAnimationFrame(function(){
            toast.style.opacity = '1'; toast.style.transform = 'translateY(0)';
        }); });
        setTimeout(function(){
            toast.style.opacity = '0'; toast.style.transform = 'translateY(12px)';
            setTimeout(function(){ if (toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
        }, 9000);
    }

    function showDesktopNotif(ticket) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        var title = (ticket.concern_type === 'PRIO' ? '🔴 PRIO' : '🔵 NON-PRIO') +
                    ' — New Ticket #' + ticket.id +
                    (ticket.campaign ? ' [' + ticket.campaign + ']' : '');
        try {
            var n = new Notification(title, {
                body: 'Submitted by ' + ticket.submitted_by + '\n' + ticket.concern,
                requireInteraction: false, tag: 'tkt-' + ticket.id
            });
            n.onclick = function(){ window.focus(); window.location.href = 'ticket_view.php?id=' + ticket.id; n.close(); };
            setTimeout(function(){ n.close(); }, 9000);
        } catch(e){}
    }

    // ── DOM rendering ────────────────────────────────────────────────────────
    function renderCount(el, newVal) {
        var old = parseInt(el.textContent, 10);
        el.textContent = newVal;
        if (newVal !== old) {
            el.classList.remove('bump');
            void el.offsetWidth; // reflow
            el.classList.add('bump');
        }
    }

    function renderGrid(campaigns) {
        var grid  = document.getElementById('campaigns-grid');
        var empty = document.getElementById('empty-msg');
        var keys  = Object.keys(campaigns).sort();

        if (keys.length === 0) {
            grid.style.display  = 'none';
            empty.style.display = 'block';
            return;
        }
        grid.style.display  = '';
        empty.style.display = 'none';

        keys.forEach(function(camp) {
            var d   = campaigns[camp];
            var id  = 'camp-' + camp.replace(/[^a-zA-Z0-9]/g, '_');
            var existing = document.getElementById(id);
            var total = 0;
            ['PRIO','NON-PRIO'].forEach(function(t){ total += d[t]['Open'] + d[t]['In Progress'] + d[t]['Resolved']; });

            if (!existing) {
                // Create card
                var card = document.createElement('div');
                card.className = 'campaign-card';
                card.id = id;
                card.innerHTML =
                    '<div class="campaign-header">' +
                        '<h2>' + escHtml(camp || '(No Campaign)') + '</h2>' +
                        '<span class="campaign-total" id="' + id + '-total">' + total + ' ticket' + (total !== 1 ? 's' : '') + '</span>' +
                    '</div>' +
                    '<div class="campaign-body">' +
                        buildTypeSection('PRIO', id, d['PRIO']) +
                        '<div class="divider"></div>' +
                        buildTypeSection('NON-PRIO', id, d['NON-PRIO']) +
                    '</div>';
                grid.appendChild(card);
            } else {
                // Update counts in place
                var changed = false;
                ['PRIO','NON-PRIO'].forEach(function(type) {
                    var prefix = id + '-' + type.replace('-','');
                    updateCount(prefix + '-open', d[type]['Open'])   && (changed = true);
                    updateCount(prefix + '-prog', d[type]['In Progress']) && (changed = true);
                    updateCount(prefix + '-res',  d[type]['Resolved'])  && (changed = true);
                });
                var totEl = document.getElementById(id + '-total');
                if (totEl) totEl.textContent = total + ' ticket' + (total !== 1 ? 's' : '');
                if (changed) {
                    existing.classList.add('updated');
                    setTimeout(function(){ existing.classList.remove('updated'); }, 2000);
                }
            }
        });
    }

    function updateCount(elId, newVal) {
        var el = document.getElementById(elId);
        if (!el) return false;
        var old = parseInt(el.textContent, 10);
        if (newVal === old) return false;
        el.textContent = newVal;
        el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
        return true;
    }

    function buildTypeSection(type, id, counts) {
        var cls    = type === 'PRIO' ? 'prio' : 'nonprio';
        var prefix = id + '-' + type.replace('-','');
        return '<div class="type-section">' +
            '<span class="type-label ' + cls + '">' + type + '</span>' +
            '<div class="status-rows">' +
                '<div class="status-row"><span class="status-name">Open</span><span id="' + prefix + '-open" class="status-count open">' + counts['Open'] + '</span></div>' +
                '<div class="status-row"><span class="status-name">In Progress</span><span id="' + prefix + '-prog" class="status-count prog">' + counts['In Progress'] + '</span></div>' +
                '<div class="status-row"><span class="status-name">Resolved</span><span id="' + prefix + '-res" class="status-count res">' + counts['Resolved'] + '</span></div>' +
            '</div>' +
        '</div>';
    }

    function escHtml(s) {
        var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML;
    }

    function fmtTime(d) {
        return d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    var lastNotifId  = state.maxId;
    var notifInitialized = false;

    function pollCampaigns() {
        fetch('ticket_notify.php?mode=campaign_data', { credentials: 'same-origin' })
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(data){
                if (!data) return;
                renderGrid(data.campaigns);

                // Handle notifications for new tickets
                if (!notifInitialized) {
                    lastNotifId = data.maxId;
                    notifInitialized = true;
                } else if (data.maxId > lastNotifId) {
                    // Fetch new tickets for notification details
                    fetch('ticket_notify.php?mode=notify&last_id=' + lastNotifId, { credentials: 'same-origin' })
                        .then(function(r2){ return r2.ok ? r2.json() : null; })
                        .then(function(nd){
                            if (!nd || nd.newCount === 0) return;
                            lastNotifId = nd.maxId;
                            playBeep();
                            nd.tickets.forEach(function(t){
                                showToast(t);
                                showDesktopNotif(t);
                            });
                        }).catch(function(){});
                }

                document.getElementById('last-updated').textContent = 'Updated ' + fmtTime(new Date());
            })
            .catch(function(){
                document.getElementById('last-updated').textContent = 'Connection error — retrying…';
            });
    }

    // Initial render from PHP seed data, then poll
    renderGrid(state.campaigns);
    document.getElementById('last-updated').textContent = 'Updated ' + fmtTime(new Date());
    setInterval(pollCampaigns, 10000);
    // Also poll immediately after 2s to confirm sync
    setTimeout(pollCampaigns, 2000);
    </script>
</body>
</html>
