<?php
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST required.');
$uid  = requireAuth();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$prompt = trim($body['prompt'] ?? '');
if (!$prompt) respond(false, 'Prompt is required.');
// Fetch upcoming tasks as context
$db   = getDB();
$stmt = $db->prepare('SELECT title, category, task_date, task_time, status FROM tasks WHERE user_id=? AND task_date >= CURDATE() ORDER BY task_date, task_time LIMIT 20');
$stmt->bind_param('i', $uid);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ctx = empty($tasks) ? 'No upcoming tasks.'
   : implode("\n", array_map(fn($t) => "- [{$t['status']}] {$t['title']} | {$t['category']} | {$t['task_date']} {$t['task_time']}", $tasks));
$system = "You are StudySync AI — a smart, friendly study planner for college students.
The student's upcoming tasks:\n$ctx\n
Rules: Keep replies under 150 words. Use bullet points. Be encouraging.
For time suggestions use blocks like '9:00 AM – 10:30 AM'. Never mention you are Claude.";
if (CLAUDE_API_KEY === 'YOUR_CLAUDE_API_KEY_ HERE') {
   respond(true, '', ['reply' => "🤖 AI tip: Break your tasks into 25-min Pomodoro blocks. You have " . count($tasks) . " upcoming tasks — tackle the hardest one first when your energy is highest!"]);
}
$payload = json_encode(['model' => CLAUDE_MODEL, 'max_tokens' => 600, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
   CURLOPT_RETURNTRANSFER => true,
   CURLOPT_POST => true,   
   CURLOPT_POSTFIELDS      => $payload,
   CURLOPT_TIMEOUT         => 30,
   CURLOPT_HTTPHEADER      => ['Content-Type: application/json', 'x-api-key: ' . CLAUDE_API_KEY, 'anthropic-version: 2023-06-01']]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 200) respond(false, 'AI service unavailable.');
$data  = json_decode($res, true);
$reply = $data['content'][0]['text'] ?? 'No response.';
respond(true, '', ['reply' => $reply]);