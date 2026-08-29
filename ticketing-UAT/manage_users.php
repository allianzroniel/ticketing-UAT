<?php
require 'auth_check.php';
requireRole(['admin', 'super_admin']);
require 'db_config.php';
require 'site_config.php';

$currentRole = $_SESSION['role'];
$isSuperAdmin = $currentRole === 'super_admin';

// Roles this account is allowed to assign/manage
$manageableRoles = $isSuperAdmin ? ['user', 'admin', 'super_admin'] : ['user'];

$error = '';
$success = '';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';
        $site     = trim($_POST['site'] ?? '');

        if ($username === '' || $password === '' || $role === '') {
            $error = 'All fields are required.';
        } elseif ($role !== 'super_admin' && $site === '') {
            $error = 'Please assign a site for this user role.';
        } elseif ($role !== 'super_admin' && !in_array($site, $SITE_OPTIONS, true)) {
            $error = 'Invalid site selected.';
        } elseif (!in_array($role, $manageableRoles, true)) {
            $error = 'You are not allowed to create users with that role.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check for duplicate username
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'That username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                if ($role === 'super_admin') {
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, site) VALUES (?, ?, ?, NULL)");
                    $stmt->bind_param("sss", $username, $hash, $role);
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, site) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $hash, $role, $site);
                }
                if ($stmt->execute()) {
                    $success = "User '{$username}' created with role '{$role}'" . ($role === 'super_admin' ? '.' : " and site '{$site}'.");
                } else {
                    $error = 'Failed to create user.';
                }
                $stmt->close();
            }
            $check->close();
        }

    } elseif ($action === 'change_password') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($newPassword === '' || strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            // Verify target user's role is within scope
            $check = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
            $check->bind_param("i", $targetId);
            $check->execute();
            $target = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$target) {
                $error = 'User not found.';
            } elseif (!in_array($target['role'], $manageableRoles, true)) {
                $error = 'You are not allowed to manage this user.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $targetId);
                if ($stmt->execute()) {
                    $success = "Password updated for '{$target['username']}'.";
                } else {
                    $error = 'Failed to update password.';
                }
                $stmt->close();
            }
        }

    } elseif ($action === 'update_site') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newSite = trim($_POST['site'] ?? '');

        if (!$isSuperAdmin) {
            $error = 'Only the super admin can change site assignments.';
        } else {
            $check = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
            $check->bind_param("i", $targetId);
            $check->execute();
            $target = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$target) {
                $error = 'User not found.';
            } elseif ($target['role'] === 'super_admin') {
                $error = 'Super admin site assignment cannot be changed here.';
            } elseif (!in_array($newSite, $SITE_OPTIONS, true)) {
                $error = 'Please choose a valid site.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET site = ? WHERE id = ?");
                $stmt->bind_param("si", $newSite, $targetId);
                if ($stmt->execute()) {
                    $success = "Site updated for '{$target['username']}' to '{$newSite}'.";
                } else {
                    $error = 'Failed to update site assignment.';
                }
                $stmt->close();
            }
        }

    } elseif ($action === 'delete') {
        $targetId = (int) ($_POST['user_id'] ?? 0);

        if ($targetId === (int) $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $check = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
            $check->bind_param("i", $targetId);
            $check->execute();
            $target = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$target) {
                $error = 'User not found.';
            } elseif (!in_array($target['role'], $manageableRoles, true)) {
                $error = 'You are not allowed to delete this user.';
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $targetId);
                if ($stmt->execute()) {
                    $success = "User '{$target['username']}' deleted.";
                } else {
                    $error = 'Failed to delete user. They may have existing tickets linked to their account.';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch users within scope
if ($isSuperAdmin) {
    $usersResult = $conn->query("SELECT id, username, role, site, created_at FROM users ORDER BY role, username");
} else {
    $usersResult = $conn->query("SELECT id, username, role, site, created_at FROM users WHERE role = 'user' ORDER BY username");
}

$conn->close();

function roleBadge($role) {
    $map = [
        'user' => 'badge-user',
        'admin' => 'badge-admin',
        'super_admin' => 'badge-super',
    ];
    $labels = [
        'user' => 'User',
        'admin' => 'Admin',
        'super_admin' => 'Super Admin',
    ];
    $class = $map[$role] ?? 'badge-user';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($labels[$role] ?? $role) . '</span>';
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
    <title>Manage Users — SyncDesk</title>
    <style>
        main { max-width: 1100px; margin: 0 auto; padding: 40px; }
        .grid-2 { display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start; }
        .row-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .row-actions form { display: flex; gap: 6px; align-items: center; }
        .row-actions input[type="password"] { width: 140px; padding: 6px 10px; font-size: 12px; }
        .empty { color: var(--text-dim); font-size: 14px; padding: 20px 0; text-align: center; }
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
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
            <a href="admin_dashboard.php" class="btn btn-ghost">Ticket Queue</a>
            <?php if ($isSuperAdmin): ?>
                <a href="super_admin_dashboard.php" class="btn btn-ghost">Analytics</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-ghost">Log out</a>
        </nav>
    </header>

    <main>
        <div class="page-head">
            <h1>Manage Users</h1>
            <p>
                <?php if ($isSuperAdmin): ?>
                    Create, update, and remove accounts across all roles.
                <?php else: ?>
                    Create, update, and remove standard user accounts.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="card">
                <h2>Create New Account</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <?php foreach ($manageableRoles as $r): ?>
                                <option value="<?php echo $r; ?>">
                                    <?php
                                        $labels = ['user' => 'User', 'admin' => 'Admin', 'super_admin' => 'Super Admin'];
                                        echo $labels[$r];
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="site">Site</label>
                        <select id="site" name="site">
                            <option value="">— Select a site —</option>
                            <?php foreach ($SITE_OPTIONS as $siteOption): ?>
                                <option value="<?php echo htmlspecialchars($siteOption); ?>"><?php echo htmlspecialchars($siteOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
                </form>
            </div>

            <div class="card">
                <h2>Existing Accounts</h2>
                <?php if ($usersResult->num_rows === 0): ?>
                    <div class="empty">No accounts found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Site</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $usersResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo roleBadge($u['role']); ?></td>
                                <td><?php echo htmlspecialchars($u['site'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($u['created_at']))); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="change_password">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="password" name="new_password" placeholder="New password" minlength="6" required>
                                            <button type="submit" class="btn btn-ghost btn-sm">Update</button>
                                        </form>
                                        <?php if ($isSuperAdmin && ($u['role'] ?? 'user') !== 'super_admin'): ?>
                                        <form method="POST" action="" style="margin-top: 6px;">
                                            <input type="hidden" name="action" value="update_site">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <select name="site" style="width: 140px; margin-right: 6px;">
                                                <option value="">— Select site —</option>
                                                <?php foreach ($SITE_OPTIONS as $siteOption): ?>
                                                    <option value="<?php echo htmlspecialchars($siteOption); ?>" <?php echo (($u['site'] ?? '') === $siteOption) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($siteOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-ghost btn-sm">Set Site</button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Delete user &quot;<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>&quot;? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
