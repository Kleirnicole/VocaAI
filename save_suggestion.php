<?php
session_start();
include '../db/config.php';

$survey_id = $_POST['survey_id'] ?? null;
$course = $_POST['recommended_course'] ?? null;
$confidence = $_POST['recommended_score'] ?? null;
$suggested_course = $_POST['suggested_course'] ?? null;
$suggested_score = $_POST['suggested_score'] ?? null;
$description = $_POST['recommended_description'] ?? null;

if ($survey_id && $course && $confidence) {
    $stmt = $conn->prepare("SELECT id FROM ai_recommendations WHERE survey_id = ?");
    $stmt->bind_param("i", $survey_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO ai_recommendations (survey_id, confidence_score, recommended_career, recommended_description, suggested_career, suggested_score, date_generated) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssss", $survey_id, $confidence, $course, $description, $suggested_course, $suggested_score);
        $stmt->execute();
    } else {
        $stmt->close();
    }

    $conn->close();
    header("Location: StudentCareerpath.php?saved=1");
    exit();
} else {
    echo "<h3>Missing data. Please complete the survey first.</h3>";
    exit();
}