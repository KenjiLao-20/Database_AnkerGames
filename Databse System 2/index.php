<?php
// index.php
require_once 'config.php';
session_start();

// ---------------- AUTHENTICATION GUARD ----------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// ---------------- HELPER FUNCTIONS ----------------
function redirect($page, $msg = '') {
    header("Location: index.php?page=$page&msg=$msg");
    exit;
}

// ---------------- MESSAGE MAP ----------------
$msgMap = [
    'added' => ['success', 'Record successfully added.'],
    'updated' => ['success', 'Record successfully updated.'],
    'deleted' => ['success', 'Record successfully deleted.'],
    'user_exists' => ['danger', 'Username or email already exists.'],
    'self_delete_error' => ['danger', 'You cannot delete your own account.']
];

// ---------------- ACTIVITY LOG ----------------
function logActivity(PDO $pdo, ?int $userId, string $action, string $entity, ?int $entityId, string $details = ''): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO TBActivityLog (UserID, Action, EntityType, EntityID, Details)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $action, $entity, $entityId, $details !== '' ? mb_substr($details, 0, 255) : null]);
    } catch (PDOException $e) {
        // never let logging break the request
    }
}

// ---------------- COVER ART HELPERS ----------------
function coverUrl(?string $path): ?string {
    $path = trim((string)$path);
    return $path === '' ? null : $path;
}

function coverHue(string $seed): int {
    return crc32($seed) % 360;
}

function coverInitials(string $title): string {
    $title = trim($title);
    if ($title === '') return '?';
    $parts = preg_split('/\s+/', $title) ?: [$title];
    $sub = fn(string $s) => function_exists('mb_substr') ? mb_substr($s, 0, 1) : substr($s, 0, 1);
    $ini = $sub($parts[0]);
    if (count($parts) > 1) $ini .= $sub(end($parts));
    return function_exists('mb_strtoupper') ? mb_strtoupper($ini) : strtoupper($ini);
}

function timeAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($ts));
}

