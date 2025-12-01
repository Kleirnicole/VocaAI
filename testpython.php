<?php
$python_path = "C:/Users/Public/Downloads/python.exe";
$script_path = "C:/xampp/htdocs/NationalHighschool/Student/predict_course.py";

$output = shell_exec("$python_path $script_path");
echo "<pre>$output</pre>";
?>
