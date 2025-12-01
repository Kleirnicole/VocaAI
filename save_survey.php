<?php
session_start();
require_once "../db/config.php";

// Validate session
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Ensure student_id is numeric
if (!is_numeric($student_id)) {
    echo "<h3>Error: Invalid student ID.</h3>";
    exit();
}

// Verify student exists
$check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE id = ?");
$check->execute([$student_id]);
if ($check->fetchColumn() == 0) {
    echo "<h3>Error: Student ID $student_id not found in database.</h3>";
    exit();
}

// Collect survey answers
$answers = [];
for ($i = 1; $i <= 42; $i++) {
    $q = "q$i";
    $answers[$q] = isset($_POST[$q]) ? $_POST[$q] : '';
}

// Validate minimum input
if (empty(array_filter($answers))) {
    echo "<h3>Error: No survey answers submitted.</h3>";
    exit();
}

try {
    // Insert survey_answers
    $columns = array_keys($answers);
    $placeholders = array_map(fn($col) => ':' . $col, $columns);

    $sql = "INSERT INTO survey_answers (student_id, " . implode(", ", $columns) . ")
            VALUES (:student_id, " . implode(", ", $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
    foreach ($answers as $key => $value) {
        $stmt->bindValue(':' . $key, (string)$value, PDO::PARAM_STR);
    }

    $stmt->execute();
    $survey_id = $pdo->lastInsertId();
    $_SESSION['survey_id'] = $survey_id;

    // --- Insert audit log for this survey submission
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, resource, details, created_at)
        VALUES (:user_id, :action, :resource, :details, NOW())
    ");
    $stmt->execute([
        ':user_id'  => $student_id,
        ':action'   => 'Submit Survey',
        ':resource' => 'survey_answers',
        ':details'  => 'Submitted survey ID ' . $survey_id
    ]);

    // Calculate RIASEC scores
    $groups = [
        'realistic'     => ['q1', 'q7', 'q14', 'q22', 'q30', 'q32', 'q37'],
        'investigative' => ['q2', 'q11', 'q18', 'q21', 'q26', 'q33', 'q39'],
        'artistic'      => ['q3', 'q8', 'q17', 'q23', 'q27', 'q31', 'q41'],
        'social'        => ['q4', 'q12', 'q13', 'q20', 'q28', 'q34', 'q40'],
        'enterprising'  => ['q5', 'q10', 'q16', 'q19', 'q29', 'q36', 'q42'],
        'conventional'  => ['q6', 'q9', 'q15', 'q24', 'q25', 'q35', 'q38'],
    ];

    $scores = [];
    foreach ($groups as $type => $questions) {
        $scores[$type] = array_reduce($questions, function($sum, $q) use ($answers) {
            return $sum + (isset($answers[$q]) && strtolower($answers[$q]) === 'yes' ? 1 : 0);
        }, 0);
    }

    arsort($scores);
    $top_3 = array_slice(array_keys($scores), 0, 3);
    $top_3_str = implode(',', array_map(fn($t) => strtoupper(substr($t, 0, 1)), $top_3));
    $_SESSION['top_3_types'] = $top_3_str;

    // Insert riasec_scores
    $stmt = $pdo->prepare("INSERT INTO riasec_scores (
        answer_id, realistic, investigative, artistic, social, enterprising, conventional, top_3_types
    ) VALUES (
        :answer_id, :realistic, :investigative, :artistic, :social, :enterprising, :conventional, :top_3_types
    )");

    $stmt->execute([
        ':answer_id'     => $survey_id,
        ':realistic'     => $scores['realistic'],
        ':investigative' => $scores['investigative'],
        ':artistic'      => $scores['artistic'],
        ':social'        => $scores['social'],
        ':enterprising'  => $scores['enterprising'],
        ':conventional'  => $scores['conventional'],
        ':top_3_types'   => $top_3_str
    ]);

} catch (Exception $e) {
    error_log("Survey DB error: " . $e->getMessage());
    echo "<h3>Database Error</h3><pre>" . $e->getMessage() . "</pre>";
    exit();
}

// Add RIASEC code to answers for JSON
$answers['top_3_types'] = $top_3_str;
$answers['code'] = $top_3_str;

// Save answers to temp JSON file
$tmp_file = tempnam(sys_get_temp_dir(), 'survey_') . '.json';
if (!file_put_contents($tmp_file, json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
    echo "<h3>Error creating temp JSON file</h3>";
    exit();
}

// Run Python prediction
$python_path = "C:/Users/Public/Downloads/python.exe";
$python_file = "C:/wamp64/www/VocAItion/Student/predict_survey.py";
$command = escapeshellarg($python_path) . " " . escapeshellarg($python_file) . " " . escapeshellarg($tmp_file) . " 2>&1";
$output = shell_exec($command);
unlink($tmp_file);

// Decode Python output (strip warnings)
if (preg_match('/\{.*\}$/s', $output, $matches)) {
    $json_output = $matches[0];
    $result = json_decode($json_output, true);
} else {
    $result = null;
}

$_SESSION['recommended_course'] = $result['recommended_course'] ?? null;
$_SESSION['recommended_score'] = $result['recommended_score'] ?? null;
$_SESSION['suggested_course'] = $result['suggested_course'] ?? null;
$_SESSION['suggested_score'] = $result['suggested_score'] ?? null;
$_SESSION['recommended_description'] = $result['recommended_description'] ?? null;

if (!$result || !isset($result['recommended_course']) || !isset($result['recommended_score'])) {
    echo "<pre>$output</pre>";
    echo "<h3>Python Prediction Error</h3>";
    error_log("Prediction error: " . $output);
    exit();
}

// Redirect to AI suggestions page
header("Location: Studentai-suggestions.php");
exit();