<?php
// login.php
require_once 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM TBUsers WHERE Username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && !empty($user['Password']) && password_verify($password, $user['Password'])) {
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['role'] = $user['Role'] ?? 'user';
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — AnkerGames</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --brand: #4f46e5;
            --brand-hover: #4338ca;
            --brand-light: #eef2ff;
            --brand-border: #c7d2fe;
            --bg: #f9fafb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #111827;
            --text-muted: #6b7280;
            --text-subtle: #9ca3af;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --shadow-sm: 0 1px 2px rgba(17,24,39,0.05);
            --shadow: 0 1px 3px rgba(17,24,39,0.08), 0 1px 2px rgba(17,24,39,0.04);
            --shadow-lg: 0 20px 25px -5px rgba(17,24,39,0.08), 0 8px 10px -6px rgba(17,24,39,0.04);
        }
        * { -webkit-font-smoothing: antialiased; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-shell {
            display: grid;
            grid-template-columns: 480px 1fr;
            min-height: 100vh;
        }
        @media (max-width: 960px) {
            .auth-shell { grid-template-columns: 1fr; }
            .auth-brand { display: none !important; }
        }

        /* ---------- BRAND PANEL ---------- */
        .auth-brand {
            background: #0b1220;
            color: #e2e8f0;
            padding: 44px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .auth-brand::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 20%, rgba(99,102,241,0.28), transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(79,70,229,0.20), transparent 45%);
            pointer-events: none;
        }
        .auth-brand::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .auth-brand > * { position: relative; z-index: 1; }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 17px;
            letter-spacing: -0.01em;
            color: #fff;
        }
        .brand-logo-mark {
            width: 36px; height: 36px;
            border-radius: 9px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            display: grid; place-items: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(99,102,241,0.4);
        }

        .brand-headline {
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -0.03em;
            line-height: 1.2;
            color: #fff;
            margin: 0 0 16px;
        }
        .brand-sub {
            color: #94a3b8;
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 32px;
            max-width: 360px;
        }
        .brand-features {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .brand-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            font-size: 14px;
        }
        .brand-features li i {
            color: #a5b4fc;
            font-size: 16px;
            flex: 0 0 auto;
        }
        .brand-footer {
            color: #64748b;
            font-size: 12.5px;
        }

        /* ---------- FORM PANEL ---------- */
        .auth-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            background: var(--surface);
        }
        .form-wrapper {
            width: 100%;
            max-width: 400px;
        }
        .mobile-brand {
            display: none;
            margin-bottom: 32px;
        }
        @media (max-width: 960px) {
            .mobile-brand { display: flex; }
        }
        .mobile-brand-mark {
            width: 44px; height: 44px;
            border-radius: 11px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            display: grid; place-items: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }

        .form-headline {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin: 0 0 8px;
            color: var(--text);
        }
        .form-sub {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0 0 32px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            width: 100%;
            border-radius: 9px;
            border: 1px solid var(--border);
            padding: 11px 14px;
            font-size: 14px;
            background: #fff;
            color: var(--text);
            transition: border-color .15s, box-shadow .15s;
            font-family: inherit;
        }
        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(79,70,229,0.10);
            outline: none;
        }
        .form-control::placeholder { color: var(--text-subtle); }

        .btn-primary {
            background: var(--brand);
            border: 1px solid var(--brand);
            color: #fff;
            border-radius: 9px;
            padding: 11px 18px;
            width: 100%;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            box-shadow: 0 1px 2px rgba(79,70,229,0.20);
            transition: background .15s, border-color .15s, box-shadow .15s;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: var(--brand-hover);
            border-color: var(--brand-hover);
            box-shadow: 0 2px 4px rgba(79,70,229,0.25);
        }

        .alert-custom {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 13.5px;
            margin-bottom: 24px;
            border: 1px solid var(--danger-border);
            background: var(--danger-bg);
            color: #991b1b;
        }
        .alert-custom i { color: var(--danger); font-size: 16px; margin-top: 1px; flex: 0 0 auto; }

        .form-footer {
            text-align: center;
            margin: 28px 0 0;
            color: var(--text-muted);
            font-size: 13.5px;
        }
        .form-footer a {
            color: var(--brand);
            font-weight: 500;
            text-decoration: none;
            margin-left: 4px;
        }
        .form-footer a:hover { text-decoration: underline; }

        .mb-3 { margin-bottom: 18px; }
        .mb-4 { margin-bottom: 24px; }
    </style>
</head>
<body>
<div class="auth-shell">

    <aside class="auth-brand">
        <div class="brand-logo">
            <div class="brand-logo-mark"><i class="bi bi-controller"></i></div>
            <span>AnkerGames</span>
        </div>

        <div>
            <h1 class="brand-headline">Manage your game library with confidence.</h1>
            <p class="brand-sub">Everything you need to track games, publishers, and user libraries — in one place.</p>
            <ul class="brand-features">
                <li><i class="bi bi-check-circle-fill"></i> Track and manage your full catalogue</li>
                <li><i class="bi bi-check-circle-fill"></i> Organize publishers and their releases</li>
                <li><i class="bi bi-check-circle-fill"></i> Monitor user libraries and playtime</li>
            </ul>
        </div>

        <div class="brand-footer">© 2026 AnkerGames. All rights reserved.</div>
    </aside>

    <main class="auth-form-panel">
        <div class="form-wrapper">

            <div class="mobile-brand">
                <div class="mobile-brand-mark"><i class="bi bi-controller"></i></div>
            </div>

            <h2 class="form-headline">Sign in to your account</h2>
            <p class="form-sub">Welcome back. Enter your credentials to continue.</p>

            <?php if ($error): ?>
                <div class="alert-custom">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-primary">Sign in</button>
            </form>

            <p class="form-footer">
                Don't have an account? <a href="register.php">Create one</a>
            </p>
        </div>
    </main>
</div>
</body>
</html>