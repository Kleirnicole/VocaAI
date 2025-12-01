<?php
session_start();
require_once '../db/config.php'; // This defines $pdo

$student_id = $_SESSION['student_id'] ?? null;
$recommendation = null;

if ($student_id) {
    try {
        // Get the latest survey_id for this student
        $stmt = $pdo->prepare("SELECT id FROM survey_answers WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$student_id]);
        $survey = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($survey) {
            $survey_id = $survey['id'];

            // Get the recommendation for this survey
            $stmt = $pdo->prepare("SELECT * FROM ai_recommendations WHERE survey_id = ?");
            $stmt->execute([$survey_id]);
            $recommendation = $stmt->fetch(PDO::FETCH_ASSOC);

            // If not found and session has prediction data, insert it
            if (!$recommendation && isset($_SESSION['recommended_course'], $_SESSION['recommended_score'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO ai_recommendations (
                        survey_id, confidence_score, recommended_career,
                        recommended_description, suggested_career, suggested_score, date_generated
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $survey_id,
                    $_SESSION['recommended_score'],
                    $_SESSION['recommended_course'],
                    $_SESSION['recommended_description'] ?? null,
                    $_SESSION['suggested_course'] ?? null,
                    $_SESSION['suggested_score'] ?? null
                ]);

                // Re-fetch the newly inserted recommendation
                $stmt = $pdo->prepare("SELECT * FROM ai_recommendations WHERE survey_id = ?");
                $stmt->execute([$survey_id]);
                $recommendation = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        error_log("AI recommendation error: " . $e->getMessage());
        echo "<h3>Database Error</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        exit();
    }
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
    .card {
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      background-color: #fff8f0;
      border: 1px solid #a67c52;
    }
    .highlight {
      background-color: #fffbe6;
      border-left: 5px solid #1d3557;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 8px;
    }
    .highlight h5 {
      color: #1d3557;
      margin-bottom: 10px;
    }
    .badge-ai {
      background-color: #f4c430;
      font-size: 0.9rem;
      color: #1d3557;
    }
    strong {
      color: #6b4f3b; 
    }

  </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="main-content d-flex justify-content-center align-items-center" style="min-height: 100vh;">
  <div class="card p-4 w-100" style="max-width: 600px;">
    <h2 class="text-center mb-4">
      <i class="bi bi-lightbulb-fill text-warning me-2"></i> AI-Powered Career Insight
    </h2>

    <?php if ($recommendation): ?>
      <div class="highlight">
        <h5>🎓 Recommended Course 
          <span class="badge badge-ai text-white"><?= htmlspecialchars($recommendation['confidence_score']) ?>% Confidence</span>
        </h5>
        <p class="mb-0"><strong><?= htmlspecialchars($recommendation['recommended_career']) ?></strong></p>
      </div>

      <?php if (!empty($recommendation['recommended_description'])): ?>
        <div class="highlight">
          <h5>📘 Why This Course?</h5>
          <p class="mb-0"><?= htmlspecialchars($recommendation['recommended_description']) ?></p>
        </div>
      <?php endif; ?>

      <?php if (!empty($recommendation['suggested_career'])): ?>
        <div class="highlight">
          <h5>💡 Alternative Suggestion 
            <span class="badge badge-ai text-white"><?= htmlspecialchars($recommendation['suggested_score']) ?>% Confidence</span>
          </h5>
          <p class="mb-0"><strong><?= htmlspecialchars($recommendation['suggested_career']) ?></strong></p>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="text-muted text-center"><em>No prediction yet. Please take the survey first.</em></p>
    <?php endif; ?>

    <div class="highlight mt-7">
    <h5>📢 Advisory</h5>
    <p class="mb-2">
      The AI suggestions are provided to guide you, but they do not determine your future. 
      The final decision on what course to pursue in college is entirely yours.
    </p>

    <form method="POST" action="save_course_choice.php">
      <label for="course_choice" class="form-label"><strong>Your Intended College Course</strong></label>
      <select name="course_choice" id="course_choice" class="form-select" required>
        <option value="Undecided" selected>Undecided</option>
        <?php
        // Fetch courses dynamically from DB
        $coursesStmt = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
        $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($courses as $course) {
            $display = htmlspecialchars($course['course_name']);
            if (!empty($course['course_code'])) {
                $display .= " (" . htmlspecialchars($course['course_code']) . ")";
            }
            echo "<option value='" . htmlspecialchars($course['course_name']) . "'>$display</option>";
        }
        ?>
      </select>
      <button type="submit" class="btn btn-primary mt-2">Save Choice</button>
    </form>
  </div>
  </div>
</div>
<?php include '../Includes/footer.php'?>
