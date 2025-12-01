<?php
session_start();

// Use full path to Python if needed
$prediction = shell_exec("python C:\\xampp\\htdocs\\NationalHighschool\\Student\\predict_course.py");

// Clean and store result
$_SESSION['predicted_course'] = trim($prediction);

// Redirect to display page
header("Location: StudentCareerpath.php");
exit();
?>