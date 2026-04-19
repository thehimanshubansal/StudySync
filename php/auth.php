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
   $ins = $db->prepare('INSERT INTO users (full_name, email, password_hash, dob, daily_capacity) VALUES (?, ?, ?, ?, 4)');
   $ins->bind_param('ssss', $name, $email, $hash, $dob);
   if ($ins->execute()) {
       $uid = $db->insert_id;
       $_SESSION['user_id']    = $uid;
       $_SESSION['user_name']  = $name;
       $_SESSION['user_email'] = $email;
       // Log first activity
       $today = date('Y-m-d');
       $db->query("INSERT IGNORE INTO daily_activity (user_id, activity_date) VALUES ($uid, '$today')");
       respond(true, 'Account created!', ['id' => $uid, 'full_name' => $name, 'email' => $email, 'daily_capacity' => 4]);
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
   $stmt = $db->prepare('SELECT id, full_name, email, password_hash, dob, daily_capacity FROM users WHERE email = ?');
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
   $stmt = $db->prepare('SELECT id, full_name, email, dob, daily_capacity FROM users WHERE id = ?');
   $stmt->bind_param('i', $uid);
   $stmt->execute();
   $user = $stmt->get_result()->fetch_assoc();

   if (!$user) respond(false, 'User not found.');
   respond(true, '', $user);
}
// ════════════════════════════════════════════════════════
//  UPDATE PROFILE
// ════════════════════════════════════════════════════════
if ($action === 'update' && $method === 'POST') {
    $uid = requireAuth();
    $name = trim($body['full_name'] ?? '');
    $email = strtolower(trim($body['email'] ?? ''));
    $capacity = (int)($body['daily_capacity'] ?? 4);
    
    if (!$name || !$email) respond(false, 'Name and Email are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Invalid email format.');

    $db = getDB();
    $stmt = $db->prepare('UPDATE users SET full_name=?, email=?, daily_capacity=? WHERE id=?');
    $stmt->bind_param('ssii', $name, $email, $capacity, $uid);
    
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $name;
        respond(true, 'Profile updated successfully!');
    }
    respond(false, 'Update failed.');
}
    
// ════════════════════════════════════════════════════════
//  CHANGE PASSWORD
// ════════════════════════════════════════════════════════
if ($action === 'change_password' && $method === 'POST') {
    $uid = requireAuth();
    $current = $body['current_password'] ?? '';
    $new = $body['new_password'] ?? '';

    if (strlen($new) < 8) respond(false, 'New password must be at least 8 characters.');

    $db = getDB();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $user['password_hash'])) {
        respond(false, 'Current password incorrect.');
    }

    $newHash = password_hash($new, PASSWORD_BCRYPT);
    $upd = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->bind_param('si', $newHash, $uid);
    
    if ($upd->execute()) respond(true, 'Password changed successfully!');
    respond(false, 'Failed to change password.');
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