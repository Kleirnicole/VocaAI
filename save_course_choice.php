<?php
session_start();
require_once "../db/config.php";

if ($_SESSION['role'] !== 'student') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$student_id = $_SESSION['student_id'] ?? null;
$career_choice = $_POST['course_choice'] ?? 'Undecided';

if ($student_id) {
    $stmt = $pdo->prepare("UPDATE students SET career_choice = ? WHERE id = ?");
    $stmt->execute([$career_choice, $student_id]);
}

header("Location: Studentai-suggestions.php?success=course_saved");
exit();
?>