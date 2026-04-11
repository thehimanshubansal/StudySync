<?php
require_once __DIR__ . '/config.php';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
// ════════════════════════════════════════════════════════
//  SIGNUP
// ════════════════════════════════════════════════════════
if ($action === 'signup' && $method === 'POST') {
   $name     = trim($body['full_name'] ?? '');
   $email    = strtolower(trim($body['email'] ?? ''));
   $password = trim($body['password']  ?? '');
   $dob      = trim($body['dob'] ?? '');
   // Server-side Validation
   if (strlen($name) < 2) respond(false, 'Invalid name.');
   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Invalid email.');
   if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
       respond(false, 'Password does not meet complexity requirements.');
   }
   if (empty($dob)) respond(false, 'Date of birth is required.');
   $db = getDB();
   $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
   $chk->bind_param('s', $email);
   $chk->execute();
   if ($chk->get_result()->num_rows > 0)
       respond(false, 'An account with this email already exists.');
   $hash = password_hash($password, PASSWORD_BCRYPT);
   $ins = $db->prepare('INSERT INTO users (full_name, email, password_hash, dob) VALUES (?, ?, ?, ?)');
   $ins->bind_param('ssss', $name, $email, $hash, $dob);
   if ($ins->execute()) {
       $uid = $db->insert_id;
       $_SESSION['user_id']    = $uid;
       $_SESSION['user_name']  = $name;
       $_SESSION['user_email'] = $email;
       // Log first activity
       $today = date('Y-m-d');
       $db->query("INSERT IGNORE INTO daily_activity (user_id, activity_date) VALUES ($uid, '$today')");
       respond(true, 'Account created!', ['id' => $uid, 'full_name' => $name, 'email' => $email, 'dob' => $dob]);
   }
   respond(false, 'Signup failed. Please try again.');
}
// ════════════════════════════════════════════════════════
//  LOGIN
// ════════════════════════════════════════════════════════
if ($action === 'login' && $method === 'POST') {
   $email    = strtolower(trim($body['email']    ?? ''));
   $password = trim($body['password'] ?? '');
   if (!$email || !$password)
       respond(false, 'Email and password are required.');
   $db   = getDB();
   $stmt = $db->prepare('SELECT id, full_name, email, password_hash, dob FROM users WHERE email = ?');
   $stmt->bind_param('s', $email);
   $stmt->execute();
   $user = $stmt->get_result()->fetch_assoc();
   if (!$user || !password_verify($password, $user['password_hash']))
       respond(false, 'Incorrect email or password.');
   $uid   = $user['id'];
   $today = date('Y-m-d');
   $db->query("INSERT IGNORE INTO daily_activity (user_id, activity_date) VALUES ($uid, '$today')");
   $_SESSION['user_id']    = $uid;
   $_SESSION['user_name']  = $user['full_name'];
   $_SESSION['user_email'] = $user['email'];
   unset($user['password_hash']);
   $user['dob']   = $user['dob']   ?? '';
   respond(true, 'Login successful!', $user);
}
// ════════════════════════════════════════════════════════
//  LOGOUT
// ════════════════════════════════════════════════════════
if ($action === 'logout') {
   $_SESSION = [];
   session_destroy();
   respond(true, 'Logged out.');
}
// ════════════════════════════════════════════════════════
//  GET CURRENT USER  (/auth.php?action=me)
// ════════════════════════════════════════════════════════
if ($action === 'me') {
   $uid  = requireAuth();
   $db   = getDB();
   $stmt = $db->prepare('SELECT id, full_name, email, dob FROM users WHERE id = ?');
   $stmt->bind_param('i', $uid);
   $stmt->execute();
   $user = $stmt->get_result()->fetch_assoc();
   if (!$user) respond(false, 'User not found.');
   $user['dob']   = $user['dob']   ?? '';
   respond(true, '', $user);
}
// ════════════════════════════════════════════════════════
//  UPDATE PROFILE
// ════════════════════════════════════════════════════════
if ($action === 'update' && $method === 'POST') {
   $uid   = requireAuth();
   $name  = trim($body['full_name'] ?? '');
   $dob   = trim($body['dob']       ?? '');
   if (!$name) respond(false, 'Name cannot be empty.');
   $db     = getDB();
   $dobVal = $dob ?: null;
   $stmt   = $db->prepare('UPDATE users SET full_name=?, dob=? WHERE id=?');
   $stmt->bind_param('ssi', $name, $dobVal, $uid);
   if ($stmt->execute()) {
       $_SESSION['user_name'] = $name;
       respond(true, 'Profile updated!');
   }
   respond(false, 'Update failed.');
}
// ════════════════════════════════════════════════════════
//  DELETE ACCOUNT
// ════════════════════════════════════════════════════════
if ($action === 'delete' && $method === 'DELETE') {
   $uid  = requireAuth();
   $db   = getDB();
   $stmt = $db->prepare('DELETE FROM users WHERE id=?');
   $stmt->bind_param('i', $uid);
   if ($stmt->execute()) {
       $_SESSION = []; session_destroy();
       respond(true, 'Account deleted.');
   }
   respond(false, 'Delete failed.');
}
respond(false, 'Unknown action: ' . $action);