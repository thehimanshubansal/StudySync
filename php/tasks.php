<?php
require_once __DIR__ . '/config.php';
$uid    = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$db = getDB();

// ════════════════════════════════════════════════════════
//  LIST TASKS
// ════════════════════════════════════════════════════════
if ($method === 'GET' && $action === 'list') {
   $date  = $_GET['date']  ?? null;
   $month = $_GET['month'] ?? null;

   if ($date) {
       // FIXED: Added task_date filter to SQL and bind_param
       $stmt = $db->prepare('SELECT * FROM tasks WHERE user_id=? AND task_date=? ORDER BY task_time ASC');
       $stmt->bind_param('is', $uid, $date);
   } elseif ($month) {
       $from = $month . '-01';
       $to   = date('Y-m-t', strtotime($from));
       $stmt = $db->prepare('SELECT * FROM tasks WHERE user_id=? AND task_date BETWEEN ? AND ? ORDER BY task_date, task_time');
       $stmt->bind_param('iss', $uid, $from, $to);
   } else {
       // This block is used by the Task Scheduler to see ALL tasks
       $stmt = $db->prepare('SELECT * FROM tasks WHERE user_id=? ORDER BY task_date DESC, task_time ASC');
       $stmt->bind_param('i', $uid);
   }
   $stmt->execute();
   respond(true, '', $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// ════════════════════════════════════════════════════════
//  STATS & STREAK (RESTORED FROM VERSION 1)
// ════════════════════════════════════════════════════════
if ($method === 'GET' && $action === 'stats') {
   $stmt = $db->prepare('SELECT COUNT(*) AS total, SUM(status="pending") AS pending, SUM(status="completed") AS completed FROM tasks WHERE user_id=?');
   $stmt->bind_param('i', $uid);
   $stmt->execute();
   $counts = $stmt->get_result()->fetch_assoc();

   // Real Streak Calculation Logic
   $streak = 0;
   $check  = date('Y-m-d');
   while (true) {
       $r = $db->prepare('SELECT 1 FROM daily_activity WHERE user_id=? AND activity_date=?');
       $r->bind_param('is', $uid, $check);
       $r->execute();
       if ($r->get_result()->num_rows === 0) break;
       $streak++;
       $check = date('Y-m-d', strtotime("$check -1 day"));
   }

   respond(true, '', [
       'total'     => (int)$counts['total'],
       'pending'   => (int)($counts['pending'] ?? 0),
       'completed' => (int)($counts['completed'] ?? 0),
       'streak'    => $streak
   ]);
}

// ════════════════════════════════════════════════════════
//  CREATE TASK (SMART FIELDS INCLUDED)
// ════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'create') {
   // Capture all fields from the Add Task form
   $title     = trim($body['title'] ?? '');
   $desc      = trim($body['description'] ?? '');
   $category  = trim($body['category'] ?? 'General');
   $subject   = trim($body['subject'] ?? 'General');
   $priority  = trim($body['priority'] ?? 'Medium');
   $date      = trim($body['task_date'] ?? date('Y-m-d'));
   $time      = trim($body['task_time'] ?? '00:00:00');
   $est_time  = (int)($body['estimated_time'] ?? 60);
   $urgent    = (int)($body['is_urgent'] ?? 0);
   $important = (int)($body['is_important'] ?? 0);
   $reminder  = (int)($body['reminder'] ?? 0);
   $color     = trim($body['color'] ?? '#00B4BE');

   if (!$title) respond(false, 'Task title is required.');
   if (!$date || $date == '0000-00-00') respond(false, 'Valid date is required.');

   $timeVal = $time ?: '00:00:00';

   // SQL Query including ALL columns from your screenshot
   $stmt = $db->prepare('INSERT INTO tasks 
        (user_id, title, description, category, task_date, task_time, subject, priority, estimated_time, is_urgent, is_important, reminder, color) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

   // BINDING TYPES EXPLAINED:
   // i (user_id), s (title), s (desc), s (cat), s (date), s (time), s (subj), s (prio), i (est), i (urg), i (imp), i (rem)
   $stmt->bind_param('isssssssiiiis', 
        $uid, $title, $desc, $category, $date, $timeVal, $subject, $priority, $est_time, $urgent, $important, $reminder, $color
   );

   if ($stmt->execute()) {
       $newId = $db->insert_id;
       // Log activity for streak logic
       $today = date('Y-m-d');
       $db->query("INSERT IGNORE INTO daily_activity (user_id, activity_date) VALUES ($uid, '$today')");
       
       $task = $db->query("SELECT * FROM tasks WHERE id=$newId")->fetch_assoc();
       respond(true, 'Task created!', $task);
   }
   respond(false, 'Failed to create task: ' . $db->error);
}


// ════════════════════════════════════════════════════════
//  AJAX TOGGLE STATUS
// ════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'toggle') {
   $tid = (int)($body['id'] ?? 0);
   if (!$tid) respond(false, 'Task ID required.');

   $stmt = $db->prepare('UPDATE tasks SET status=IF(status="pending","completed","pending") WHERE id=? AND user_id=?');
   $stmt->bind_param('ii', $tid, $uid);
   
   if ($stmt->execute()) {
       $task = $db->query("SELECT * FROM tasks WHERE id=$tid")->fetch_assoc();
       respond(true, 'Status Updated', $task);
   }
   respond(false, 'Toggle failed');
}

// ════════════════════════════════════════════════════════
//  UPDATE TASK
// ════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'update') {
    $tid      = (int)($body['id'] ?? 0);
    $title    = trim($body['title'] ?? '');
    $desc     = trim($body['description'] ?? '');
    $category = trim($body['category'] ?? 'General');
    $subject  = trim($body['subject'] ?? 'General');
    $priority = trim($body['priority'] ?? 'Medium');
    $date     = trim($body['task_date'] ?? '');
    $time     = trim($body['task_time'] ?? '00:00:00');
    $est_time = (int)($body['estimated_time'] ?? 60);
    $urgent   = (int)($body['is_urgent'] ?? 0);
    $important= (int)($body['is_important'] ?? 0);
    $reminder = (int)($body['reminder'] ?? 0);
    $color    = trim($body['color'] ?? '#00B4BE');

    if (!$tid || !$title || !$date) respond(false, 'Missing required fields.');

    $db = getDB();
    $stmt = $db->prepare('UPDATE tasks SET 
        title=?, description=?, category=?, task_date=?, task_time=?, 
        subject=?, priority=?, estimated_time=?, is_urgent=?, 
        is_important=?, reminder=?, color=? 
        WHERE id=? AND user_id=?');

    $stmt->bind_param('sssssssiiiisii', 
        $title, $desc, $category, $date, $time, 
        $subject, $priority, $est_time, $urgent, 
        $important, $reminder, $color, $tid, $uid
    );
    
    if ($stmt->execute()) {
        $task = $db->query("SELECT * FROM tasks WHERE id=$tid")->fetch_assoc();
        respond(true, 'Task updated!', $task);
    }
    respond(false, 'Update failed.');
}

// ════════════════════════════════════════════════════════
//  DELETE TASK
// ════════════════════════════════════════════════════════
if ($method === 'DELETE' && $action === 'delete') {
   $tid  = (int)($_GET['id'] ?? 0);
   if (!$tid) respond(false, 'Task ID required.');
   
   $stmt = $db->prepare('DELETE FROM tasks WHERE id=? AND user_id=?');
   $stmt->bind_param('ii', $tid, $uid);
   
   if ($stmt->execute()) {
       respond(true, 'Task deleted.');
   }
   respond(false, 'Delete failed.');
}

respond(false, 'Unknown action: ' . $action);