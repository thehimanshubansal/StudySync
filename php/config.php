<?php
// ══════════════════════════════════════════════════════
//  StudySync — config.php
//  EDIT the 4 lines below to match your XAMPP setup
// ══════════════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       
// default XAMPP user
define('DB_PASS', '');           
define('DB_NAME', 'studysync');
// default XAMPP password (blank)
// ── Claude AI ─────────────────────────────────────────
define('CLAUDE_API_KEY', 'YOUR_CLAUDE_API_KEY_HERE');
define('CLAUDE_MODEL',   'claude-sonnet-4-20250514');
// ── Session (must be FIRST before any output) ─────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([ 
        'lifetime' => 86400 * 7,   // 7 days
        'path'     => '/',
        'secure'   => false,       
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
// set true if using HTTPS
// ── CORS headers (needed for fetch() from same origin) ─
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
// ── DB connection factory ─────────────────────────────
function getDB(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
// ── JSON response helper ──────────────────────────────
function respond(bool $ok, string $msg = '', $data = null): void {
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
// ── Auth guard — returns user_id or stops with 401 ───
function requireAuth(): int {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        respond(false, 'Not authenticated. Please log in.');
    }
    return (int) $_SESSION['user_id'];
}