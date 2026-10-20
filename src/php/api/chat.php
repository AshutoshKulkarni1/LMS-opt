<?php

require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (!isStudent()) {
    echo json_encode([
        'success' => false,
        'message' => 'Student session not found.',
        'session' => $_SESSION
    ]);
    exit;
}

$studentId = $_SESSION['student_id'];

$db = getDB();

$stmt = $db->prepare("
    SELECT id, name, email, college_name, branch,
           roll_number, year_of_joining, course_name, batch_id
    FROM students
    WHERE id = ?
");

$stmt->execute([$studentId]);

$student = $stmt->fetch();

if (!$student) {
    echo json_encode([
        'success' => false,
        'message' => 'Student record not found.'
    ]);
    exit;
}

// Get student's test performance
$stmt = $db->prepare("
    SELECT
        s.id AS submission_id,
        s.test_id,
        t.title AS test_title,
        s.status,
        s.evaluation_status,
        s.total_marks_obtained,
        s.total_marks,
        s.auto_score,
        s.manual_score,
        s.total_score,
        s.submitted_at
    FROM submissions s
    JOIN tests t ON t.id = s.test_id
    WHERE s.student_id = ?
    ORDER BY s.submitted_at DESC
");

$stmt->execute([$studentId]);
$submissions = $stmt->fetchAll();


// Get student's PCI performance
$stmt = $db->prepare("
    SELECT
        pr.test_id,
        t.title AS test_title,
        pr.pci_score,
        pr.mcq_score,
        pr.coding_score,
        pr.explanation_score,
        pr.mcq_weight,
        pr.coding_weight,
        pr.explanation_weight,
        pr.generated_at
    FROM pci_records pr
    JOIN tests t ON t.id = pr.test_id
    WHERE pr.student_id = ?
    ORDER BY pr.generated_at DESC
");

$stmt->execute([$studentId]);
$pciRecords = $stmt->fetchAll();


$groqApiKey = env('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
$groqModel  = env('GROQ_MODEL', getenv('GROQ_MODEL') ?: 'qwen/qwen3.8-27b');

// ─── 1. Primary: Direct Groq Cloud AI (High speed, zero laptop load, works on Railway) ───
if (!empty($groqApiKey)) {
    // Quick greeting check
    $lower = strtolower(trim($message, "!.? "));
    $greetings = ['hi', 'hello', 'hey', 'hola', 'good morning', 'good afternoon', 'good evening'];
    if (in_array($lower, $greetings, true)) {
        $studentName = $student['name'] ?? 'there';
        echo json_encode([
            'success' => true,
            'message' => "Hello {$studentName}! How can I assist you with your academic performance or tests today?"
        ]);
        exit;
    }

    // Clean context
    $cleanSubmissions = [];
    foreach (array_slice($submissions, 0, 5) as $s) {
        $cleanSubmissions[] = [
            'test_title'     => $s['test_title'] ?? '',
            'status'         => $s['status'] ?? '',
            'marks_obtained' => $s['total_marks_obtained'] ?? null,
            'total_marks'    => str_replace(':00', '', (string)($s['total_marks'] ?? '')),
            'submitted_at'   => (string)($s['submitted_at'] ?? ''),
        ];
    }

    $cleanPci = [];
    foreach (array_slice($pciRecords, 0, 5) as $p) {
        $cleanPci[] = [
            'test_title'        => $p['test_title'] ?? '',
            'pci_score'         => $p['pci_score'] ?? null,
            'mcq_score'         => $p['mcq_score'] ?? null,
            'coding_score'      => $p['coding_score'] ?? null,
            'explanation_score' => $p['explanation_score'] ?? null,
        ];
    }

    $context = [
        'student' => [
            'name'   => $student['name'] ?? '',
            'course' => $student['course_name'] ?? '',
            'branch' => $student['branch'] ?? '',
        ],
        'recent_submissions'    => $cleanSubmissions,
        'performance_breakdown' => $cleanPci,
    ];

    $systemPrompt = "You are an academic mentor and AI assistant for a student on this learning portal.\n" .
        "Your purpose is strictly to assist with:\n" .
        "1. Coursework, study concepts, syllabus topics, and exam preparation.\n" .
        "2. Reviewing the student's test submissions, marks, and performance analytics provided below.\n" .
        "3. Portal navigation and test instructions.\n\n" .
        "CRITICAL RULES:\n" .
        "- ONLY answer questions related to academics, studies, coursework, tests, or this learning portal.\n" .
        "- If the student asks about anything unrelated (such as movies, gaming, celebrities, jokes, gossip, recipes, pop culture, politics, or general chit-chat), politely decline by answering:\n" .
        "  'I am your academic mentor assistant. I can only assist you with coursework, test performance, studies, and learning portal questions.'\n" .
        "- Keep answers direct, helpful, and concise (2-4 sentences).\n" .
        "- Do not show internal thinking or reasoning tags; output only the final answer.";

    $userContent = "Student Academic Data:\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n\nStudent Question:\n" . $message;

    $groqPayload = json_encode([
        'model'       => $groqModel,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ],
        'temperature' => 0.2,
        'max_tokens'  => 512,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Authorization: Bearer {$groqApiKey}\r\n" .
                               "Content-Type: application/json\r\n" .
                               "User-Agent: LMS-Chatbot/1.0\r\n",
            'content'       => $groqPayload,
            'timeout'       => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $ctx);
    if ($raw !== false) {
        $res = json_decode($raw, true);
        if (!empty($res['choices'][0]['message']['content'])) {
            $content = trim($res['choices'][0]['message']['content']);
            $content = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $content);
            echo json_encode([
                'success' => true,
                'message' => trim($content)
            ]);
            exit;
        }
    }
}

// ─── 2. Fallback: Local Python / Ollama microservice (used when GROQ_API_KEY is not set) ───
$pythonPayload = [
    'message'     => $message,
    'student'     => $student,
    'submissions' => $submissions,
    'pci_records' => $pciRecords
];

$pythonResponse = pythonApiRequest(
    '/api/chat',
    $pythonPayload,
    'POST',
    360
);

$aiMessage = '';
if (!empty($pythonResponse['ok']) && !empty($pythonResponse['data']['message'])) {
    $aiMessage = $pythonResponse['data']['message'];
} elseif (!empty($pythonResponse['data']['error'])) {
    $aiMessage = $pythonResponse['data']['error'];
}

echo json_encode([
    'success'         => !empty($pythonResponse['ok']),
    'message'         => $aiMessage ?: ($pythonResponse['error'] ?? 'Sorry, the AI service is currently unavailable.'),
    'python_response' => $pythonResponse
]);