<?php
require_once __DIR__ . '/config.php';
$uid    = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$db = getDB();

// Helper function to calculate Smart Score server-side
function calculateScore($prio, $urg, $imp) {
    $score = ($prio === 'High') ? 50 : (($prio === 'Medium') ? 25 : 10);
    if ($urg == 1) $score += 40;
    if ($imp == 1) $score += 30;
    return $score;
}

// ════════════════════════════════════════════════════════
//  LIST TASKS
// ════════════════════════════════════════════════════════
if ($method === 'GET' && $action === 'list') {
   $date  = $_GET['date']  ?? null;
   $month = $_GET['month'] ?? null;

   // Base Query: Status 'pending' comes first, then ordered by User/AI hybrid logic
   $sql = "SELECT * FROM tasks WHERE user_id = ?";
   $params = [$uid];
   $types = "i";

   if ($date) {
       $sql .= " AND task_date = ?";
       $params[] = $date;
       $types .= "s";
   } elseif ($month) {
       $from = $month . '-01';
       $to   = date('Y-m-t', strtotime($from));
       $sql .= " AND task_date BETWEEN ? AND ?";
       $params[] = $from;
       $params[] = $to;
       $types .= "ss";
   }

   // HYBRID SORT: 
   // 1. Manual order (order_number)
   // 2. AI Score (smart_score)
   // 3. Time
   $sql .= " ORDER BY status ASC, order_number ASC, smart_score DESC, task_time ASC";
   
   $stmt = $db->prepare($sql);
   $stmt->bind_param($types, ...$params);
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
   $date = !empty(trim($body['task_date'] ?? '')) ? trim($body['task_date']) : date('Y-m-d');
   $time = !empty(trim($body['task_time'] ?? '')) ? trim($body['task_time']) : '00:00:00';
   $est_time  = (int)($body['estimated_time'] ?? 60);
   $urgent    = (int)($body['is_urgent'] ?? 0);
   $important = (int)($body['is_important'] ?? 0);
   $reminder  = (int)($body['reminder'] ?? 0);
   $color     = trim($body['color'] ?? '#00B4BE');

   if (!$title) respond(false, 'Task title is required.');
   // Calculate AI score before saving
   $smart_score = calculateScore($priority, $urgent, $important);

   $res = $db->query("SELECT MAX(order_number) as max_ord FROM tasks WHERE user_id = $uid");
   $row = $res->fetch_assoc();
   $nextOrder = ($row['max_ord'] ?? 0) + 1;


   // SQL Query including ALL columns from your screenshot
   $stmt = $db->prepare('INSERT INTO tasks 
        (user_id, title, description, category, task_date, task_time, subject, priority, estimated_time, is_urgent, is_important, reminder, color, smart_score, order_number) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

   // BINDING TYPES EXPLAINED:
   // i (user_id), s (title), s (desc), s (cat), s (date), s (time), s (subj), s (prio), i (est), i (urg), i (imp), i (rem)
   $stmt->bind_param('isssssssiiiisii', 
        $uid, $title, $desc, $category, $date, $time, $subject, $priority, $est_time, $urgent, $important, $reminder, $color, $smart_score, $nextOrder
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

    $stmt = $db->prepare("UPDATE tasks SET 
        completed_at = IF(status='pending', CURRENT_TIMESTAMP, NULL),
         time_spent_mins = IF(status='pending', (DATEDIFF(CURRENT_TIMESTAMP, created_at) + 1) * estimated_time, 0),
        status = IF(status='pending','completed','pending')
        WHERE id=? AND user_id=?");
   $stmt->bind_param('ii', $tid, $uid);

   
   if ($stmt->execute()) {
       $task = $db->query("SELECT * FROM tasks WHERE id=$tid")->fetch_assoc();
       // GAMIFICATION LOGIC: Award XP only when task moves from pending -> completed
       if ($task['status'] === 'completed') {
           $points = 50; // Base points
           if ($task['priority'] === 'High') $points += 30;
           if ($task['is_urgent']) $points += 20;
           
           // Update User XP
           $db->query("UPDATE users SET xp = xp + $points WHERE id = $uid");
           
           // Simple Level Up logic (every 1000 XP)
           $db->query("UPDATE users SET level = FLOOR(xp / 1000) + 1 WHERE id = $uid");
       }
       respond(true, 'Status Updated', $task);
   }
   respond(false, 'Toggle failed');
   
}


// ════════════════════════════════════════════════════════
//  REORDER TASK
// ════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'reorder') {
    $draggedId = (int)($body['draggedId'] ?? 0);
    $targetId  = (int)($body['targetId'] ?? 0);

    if (!$draggedId || !$targetId) respond(false, "Invalid IDs");

    $res = $db->query("SELECT order_number FROM tasks WHERE id = $targetId");
    $targetOrder = $res->fetch_assoc()['order_number'];

    $newOrder = $targetOrder - 1; 
    $db->query("UPDATE tasks SET order_number = $newOrder WHERE id = $draggedId");

    $db->query("SET @rank := 0;");
    $db->query("UPDATE tasks 
                SET order_number = (@rank := @rank + 1) 
                WHERE user_id = $uid AND status = 'pending' 
                ORDER BY order_number ASC, smart_score DESC");

    respond(true, 'Manual priority saved and normalized.');
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
    $date = !empty(trim($body['task_date'] ?? '')) ? trim($body['task_date']) : date('Y-m-d');
    $time = !empty(trim($body['task_time'] ?? '')) ? trim($body['task_time']) : '00:00:00';
    $est_time = (int)($body['estimated_time'] ?? 60);
    $urgent   = (int)($body['is_urgent'] ?? 0);
    $important= (int)($body['is_important'] ?? 0);
    $reminder = (int)($body['reminder'] ?? 0);
    $color    = trim($body['color'] ?? '#00B4BE');

    if (!$tid || !$title || !$date) respond(false, 'Missing required fields.');
    
    $smart_score = calculateScore($priority, $urgent, $important);

    $db = getDB();
    $stmt = $db->prepare('UPDATE tasks SET 
        title=?, description=?, category=?, task_date=?, task_time=?, 
        subject=?, priority=?, estimated_time=?, is_urgent=?, 
        is_important=?, reminder=?, color=?, smart_score=? 
        WHERE id=? AND user_id=?');

    $stmt->bind_param('sssssssiiiisiii', 
        $title, $desc, $category, $date, $time, 
        $subject, $priority, $est_time, $urgent, 
        $important, $reminder, $color, $smart_score, $tid, $uid
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