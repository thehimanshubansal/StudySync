<?php
require_once __DIR__ . '/config.php';
$uid = requireAuth();
$db = getDB();
$action = $_GET['action'] ?? '';

// Get Leaderboard (Self + Friends)
if ($action === 'leaderboard') {
    $sql = "SELECT id, full_name, xp, level FROM users 
            WHERE id = $uid 
            OR id IN (SELECT friend_id FROM friends WHERE user_id = $uid AND status='accepted')
            OR id IN (SELECT user_id FROM friends WHERE friend_id = $uid AND status='accepted')
            ORDER BY xp DESC LIMIT 10";
    $res = $db->query($sql);
    respond(true, '', $res->fetch_all(MYSQLI_ASSOC));
}

// Add Friend by Email or ID
if ($action === 'add_friend') {
    $body = json_decode(file_get_contents('php://input'), true);
    $search = $body['search']; // Can be Email or ID

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR id = ?");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $friend = $stmt->get_result()->fetch_assoc();

    if (!$friend) respond(false, "User not found");
    if ($friend['id'] == $uid) respond(false, "You cannot add yourself");

    $stmt = $db->prepare("INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
    $stmt->bind_param("ii", $uid, $friend['id']);
    if ($stmt->execute()) respond(true, "Friend added!");
    respond(false, "Already friends");
}