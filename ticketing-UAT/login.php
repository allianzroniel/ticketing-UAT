<?php
session_start();
ob_start();
require 'db_config.php';
require_once 'history.php';

// Prevent caching of the login page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$alreadyLoggedIn = isset($_SESSION['user_id']);

if ($alreadyLoggedIn) {
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id, username, password, role, site FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['site']     = $user['site'] ?? '';

                recordLogHistory($conn, $user['username']);

                // Redirect based on role
                switch ($user['role']) {
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
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="assets/js/page-cache.js"></script>
    <link rel="stylesheet" href="assets/css/base.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1f2937, #374151);
        }

        .login-box {
            background: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 380px;
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #1f2937;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #374151;
            font-weight: 600;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 14px;
            text-align: center;
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <div class="login-box">
        <h2>Sign In</h2>

        <?php if ($alreadyLoggedIn): ?>
            <div class="error" style="background:#e0f2fe;color:#0369a1;">You are already logged in as <?php echo htmlspecialchars($_SESSION['username']); ?>.</div>
            <form method="GET" action="<?php echo htmlspecialchars($_SESSION['role'] === 'super_admin' ? 'super_admin_dashboard.php' : ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php')); ?>">
                <button type="submit">Continue to Dashboard</button>
            </form>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit">Log In</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
