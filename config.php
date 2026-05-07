<?php
// ============================================================
//  config.php — UMU Online Voting System
//  
// ── Database ─────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_USER',    'root');   //
define('DB_PASS',    '');
define('DB_NAME',    'umu_voting');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'UMU Student Guild Voting System');

// ── Base URL (works at root OR in a subfolder like /umu-voting) ─
$_docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_projDir = rtrim(str_replace('\\', '/', __DIR__), '/');
define('BASE', str_replace($_docRoot, '', $_projDir)); // e.g. '' or '/umu-voting'
unset($_docRoot, $_projDir);

// ── PDO singleton 
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $ex) {
        http_response_code(500);
        die('<div style="font-family:monospace;background:#fff0f2;color:#c00;padding:24px;'
          . 'border-left:5px solid #c00;max-width:700px;margin:40px auto">'
          . '<strong>Database connection failed.</strong><br><br>'
          . htmlspecialchars($ex->getMessage())
          . '<br><br>Please edit <strong>config.php</strong> and set the correct '
          . 'DB_HOST, DB_USER, DB_PASS, DB_NAME values.</div>');
    }
    return $pdo;
}

// ── Session 
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

function requireLogin(): void {
    // Ensure any stale photo_path etc. does not break approval flow

    startSession();
    if (empty($_SESSION['user_id'])) {
        redirect('index.php?msg=login_required');
    }

    // Refresh approval status from DB (prevents admin approval not reflecting)
    try {
        $stmt = db()->prepare('SELECT is_approved, has_voted FROM users WHERE id=? AND role="student" LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['is_approved'] = (int)($row['is_approved'] ?? 0);
            $_SESSION['has_voted']   = (bool)($row['has_voted'] ?? false);
        }
    } catch (Throwable $t) {
        // ignore refresh errors
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        redirect('vote.php');
    }
}

function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'          => (int)$_SESSION['user_id'],
        'name'        => (string)($_SESSION['user_name']  ?? ''),
        'email'       => (string)($_SESSION['user_email'] ?? ''),
        'role'        => (string)($_SESSION['role']        ?? 'student'),
        'has_voted'   => (bool)($_SESSION['has_voted']     ?? false),
        'is_approved' => (int)($_SESSION['is_approved'] ?? 0),
        'photo_path'  => (string)($_SESSION['photo_path'] ?? ''),
    ];
}


// ── Redirect helper 
function redirect(string $path): void {
    // $path is relative to project root, e.g. 'index.php' or 'admin/dashboard.php'
    header('Location: ' . BASE . '/' . ltrim($path, '/'));
    exit;
}

// ── Flash messages ─
function flash(string $key, string $msg = ''): string {
    startSession();
    if ($msg !== '') {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $val = (string)($_SESSION['flash'][$key] ?? '');
    unset($_SESSION['flash'][$key]);
    return $val;
}

// ── CSRF 
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    startSession();
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if ($stored === '' || !hash_equals($stored, $posted)) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

// ── Output escaping 
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Email validation: must be @stud.umu.ac.ug ─────────────────
function isUmuStudentEmail(string $email): bool {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    // Accepts:  firstname.lastname@stud.umu.ac.ug
    return (bool)preg_match('/^[a-z0-9._+-]+@stud\.umu\.ac\.ug$/i', $email);
}


// Format: 4digits - 1letter + 3digits - 5digits
function isValidRegNumber(string $reg): bool {
    $reg = strtoupper(trim($reg));
    return (bool)preg_match('/^\d{4}-[A-Z]\d{3}-\d{5}$/', $reg);
}

// ── Voting helpers 
function isVotingOpen(): bool {
    $row = db()->query(
        "SELECT setting_value FROM settings WHERE setting_key='voting_open' LIMIT 1"
    )->fetch();
    return ($row['setting_value'] ?? '0') === '1';
}

function electionTitle(): string {
    $row = db()->query(
        "SELECT setting_value FROM settings WHERE setting_key='election_title' LIMIT 1"
    )->fetch();
    return $row['setting_value'] ?? APP_NAME;
}
