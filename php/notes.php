<?php
require_once __DIR__ . '/config.php';
$uid = requireAuth();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET' && $action === 'list') {
    $stmt = $db->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY is_starred DESC, created_at DESC");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    respond(true, '', $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($method === 'POST' && $action === 'save') {
    $id = $body['id'] ?? null;
    $title = $body['title'] ?? 'Untitled';
    $content = $body['content'] ?? '';
    $cat = $body['category'] ?? 'Study';

    if ($id) {
        $stmt = $db->prepare("UPDATE notes SET title=?, content=?, category=? WHERE id=? AND user_id=?");
        $stmt->bind_param("sssii", $title, $content, $cat, $id, $uid);
    } else {
        $stmt = $db->prepare("INSERT INTO notes (user_id, title, content, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uid, $title, $content, $cat);
    }
    
    if ($stmt->execute()) respond(true, 'Note saved');
    respond(false, 'Error saving note');
}

if ($method === 'POST' && $action === 'star') {
    $id = $body['id'] ?? 0;
    $stmt = $db->prepare("UPDATE notes SET is_starred = NOT is_starred WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $uid);
    if ($stmt->execute()) respond(true, 'Status updated');
    respond(false, 'Error');
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("DELETE FROM notes WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $uid);
    if ($stmt->execute()) respond(true, 'Note deleted');
    respond(false, 'Error');
}