<?php
require_once __DIR__ . '/config.php';
$uid    = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
// ════════════════════════════════════════════════════════
//  LIST
// ════════════════════════════════════════════════════════
if ($method === 'GET' && $action === 'list') {
   $db    = getDB();
   $month = $_GET['month'] ?? null;
   if ($month) {
       $from = $month . '-01';
       $to   = date('Y-m-t', strtotime($from));
       $stmt = $db->prepare('SELECT * FROM schedules WHERE user_id=? AND start_date BETWEEN ? AND ? ORDER BY start_date, start_time');
       $stmt->bind_param('iss', $uid, $from, $to);
   } else {
       $stmt = $db->prepare('SELECT * FROM schedules WHERE user_id=? ORDER BY start_date, start_time');
       $stmt->bind_param('i', $uid);
   }
   $stmt->execute();
   respond(true, '', $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}
// ════════════════════════════════════════════════════════
//  CREATE
// ════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'create') {
   $title      = trim($body['title']       ?? '');
   $desc       = trim($body['description'] ?? '');
   $subject    = trim($body['subject']     ?? '');
   $start_date = trim($body['start_date']  ?? '');
   $end_date   = trim($body['end_date']    ?? '') ?: null;
   $start_time = trim($body['start_time']  ?? '') ?: null;
   $end_time   = trim($body['end_time']    ?? '') ?: null;
   $recurrence = trim($body['recurrence']  ?? 'none');
   $color      = trim($body['color']       ?? '#4f46e5');
   if (!$title)      respond(false, 'Title is required.');
   if (!$start_date) respond(false, 'Start date is required.');
   $db   = getDB();
   $stmt = $db->prepare('INSERT INTO schedules (user_id,title,description,subject,start_date,end_date,start_time,end_time,recurrence,color) VALUES (?,?,?,?,?,?,?,?,?,?)');
   $stmt->bind_param('isssssssss', $uid, $title, $desc, $subject, $start_date, $end_date, $start_time, $end_time, $recurrence, $color);
   if ($stmt->execute()) {
       $nid   = $db->insert_id;
       $sched = $db->query("SELECT * FROM schedules WHERE id=$nid")->fetch_assoc();
       respond(true, 'Schedule created!', $sched);
   }
   respond(false, 'Failed to create schedule.');
}
// ════════════════════════════════════════════════════════
//  DELETE
// ════════════════════════════════════════════════════════
if ($method === 'DELETE' && $action === 'delete') {
   $sid  = (int)($_GET['id'] ?? 0);
   if (!$sid) respond(false, 'ID required.');
   $db   = getDB();
   $stmt = $db->prepare('DELETE FROM schedules WHERE id=? AND user_id=?');
   $stmt->bind_param('ii', $sid, $uid);
   $stmt->execute();
   respond(true, 'Schedule deleted.');
}
respond(false, 'Unknown action: ' . $action);