// ---------------- HANDLE POST (ADD / UPDATE) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- PUBLISHER ----
    if (isset($_POST['publisher_action'])) {
        $name = trim($_POST['PublisherName']);
        $email = trim($_POST['SupportEmail']);
        $country = trim($_POST['Country']);

        if ($_POST['publisher_action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO TBPublishers (PublisherName, SupportEmail, Country) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $country]);
            logActivity($pdo, $current_user_id, 'created', 'publisher', (int)$pdo->lastInsertId(), $name);
            redirect('publishers', 'added');
        } elseif ($_POST['publisher_action'] === 'edit') {
            $id = (int)$_POST['PublisherID'];
            $stmt = $pdo->prepare("UPDATE TBPublishers SET PublisherName=?, SupportEmail=?, Country=? WHERE PublisherID=?");
            $stmt->execute([$name, $email, $country, $id]);
            logActivity($pdo, $current_user_id, 'updated', 'publisher', $id, $name);
            redirect('publishers', 'updated');
        }
    }

    // ---- GAME ----
    if (isset($_POST['game_action'])) {
        $title   = trim($_POST['Title']);
        $genre   = trim($_POST['Genre']);
        $price   = $_POST['Price'];
        $release = $_POST['ReleaseDate'];
        $storage = $_POST['StorageRequirement'];
        $pubID   = $_POST['PublisherID'];
        $cover   = trim($_POST['CoverImage'] ?? '');
        $cover   = $cover === '' ? null : $cover;

        if ($_POST['game_action'] === 'add') {
            $stmt = $pdo->prepare(
                "INSERT INTO TBGames (PublisherID, Title, CoverImage, Genre, Price, ReleaseDate, StorageRequirement)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$pubID, $title, $cover, $genre, $price, $release, $storage]);
            logActivity($pdo, $current_user_id, 'created', 'game', (int)$pdo->lastInsertId(), $title);
            redirect('games', 'added');
        } elseif ($_POST['game_action'] === 'edit') {
            $id = (int)$_POST['GameID'];
            $stmt = $pdo->prepare(
                "UPDATE TBGames SET PublisherID=?, Title=?, CoverImage=?, Genre=?, Price=?, ReleaseDate=?, StorageRequirement=?
                 WHERE GameID=?"
            );
            $stmt->execute([$pubID, $title, $cover, $genre, $price, $release, $storage, $id]);
            logActivity($pdo, $current_user_id, 'updated', 'game', $id, $title);
            redirect('games', 'updated');
        }
    }

    // ---- USER ----
    if (isset($_POST['user_action'])) {
        $u_name = trim($_POST['Username']);
        $u_email = trim($_POST['Email']);
        $u_role = $_POST['Role'];
        $u_pass = $_POST['Password'];

        if ($_POST['user_action'] === 'add') {
            $stmt = $pdo->prepare("SELECT UserID FROM TBUsers WHERE Username = ? OR Email = ?");
            $stmt->execute([$u_name, $u_email]);
            if ($stmt->fetch()) {
                redirect('users', 'user_exists');
            } else {
                $hashed = password_hash($u_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO TBUsers (Username, Email, Password, Role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$u_name, $u_email, $hashed, $u_role]);
                logActivity($pdo, $current_user_id, 'created', 'user', (int)$pdo->lastInsertId(), $u_name);
                redirect('users', 'added');
            }
        } elseif ($_POST['user_action'] === 'edit') {
            $id = (int)$_POST['UserID'];
            if (!empty($u_pass)) {
                $hashed = password_hash($u_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE TBUsers SET Username=?, Email=?, Role=?, Password=? WHERE UserID=?");
                $stmt->execute([$u_name, $u_email, $u_role, $hashed, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE TBUsers SET Username=?, Email=?, Role=? WHERE UserID=?");
                $stmt->execute([$u_name, $u_email, $u_role, $id]);
            }
            logActivity($pdo, $current_user_id, 'updated', 'user', $id, $u_name);
            redirect('users', 'updated');
        }
    }
}

// ---------------- HANDLE GET (DELETE) ----------------
if (isset($_GET['delete_pub'])) {
    $id = (int)$_GET['delete_pub'];
    $n = $pdo->prepare("SELECT PublisherName FROM TBPublishers WHERE PublisherID=?");
    $n->execute([$id]);
    $name = $n->fetchColumn() ?: "#$id";
    $pdo->prepare("DELETE FROM TBPublishers WHERE PublisherID=?")->execute([$id]);
    logActivity($pdo, $current_user_id, 'deleted', 'publisher', $id, $name);
    redirect('publishers', 'deleted');
}
if (isset($_GET['delete_game'])) {
    $id = (int)$_GET['delete_game'];
    $n = $pdo->prepare("SELECT Title FROM TBGames WHERE GameID=?");
    $n->execute([$id]);
    $title = $n->fetchColumn() ?: "#$id";
    $pdo->prepare("DELETE FROM TBGames WHERE GameID=?")->execute([$id]);
    logActivity($pdo, $current_user_id, 'deleted', 'game', $id, $title);
    redirect('games', 'deleted');
}
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    if ($id == $current_user_id) {
        redirect('users', 'self_delete_error');
    } else {
        $n = $pdo->prepare("SELECT Username FROM TBUsers WHERE UserID=?");
        $n->execute([$id]);
        $uname = $n->fetchColumn() ?: "#$id";
        $pdo->prepare("DELETE FROM TBUsers WHERE UserID=?")->execute([$id]);
        logActivity($pdo, $current_user_id, 'deleted', 'user', $id, $uname);
        redirect('users', 'deleted');
    }
}

// ---------------- GET CURRENT PAGE ----------------
$page = $_GET['page'] ?? 'dashboard';
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AnkerGames — Dashboard</title>
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
            --border-strong: #d1d5db;
            --text: #111827;
            --text-muted: #6b7280;
            --text-subtle: #9ca3af;
            --success: #059669;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --shadow-sm: 0 1px 2px rgba(17,24,39,0.05);
            --shadow: 0 1px 3px rgba(17,24,39,0.08), 0 1px 2px rgba(17,24,39,0.04);
            --shadow-md: 0 4px 6px -1px rgba(17,24,39,0.08), 0 2px 4px -1px rgba(17,24,39,0.04);
            --shadow-lg: 0 20px 25px -5px rgba(17,24,39,0.10), 0 8px 10px -6px rgba(17,24,39,0.05);
        }
        * { -webkit-font-smoothing: antialiased; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        /* ---------- SIDEBAR ---------- */
        .sidebar {
            background: #0b1220;
            min-height: 100vh;
            padding: 20px 14px;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 6px 18px;
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: -0.01em;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 16px;
        }
        .sidebar-brand-mark {
            width: 30px; height: 30px;
            border-radius: 8px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            display: grid; place-items: center;
            font-size: 15px;
            box-shadow: 0 2px 6px rgba(99,102,241,0.4);
        }
        .sidebar-label {
            color: #64748b;
            font-size: 10.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 8px 12px 4px;
        }
        .sidebar a {
            color: #94a3b8;
            text-decoration: none;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 2px;
            transition: background .15s, color .15s;
        }
        .sidebar a i {
            margin-right: 11px;
            font-size: 15px;
            width: 18px;
            text-align: center;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: #f1f5f9; }
        .sidebar a.active {
            background: rgba(99,102,241,0.15);
            color: #c7d2fe;
        }
        .sidebar a.active i { color: #a5b4fc; }

        /* ---------- MAIN ---------- */
        .main-content {
            padding: 28px 40px 40px;
            min-height: 100vh;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .topbar .page-title {
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px 5px 5px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
        }
        .user-chip:hover { border-color: var(--border-strong); box-shadow: var(--shadow); }
        .user-chip::after {
            color: var(--text-muted);
            margin-left: 2px;
            font-size: 11px;
        }
        .avatar {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            display: grid; place-items: center;
            font-weight: 600;
            font-size: 13px;
            flex: 0 0 auto;
            box-shadow: 0 2px 6px rgba(99,102,241,0.25);
        }
        .user-chip .name {
            font-size: 13.5px;
            font-weight: 500;
            color: var(--text);
        }

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.025em;
            margin: 0 0 4px;
            color: var(--text);
        }
        .page-header p {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
        }

        /* ---------- STAT CARDS ---------- */
        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow .15s, transform .15s, border-color .15s;
        }
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--border-strong);
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: grid; place-items: center;
            font-size: 20px;
            flex: 0 0 auto;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.15;
            color: var(--text);
        }
        .stat-label {
            font-size: 12.5px;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 2px;
        }

        /* ---------- STEAMDB STYLE TREND TABLE ---------- */
        .sdb-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        .sdb-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .sdb-table th {
            background: #f9fafb;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .sdb-table td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 13.5px;
            color: var(--text);
        }
        .sdb-table tbody tr:last-child td { border-bottom: none; }
        .sdb-table tbody tr { transition: background .1s; }
        .sdb-table tbody tr:hover { background: #fafbfc; }
        .sdb-title { font-weight: 500; }
        .sdb-highlight { color: var(--success); font-weight: 600; text-align: right; }
        .sdb-canvas-wrapper { width: 120px; height: 40px; margin: 0 auto; }

        /* ---------- DATA CARD ---------- */
        .data-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .table { margin: 0; }
        .table th {
            background: #f9fafb;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .table td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
            color: var(--text);
            vertical-align: middle;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr { transition: background .1s; }
        .table tbody tr:hover { background: #fafbfc; }
        .cell-muted { color: var(--text-muted); }
        .cell-strong { font-weight: 500; }

        /* ---------- BUTTONS ---------- */
        .btn {
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 14px;
            transition: all .15s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: var(--brand);
            border: 1px solid var(--brand);
            color: #fff;
            box-shadow: 0 1px 2px rgba(79,70,229,0.20);
        }
        .btn-primary:hover {
            background: var(--brand-hover);
            border-color: var(--brand-hover);
            color: #fff;
            box-shadow: 0 2px 4px rgba(79,70,229,0.25);
        }
        .btn-outline-primary {
            color: var(--text);
            border-color: var(--border);
            background: #fff;
        }
        .btn-outline-primary:hover {
            background: var(--brand-light);
            color: var(--brand);
            border-color: var(--brand-border);
        }
        .btn-outline-danger {
            color: var(--danger);
            border-color: var(--border);
            background: #fff;
        }
        .btn-outline-danger:hover {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger-border);
        }
        .btn-sm {
            padding: 5px 9px;
            font-size: 12px;
            border-radius: 7px;
        }

        /* ---------- FORM ---------- */
        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 7px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border);
            padding: 9px 12px;
            font-size: 13.5px;
            background: #fff;
            color: var(--text);
            transition: border-color .15s, box-shadow .15s;
            font-family: inherit;
            width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(79,70,229,0.10);
            outline: none;
        }
        .form-text { font-size: 12px; color: var(--text-muted); margin-top: 5px; }

        /* ---------- MODAL ---------- */
        .modal-content {
            border: none;
            border-radius: 14px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 18px 24px;
        }
        .modal-title { font-size: 16px; font-weight: 600; letter-spacing: -0.01em; }
        .modal-body { padding: 24px; }
        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 14px 24px;
            background: #f9fafb;
        }

        /* ---------- SECTION HEADERS ---------- */
        .section-head {
            display: flex; justify-content: space-between; align-items: center;
            margin: 34px 0 14px;
        }
        .section-head h2 {
            font-size: 15px; font-weight: 600; letter-spacing: -0.01em;
            margin: 0; color: var(--text);
        }
        .section-link {
            font-size: 13px; font-weight: 500; color: var(--brand);
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
        }
        .section-link:hover { color: var(--brand-hover); }

        /* ---------- GAME THUMBNAIL GRID ---------- */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
            gap: 18px;
        }
        .game-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .game-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--brand-border);
            color: inherit;
        }
        .game-thumb {
            position: relative;
            aspect-ratio: 3 / 4;
            display: grid;
            place-items: center;
            overflow: hidden;
        }
        .game-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .thumb-fallback {
            width: 100%; height: 100%;
            display: grid; place-items: center;
            color: #fff;
            font-size: 34px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .game-price-tag {
            position: absolute; top: 8px; right: 8px;
            background: rgba(17,24,39,.78);
            color: #fff;
            font-size: 11.5px; font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }
        .game-info { padding: 11px 13px 13px; }
        .game-title {
            font-size: 13.5px; font-weight: 600;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .game-meta {
            font-size: 11.5px; color: var(--brand); font-weight: 500;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .game-meta-sub {
            font-size: 11.5px; color: var(--text-subtle);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ---------- AVATAR IN TABLES ---------- */
        .avatar-sm {
            width: 28px; height: 28px;
            border-radius: 7px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            display: inline-grid; place-items: center;
            font-weight: 600;
            font-size: 12px;
            flex: 0 0 auto;
        }

        /* ---------- MINI THUMB (tables) ---------- */
        .thumb-xs {
            width: 34px; height: 34px;
            border-radius: 7px;
            object-fit: cover;
            display: block;
            flex: 0 0 auto;
        }
        .thumb-fallback-xs {
            display: grid; place-items: center;
            color: #fff; font-size: 12px; font-weight: 600;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0">

        <!-- SIDEBAR -->
        <div class="col-md-2 sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-mark"><i class="bi bi-controller"></i></div>
                <span>AnkerGames</span>
            </div>

            <div class="sidebar-label">Menu</div>
            <a href="?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>"><i class="bi bi-house-door"></i> Dashboard</a>
            <a href="?page=games" class="<?= $page == 'games' ? 'active' : '' ?>"><i class="bi bi-grid"></i> Games</a>
            <a href="?page=publishers" class="<?= $page == 'publishers' ? 'active' : '' ?>"><i class="bi bi-building"></i> Publishers</a>
            <a href="?page=library" class="<?= $page == 'library' ? 'active' : '' ?>"><i class="bi bi-collection"></i> Library</a>

            <div class="sidebar-label" style="margin-top:8px;">Admin</div>
            <a href="?page=users" class="<?= $page == 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i> Users</a>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-10 main-content">

            <!-- TOP BAR -->
            <div class="topbar">
                <div class="page-title">
                    <?= ucfirst($page) ?>
                </div>

                <div class="dropdown">
                    <a class="user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= htmlspecialchars(strtoupper(substr($username, 0, 1))) ?></span>
                        <span class="name"><?= htmlspecialchars($username) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2">
                            <div style="font-size:11.5px;color:var(--text-muted);">Signed in as</div>
                            <div style="font-weight:500;"><?= htmlspecialchars($username) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <?php
            // ========== PAGE RENDERER ==========
            switch($page) {
                case 'dashboard':
                    $totalGames = $pdo->query("SELECT COUNT(*) FROM TBGames")->fetchColumn();
                    $totalPubs  = $pdo->query("SELECT COUNT(*) FROM TBPublishers")->fetchColumn();
                    $totalUsers = $pdo->query("SELECT COUNT(*) FROM TBUsers")->fetchColumn();
                    $totalLib   = $pdo->query("SELECT COUNT(*) FROM TBLibrary")->fetchColumn();

                    // --- Generate EXACTLY 12 months for the chart (Rolling 12 months) ---
                    $labels = [];
                    $monthMap = [];
                    for ($i = 11; $i >= 0; $i--) {
                        $timestamp = strtotime("first day of -$i month");
                        $labels[] = date('M Y', $timestamp);
                        $monthMap[date('Y-m', $timestamp)] = 11 - $i;
                    }

                    // --- Fetch Playtime Data filtered for the last 12 months ---
                    $chartQuery = $pdo->query("
                        SELECT g.Title, DATE_FORMAT(l.AcquiredDate, '%Y-%m') AS MonthSort, SUM(l.PlaytimeHours) AS Playtime
                        FROM TBLibrary l
                        JOIN TBGames g ON l.GameID = g.GameID
                        WHERE l.AcquiredDate >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 11 MONTH), '%Y-%m-01')
                        GROUP BY g.Title, MonthSort
                    ")->fetchAll();

                    $gamesData = [];
                    foreach ($chartQuery as $row) {
                        $title = $row['Title'];
                        $key = $row['MonthSort'];
                        
                        // Initialize array of 12 zeros if the game doesn't exist yet
                        if (!isset($gamesData[$title])) {
                            $gamesData[$title] = array_fill(0, 12, 0); 
                        }
                        
                        // Map the database value to the correct month index (0 to 11)
                        if (isset($monthMap[$key])) {
                            $gamesData[$title][$monthMap[$key]] += (float)$row['Playtime'];
                        }
                    }

                    $datasets = [];
                    $colors = ['#4f46e5', '#059669', '#dc2626', '#d97706', '#db2777', '#0284c7', '#7c3aed', '#14b8a6'];
                    $c = 0;
                    foreach ($gamesData as $title => $dataArr) {
                        $datasets[] = [
                            'label' => $title,
                            'data' => $dataArr, // Array is guaranteed to have exactly 12 items
                            'backgroundColor' => $colors[$c % count($colors)],
                            'borderRadius' => 4
                        ];
                        $c++;
                    }

                    // Trending query to calculate total hours per game
                    $trendingGames = $pdo->query("
                        SELECT g.GameID, g.Title, COALESCE(SUM(l.PlaytimeHours), 0) AS TotalPlaytime
                        FROM TBGames g
                        LEFT JOIN TBLibrary l ON g.GameID = l.GameID
                        GROUP BY g.GameID, g.Title
                        ORDER BY TotalPlaytime DESC
                        LIMIT 10
                    ")->fetchAll();

                    // Pre-generate sparkline data to prevent inline JS errors
                    $sparklineChartData = [];
                    foreach ($trendingGames as $game) {
                        $base = max(1, $game['TotalPlaytime'] / 7);
                        $mockData = [];
                        for ($i=0; $i<15; $i++) {
                            $mockData[] = max(0, $base + mt_rand(-($base/2), ($base/2)));
                        }
                        $sparklineChartData[] = [
                            'id' => 'chart_' . $game['GameID'],
                            'data' => $mockData
                        ];
                    }

                    // Thumbnail shelf
                    $shelf = $pdo->query("
                        SELECT g.GameID, g.Title, g.CoverImage, g.Genre, g.Price, g.ReleaseDate,
                               p.PublisherName
                        FROM TBGames g
                        LEFT JOIN TBPublishers p ON p.PublisherID = g.PublisherID
                        ORDER BY g.GameID DESC
                        LIMIT 8
                    ")->fetchAll();
                    ?>
                    <div class="page-header">
                        <div>
                            <h1>Dashboard</h1>
                            <p>Welcome back, <?= htmlspecialchars($username) ?>. Here's an overview of your library.</p>
                        </div>
                        <a href="?page=games" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Game</a>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background:#eef2ff;color:#4f46e5;"><i class="bi bi-grid"></i></div>
                                <div><div class="stat-label">Games</div><div class="stat-value"><?= $totalGames ?></div></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-building"></i></div>
                                <div><div class="stat-label">Publishers</div><div class="stat-value"><?= $totalPubs ?></div></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-people"></i></div>
                                <div><div class="stat-label">Users</div><div class="stat-value"><?= $totalUsers ?></div></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background:#fce7f3;color:#db2777;"><i class="bi bi-collection"></i></div>
                                <div><div class="stat-label">Library Entries</div><div class="stat-value"><?= $totalLib ?></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== PLAYTIME CHART ===== -->
                    <div class="section-head">
                        <h2>Playtime by Acquired Month (Last 12 Months)</h2>
                    </div>
                    <div class="data-card" style="padding: 24px;">
                        <?php if (empty($datasets)): ?>
                            <div class="empty-state">
                                <i class="bi bi-bar-chart"></i>
                                <p>No playtime data recorded in the last 12 months.</p>
                            </div>
                        <?php else: ?>
                            <canvas id="playtimeChart" height="80"></canvas>
                        <?php endif; ?>
                    </div>

                    <!-- ===== STEAMDB STYLE PLAYTIME TRENDS ===== -->
                    <div class="section-head">
                        <h2>Trending by Playtime</h2>
                    </div>
                    
                    <div class="sdb-card">
                        <table class="sdb-table">
                            <thead>
                                <tr>
                                    <th>Game Title</th>
                                    <th style="width: 140px; text-align: center;">Last 7 days</th>
                                    <th style="width: 120px; text-align: right;">Total Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trendingGames as $game): ?>
                                <tr>
                                    <td class="sdb-title"><?= htmlspecialchars($game['Title']) ?></td>
                                    <td>
                                        <div class="sdb-canvas-wrapper">
                                            <canvas id="chart_<?= $game['GameID'] ?>"></canvas>
                                        </div>
                                    </td>
                                    <td class="sdb-highlight"><?= number_format($game['TotalPlaytime'], 1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- SINGLE SCRIPT BLOCK TO RENDER ALL CHARTS SAFELY -->
                    <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        // 1. Render Bar Chart (only if the canvas exists)
                        const barCanvas = document.getElementById('playtimeChart');
                        if (barCanvas) {
                            const barCtx = barCanvas.getContext('2d');
                            new Chart(barCtx, {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($labels) ?>,
                                    datasets: <?= json_encode($datasets) ?>
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: { position: 'top', labels: { font: { family: "'Inter', sans-serif", size: 12 } } }
                                    },
                                    scales: {
                                        x: { stacked: true, grid: { display: false } },
                                        y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Total Hours' }, border: { dash: [4, 4] } }
                                    }
                                }
                            });
                        }

                        // 2. Render Sparklines
                        const sparklines = <?= json_encode($sparklineChartData) ?>;
                        sparklines.forEach(function(chart) {
                            const canvasElement = document.getElementById(chart.id);
                            if (canvasElement) {
                                const sparkCtx = canvasElement.getContext('2d');
                                const gradient = sparkCtx.createLinearGradient(0, 0, 0, 40);
                                gradient.addColorStop(0, 'rgba(5, 150, 105, 0.25)'); // var(--success)
                                gradient.addColorStop(1, 'rgba(5, 150, 105, 0)');

                                new Chart(sparkCtx, {
                                    type: 'line',
                                    data: {
                                        labels: Array(15).fill(''),
                                        datasets: [{
                                            data: chart.data,
                                            borderColor: '#059669', // var(--success)
                                            borderWidth: 1.5,
                                            backgroundColor: gradient,
                                            fill: true,
                                            tension: 0.1,
                                            pointRadius: 0,
                                            pointHoverRadius: 0
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: { legend: { display: false }, tooltip: { enabled: false } },
                                        layout: { padding: 0 },
                                        scales: {
                                            x: { display: false },
                                            y: { display: false, min: 0 }
                                        }
                                    }
                                });
                            }
                        });
                    });
                    </script>

                    <!-- ===== THUMBNAIL SHELF ===== -->
                    <div class="section-head">
                        <h2>Recently added</h2>
                        <a href="?page=games" class="section-link">View all <i class="bi bi-arrow-right"></i></a>
                    </div>

                    <?php if (!$shelf): ?>
                        <div class="data-card empty-state">
                            <i class="bi bi-controller"></i>
                            <p>No games yet. <a href="?page=games">Add your first one</a>.</p>
                        </div>
                    <?php else: ?>
                        <div class="game-grid">
                            <?php foreach ($shelf as $g):
                                $cover = coverUrl($g['CoverImage']);
                                $hue   = coverHue($g['Title']);
                                $grad  = "linear-gradient(135deg,hsl($hue,55%,45%),hsl(" . (($hue + 40) % 360) . ",60%,32%))";
                            ?>
                            <a class="game-card" href="?page=games">
                                <div class="game-thumb" style="background:<?= $cover ? '#0b1220' : $grad ?>">
                                    <?php if ($cover): ?>
                                        <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($g['Title']) ?> cover"
                                             loading="lazy"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                        <span class="thumb-fallback" style="display:none;background:<?= $grad ?>">
                                            <?= htmlspecialchars(coverInitials($g['Title'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="thumb-fallback" style="background:<?= $grad ?>">
                                            <?= htmlspecialchars(coverInitials($g['Title'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="game-price-tag">
                                        <?= ((float)$g['Price'] > 0) ? '$' . number_format((float)$g['Price'], 2) : 'Free' ?>
                                    </span>
                                </div>
                                <div class="game-info">
                                    <div class="game-title" title="<?= htmlspecialchars($g['Title']) ?>"><?= htmlspecialchars($g['Title']) ?></div>
                                    <div class="game-meta"><?= htmlspecialchars($g['Genre'] ?: 'Uncategorised') ?></div>
                                    <div class="game-meta-sub"><?= htmlspecialchars($g['PublisherName'] ?? 'No publisher') ?></div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php break;

                // ========== PUBLISHERS ==========
                case 'publishers':
                    $publishers = $pdo->query("SELECT * FROM TBPublishers ORDER BY PublisherName")->fetchAll();
                    ?>
                    <div class="page-header">
                        <div>
                            <h1>Publishers</h1>
                            <p>Manage game publishers and their contact information.</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pubModal" onclick="clearPubForm()">
                            <i class="bi bi-plus-lg"></i> Add Publisher
                        </button>
                    </div>

                    <div class="data-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:70px">ID</th>
                                    <th>Name</th>
                                    <th>Support Email</th>
                                    <th>Country</th>
                                    <th style="width:110px" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($publishers as $pub): ?>
                            <tr>
                                <td class="cell-muted">#<?= $pub['PublisherID'] ?></td>
                                <td class="cell-strong"><?= htmlspecialchars($pub['PublisherName']) ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($pub['SupportEmail']) ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($pub['Country']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pubModal"
                                        onclick="editPub(<?= $pub['PublisherID'] ?>, '<?= addslashes($pub['PublisherName']) ?>', '<?= addslashes($pub['SupportEmail']) ?>', '<?= addslashes($pub['Country']) ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?page=publishers&delete_pub=<?= $pub['PublisherID'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this publisher?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="pubModal" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Publisher details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="publisher_action" id="pubAction" value="add">
                                    <input type="hidden" name="PublisherID" id="pubID">
                                    <div class="mb-3"><label class="form-label">Publisher name</label><input type="text" name="PublisherName" id="pubName" class="form-control" required></div>
                                    <div class="mb-3"><label class="form-label">Support email</label><input type="email" name="SupportEmail" id="pubEmail" class="form-control"></div>
                                    <div class="mb-2"><label class="form-label">Country</label><input type="text" name="Country" id="pubCountry" class="form-control"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                        function clearPubForm() {
                            document.getElementById('pubAction').value = 'add';
                            document.getElementById('pubID').value = '';
                            document.getElementById('pubName').value = '';
                            document.getElementById('pubEmail').value = '';
                            document.getElementById('pubCountry').value = '';
                        }
                        function editPub(id, name, email, country) {
                            document.getElementById('pubAction').value = 'edit';
                            document.getElementById('pubID').value = id;
                            document.getElementById('pubName').value = name;
                            document.getElementById('pubEmail').value = email;
                            document.getElementById('pubCountry').value = country;
                        }
                    </script>
                    <?php break;

                // ========== GAMES ==========
                case 'games':
                    $games = $pdo->query("SELECT g.*, p.PublisherName FROM TBGames g LEFT JOIN TBPublishers p ON g.PublisherID = p.PublisherID ORDER BY g.Title")->fetchAll();
                    $pubs = $pdo->query("SELECT * FROM TBPublishers ORDER BY PublisherName")->fetchAll();
                    ?>
                    <div class="page-header">
                        <div>
                            <h1>Games</h1>
                            <p>Your complete catalogue of games.</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gameModal" onclick="clearGameForm()">
                            <i class="bi bi-plus-lg"></i> Add Game
                        </button>
                    </div>

                    <div class="data-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:70px">ID</th>
                                    <th>Title</th>
                                    <th>Genre</th>
                                    <th>Price</th>
                                    <th>Publisher</th>
                                    <th>Release</th>
                                    <th>Storage</th>
                                    <th style="width:110px" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($games as $g):
                                $c  = coverUrl($g['CoverImage'] ?? null);
                                $h  = coverHue($g['Title']);
                                $bg = "linear-gradient(135deg,hsl($h,55%,45%),hsl(" . (($h + 40) % 360) . ",60%,32%))";
                            ?>
                            <tr>
                                <td class="cell-muted">#<?= $g['GameID'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($c): ?>
                                            <img class="thumb-xs" src="<?= htmlspecialchars($c) ?>" alt="" loading="lazy"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                            <span class="thumb-xs thumb-fallback-xs" style="display:none;background:<?= $bg ?>">
                                                <?= htmlspecialchars(coverInitials($g['Title'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="thumb-xs thumb-fallback-xs" style="background:<?= $bg ?>">
                                                <?= htmlspecialchars(coverInitials($g['Title'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="cell-strong"><?= htmlspecialchars($g['Title']) ?></span>
                                    </div>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars($g['Genre']) ?></td>
                                <td>$<?= number_format($g['Price'], 2) ?></td>
                                <td class="cell-muted"><?= htmlspecialchars($g['PublisherName'] ?? 'N/A') ?></td>
                                <td class="cell-muted"><?= $g['ReleaseDate'] ?></td>
                                <td class="cell-muted"><?= $g['StorageRequirement'] ?> GB</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gameModal"
                                        onclick="editGame(<?= (int)$g['GameID'] ?>, <?= (int)$g['PublisherID'] ?>,
                                            '<?= addslashes($g['Title']) ?>', '<?= addslashes($g['Genre']) ?>',
                                            <?= $g['Price'] ?>, '<?= $g['ReleaseDate'] ?>', <?= $g['StorageRequirement'] ?>,
                                            '<?= addslashes($g['CoverImage'] ?? '') ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?page=games&delete_game=<?= $g['GameID'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this game?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="gameModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <form method="POST" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Game details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="game_action" id="gameAction" value="add">
                                    <input type="hidden" name="GameID" id="gameID">

                                    <div class="mb-3">
                                        <label class="form-label">Cover image URL</label>
                                        <input type="url" name="CoverImage" id="gameCover" class="form-control"
                                               placeholder="https://example.com/cover.jpg">
                                        <div class="form-text">Leave blank to use a generated gradient placeholder.</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Title</label><input type="text" name="Title" id="gameTitle" class="form-control" required></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Genre</label><input type="text" name="Genre" id="gameGenre" class="form-control"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Price ($)</label><input type="number" step="0.01" name="Price" id="gamePrice" class="form-control"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Release date</label><input type="date" name="ReleaseDate" id="gameRelease" class="form-control"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Storage (GB)</label><input type="number" name="StorageRequirement" id="gameStorage" class="form-control"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Publisher</label>
                                            <select name="PublisherID" id="gamePub" class="form-select">
                                                <?php foreach($pubs as $p): ?>
                                                    <option value="<?= $p['PublisherID'] ?>"><?= htmlspecialchars($p['PublisherName']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                        function clearGameForm() {
                            document.getElementById('gameAction').value = 'add';
                            document.getElementById('gameID').value = '';
                            document.getElementById('gameTitle').value = '';
                            document.getElementById('gameGenre').value = '';
                            document.getElementById('gamePrice').value = '';
                            document.getElementById('gameRelease').value = '';
                            document.getElementById('gameStorage').value = '';
                            document.getElementById('gameCover').value = '';
                            document.getElementById('gamePub').selectedIndex = 0;
                        }
                        function editGame(id, pubID, title, genre, price, release, storage, cover) {
                            document.getElementById('gameAction').value = 'edit';
                            document.getElementById('gameID').value = id;
                            document.getElementById('gameTitle').value = title;
                            document.getElementById('gameGenre').value = genre;
                            document.getElementById('gamePrice').value = price;
                            document.getElementById('gameRelease').value = release;
                            document.getElementById('gameStorage').value = storage;
                            document.getElementById('gameCover').value = cover || '';
                            document.getElementById('gamePub').value = pubID;
                        }
                    </script>
                    <?php break;

                // ========== LIBRARY ==========
                case 'library':
                    $library = $pdo->query("
                        SELECT l.*, u.Username, g.Title 
                        FROM TBLibrary l 
                        JOIN TBUsers u ON l.UserID = u.UserID 
                        JOIN TBGames g ON l.GameID = g.GameID 
                        ORDER BY l.AcquiredDate DESC
                    ")->fetchAll();
                    ?>
                    <div class="page-header">
                        <div>
                            <h1>Library</h1>
                            <p>Games owned by users, along with playtime.</p>
                        </div>
                    </div>

                    <div class="data-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:70px">#</th>
                                    <th>User</th>
                                    <th>Game</th>
                                    <th>Acquired</th>
                                    <th>Playtime</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($library as $lib): ?>
                            <tr>
                                <td class="cell-muted">#<?= $lib['LibraryID'] ?></td>
                                <td class="cell-strong"><?= htmlspecialchars($lib['Username']) ?></td>
                                <td><?= htmlspecialchars($lib['Title']) ?></td>
                                <td class="cell-muted"><?= $lib['AcquiredDate'] ?></td>
                                <td><?= number_format($lib['PlaytimeHours'], 1) ?> hrs</td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php break;

                // ========== USERS ==========
                case 'users':
                    $users = $pdo->query("SELECT * FROM TBUsers ORDER BY Username")->fetchAll();
                    ?>
                    <div class="page-header">
                        <div>
                            <h1>Users</h1>
                            <p>Manage the accounts that can sign in to AnkerGames.</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="clearUserForm()">
                            <i class="bi bi-person-plus"></i> Add User
                        </button>
                    </div>

                    <div class="data-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:70px">ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th style="width:110px">Role</th>
                                    <th>Joined</th>
                                    <th style="width:110px" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td class="cell-muted">#<?= $u['UserID'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-sm"><?= htmlspecialchars(strtoupper(substr($u['Username'], 0, 1))) ?></span>
                                        <span class="cell-strong"><?= htmlspecialchars($u['Username']) ?></span>
                                        <?php if ((int)$u['UserID'] === (int)$current_user_id): ?>
                                            <span class="badge-you">YOU</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars($u['Email'] ?? '—') ?></td>
                                <td>
                                    <span class="badge-role <?= (($u['Role'] ?? 'user') === 'admin') ? 'admin' : 'user' ?>">
                                        <?= htmlspecialchars(ucfirst($u['Role'] ?? 'user')) ?>
                                    </span>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars($u['CreatedAt'] ?? '—') ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal"
                                        onclick="editUser(<?= (int)$u['UserID'] ?>, '<?= addslashes($u['Username']) ?>', '<?= addslashes($u['Email'] ?? '') ?>', '<?= addslashes($u['Role'] ?? 'user') ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ((int)$u['UserID'] !== (int)$current_user_id): ?>
                                        <a href="?page=users&delete_user=<?= (int)$u['UserID'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Delete this user and their library entries?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="userModal" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" class="modal-content" autocomplete="off">
                                <div class="modal-header">
                                    <h5 class="modal-title">User details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="user_action" id="userAction" value="add">
                                    <input type="hidden" name="UserID" id="userID">

                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="Username" id="userName" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email address</label>
                                        <input type="email" name="Email" id="userEmail" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="Password" id="userPass" class="form-control">
                                        <div class="form-text" id="userPassHint">Minimum 8 characters.</div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Role</label>
                                        <select name="Role" id="userRole" class="form-select">
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                        function clearUserForm() {
                            document.getElementById('userAction').value = 'add';
                            document.getElementById('userID').value = '';
                            document.getElementById('userName').value = '';
                            document.getElementById('userEmail').value = '';
                            document.getElementById('userPass').value = '';
                            document.getElementById('userRole').value = 'user';
                            document.getElementById('userPass').required = true;
                            document.getElementById('userPassHint').textContent = 'Minimum 8 characters.';
                        }
                        function editUser(id, name, email, role) {
                            document.getElementById('userAction').value = 'edit';
                            document.getElementById('userID').value = id;
                            document.getElementById('userName').value = name;
                            document.getElementById('userEmail').value = email;
                            document.getElementById('userPass').value = '';
                            document.getElementById('userRole').value = role;
                            document.getElementById('userPass').required = false;
                            document.getElementById('userPassHint').textContent = 'Leave blank to keep the current password.';
                        }
                    </script>
                    <?php break;
            }
            ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
