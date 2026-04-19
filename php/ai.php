<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'POST required.');

$uid  = requireAuth();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$prompt = trim($body['prompt'] ?? '');

if (!$prompt) respond(false, 'Prompt is required.');

// 1. Fetch upcoming tasks for Context
$db   = getDB();
$stmt = $db->prepare('SELECT title, subject, priority, task_date, task_time, status FROM tasks WHERE user_id=? AND task_date >= CURDATE() ORDER BY task_date, task_time LIMIT 20');
$stmt->bind_param('i', $uid);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$ctx = empty($tasks) ? 'No upcoming tasks scheduled.'
   : implode("\n", array_map(fn($t) => "- [{$t['status']}] {$t['title']} ({$t['subject']}) | Priority: {$t['priority']} | Date: {$t['task_date']} {$t['task_time']}", $tasks));

$system_instruction = "You are StudySync AI — a smart study planner. 
The student's current schedule:

IMPORTANT RULES:
- DO NOT use Markdown.
- DO NOT use asterisks (*) or bold text (**).
- Provide response in PLAIN TEXT only.
- Keep replies under 150 words.
- Use simple dashes (-) for lists instead of bullet points.
- Be encouraging and focus on academic success.
- If suggesting times, use clear text like '10:00 AM to 11:30 AM'.
- Focus on the student's actual tasks provided below:
$ctx";

// 2. Fallback if no key is set
if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
   respond(true, '', ['reply' => "🤖 StudySync AI (Offline Mode): Try breaking your " . count($tasks) . " tasks into 25-minute Pomodoro blocks! (Please add your Gemini API Key for full AI power)."]);
}

// 3. Google Gemini API Call
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "system_instruction" => [
        "parts" => [
            ["text" => $system_instruction]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 2048,
        "topP" => 0.95,
        "topK" => 40
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS      => json_encode($payload),
    CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER  => false, // Crucial for XAMPP
    CURLOPT_TIMEOUT         => 60
]);

$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    $err = json_decode($res, true);
    $msg = $err['error']['message'] ?? 'Gemini AI connection failed.';
    respond(false, "AI Error: " . $msg);
}

$data = json_decode($res, true);

// ai response detection code 
$reason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
if ($reason !== 'STOP') {
    // If it says 'MAX_TOKENS', your limit is too low.
    error_log("Gemini stopped because: " . $reason); 
}

// Gemini Response Path: candidates[0] -> content -> parts[0] -> text
$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'AI could not generate a response.';

respond(true, '', ['reply' => $reply]);