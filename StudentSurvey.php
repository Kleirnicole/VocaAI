<?php
session_start();
include "../db/config.php";

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$currentYear = date('Y');
$hasSubmitted = false;

// Check if student has submitted survey this year
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_answers WHERE student_id = ? AND YEAR(created_at) = ?");
    $stmt->execute([$student_id, $currentYear]);
    $hasSubmitted = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    error_log("Survey check error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Error checking survey status. Please try again later.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocAItion Student Dashboard</title>
  <?php include '../Includes/header.php'; ?>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { display: flex; height: 100vh; background: #f8f9fa; }
    .btn { padding: 10px 14px; background: #1d3557; color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: 0.3s; margin-top: 5px; }
    .btn:hover { background: #457b9d; }
    form { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); }
    .question { margin-bottom: 20px; }
    label { font-weight: bold; display: block; margin-bottom: 8px; }
    .options { margin-left: 15px; }
    select, input[type="text"] { width: 90%; padding: 8px; border: 1px solid #ccc; border-radius: 5px; margin-top: 5px; }
    input[type="radio"], input[type="checkbox"] { margin-right: 5px; }
    /* Global font settings */
body, form {
  font-family: 'Poppins', sans-serif;
  font-size: 16px;
  color: #212529;
}

/* Survey title and description */
h2.text-warning {
  font-size: 2rem;
  font-weight: 600;
}

p.text-muted {
  font-size: 1.1rem;
  margin-bottom: 10px;
}

/* Question text */
.card-body h6 {
  font-size: 1.05rem;
  font-weight: 500;
  color: #1d3557;
}

/* Answer options */
.form-check-label {
  font-size: 1rem;
  font-weight: 500;
  color: #343a40;
}

/* Submit button */
.btn-lg {
  font-size: 1.1rem;
  font-weight: 600;
}
.card-body {
  padding: 1rem 1.5rem;
}

.card {
  border: none;
  border-left: 4px solid #1d3557;
  background-color: #ffffff;
}
  </style>
</head>
<body>
  <?php include '../Includes/sidebar.php'; ?>

  <div class="main-content">
    <div class="card shadow-sm p-3 mb-4">
      <h2 class="text-center text-warning">🧠 RIASEC Survey</h2>
      <p class="text-center text-muted">Answer each question honestly to help us match you with the best-fit career paths.</p>
      <div class="progress" style="height: 6px;">
        <div class="progress-bar bg-info" role="progressbar" style="width: 0%;" id="surveyProgress"></div>
      </div>
    </div>

    <?php
    $questions = [
      1 => "I like to work on cars.",
      2 => "I like to do puzzles.",
      3 => "I am good at working independently.",
      4 => "I like to work in teams.",
      5 => "I am an ambitious person, I set goals for myself.",
      6 => "I like to organize things (files, desks/offices).",
      7 => "I like to build things.",
      8 => "I like to read about art and music.",
      9 => "I like to have clear instructions.",
      10 => "I like to try to influence or persuade people.",
      11 => "I like to do experiments.",
      12 => "I like to teach or train people.",
      13 => "I like trying to help people solve their problems.",
      14 => "I like to take care of animals.",
      15 => "I wouldn't mind working 8 hours per day in an office.",
      16 => "I like selling things.",
      17 => "I enjoy creative writing.",
      18 => "I enjoy science.",
      19 => "I am quick to take on new responsibilities.",
      20 => "I am interested in healing people.",
      21 => "I enjoy trying to figure out how things work.",
      22 => "I like putting things together or assembling things.",
      23 => "I am a creative person.",
      24 => "I pay attention to details.",
      25 => "I like to do filing or typing.",
      26 => "I like to analyze things (problems/situations).",
      27 => "I like to play instruments or sing.",
      28 => "I enjoy learning about other cultures.",
      29 => "I would like to start my own business.",
      30 => "I like to cook.",
      31 => "I like acting in plays.",
      32 => "I am a practical person.",
      33 => "I like working with numbers or charts.",
      34 => "I like to get into discussions about issues.",
      35 => "I am good at keeping records of my work.",
      36 => "I like to lead.",
      37 => "I like working outdoors.",
      38 => "I would like to work in an office.",
      39 => "I'm good at math.",
      40 => "I like helping people.",
      41 => "I like to draw.",
      42 => "I like to give speeches."
    ];
    ?>

    <?php if ($hasSubmitted): ?>
      <div class="alert alert-success text-center">
        <h5 class="mb-2">🎉 You've already completed the RIASEC survey for <?= $currentYear ?>.</h5>
        <p class="mb-0">You can retake the survey next year.</p>
      </div>
    <?php endif;?>
    
    <form action="save_survey.php" method="POST">
    <?php if ($hasSubmitted): ?>
      <fieldset disabled>
    <?php endif; ?>

    <div class="row">
  <?php foreach ($questions as $num => $text): ?>
    <div class="col-12 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="row align-items-center">
            <!-- Left side: Question -->
            <div class="col-md-8">
              <h6 class="fw-bold mb-0">Q<?= $num ?>. <?= htmlspecialchars($text) ?></h6>
            </div>
            <!-- Right side: Options -->
            <div class="col-md-4 d-flex justify-content-start">
              <div class="form-check me-3">
                <input class="form-check-input" type="radio" name="q<?= $num ?>" id="q<?= $num ?>yes" value="yes" required>
                <label class="form-check-label" for="q<?= $num ?>yes">Yes</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="q<?= $num ?>" id="q<?= $num ?>no" value="no">
                <label class="form-check-label" for="q<?= $num ?>no">No</label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

    <?php if ($hasSubmitted): ?>
      </fieldset>
    <?php endif; ?>

    <?php if (!$hasSubmitted): ?>
      <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary btn-lg">✅ Submit Survey</button>
      </div>
    <?php endif; ?>
  </form> 
  </div>
<?php include '../Includes/footer.php'; ?>