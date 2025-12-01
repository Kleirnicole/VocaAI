<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

require_once "../db/config.php";

$user_id = $_SESSION['user_id'];
$student_id = $pdo->query("SELECT student_id FROM users WHERE id = $user_id")->fetchColumn();

// Fetch latest survey
$survey_id = $pdo->prepare("SELECT id FROM survey_answers WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$survey_id->execute([$student_id]);
$survey_id = $survey_id->fetchColumn();

// Fetch RIASEC scores
$traits = [];
$top_3_types = '';
if ($survey_id) {
    $stmt = $pdo->prepare("SELECT realistic, investigative, artistic, social, enterprising, conventional, top_3_types FROM riasec_scores WHERE answer_id = ?");
    $stmt->execute([$survey_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $traits = $row ? array_slice($row, 0, 6) : [];
    $top_3_types = $row['top_3_types'] ?? '';
}

// Fetch courses from DB
$coursesStmt = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

//radar chart
$skillScores = [];

if ($survey_id) {
    $stmt = $pdo->prepare("SELECT * FROM survey_answers WHERE id = ?");
    $stmt->execute([$survey_id]);
    $answers = $stmt->fetch(PDO::FETCH_ASSOC);

    $skillMap = [
        'Communication' => ['q10', 'q12', 'q31', 'q34', 'q42'],
        'Teamwork' => ['q4', 'q13', 'q40'],
        'Problem Solving' => ['q2', 'q11', 'q21', 'q26'],
        'Initiative' => ['q5', 'q19', 'q36'],
        'Planning & Organizing' => ['q6', 'q9', 'q24', 'q35'],
        'Self-Management' => ['q3', 'q15', 'q32'],
        'Learning Agility' => ['q18', 'q28', 'q33', 'q39'],
        'Technology Literacy' => ['q1', 'q7', 'q22']
    ];

    foreach ($skillMap as $skill => $questions) {
        $yesCount = 0;
        foreach ($questions as $q) {
            if (isset($answers[$q]) && strtolower($answers[$q]) === 'yes') {
                $yesCount++;
            }
        }
        $score = round(($yesCount / count($questions)) * 10); // scale to 0–10
        $skillScores[$skill] = $score;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocAItion Student Dashboard</title>
  <?php include '../Includes/header.php';?>
  <style>
    /* HEADER */
    .dashboard-header {
      background: #fff;
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;  
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    .dashboard-header h1 { font-size: 22px; color: #333; }
    .header-right { display: flex; align-items: center; gap: 15px; }
    .header-btn {
      padding: 10px 14px;
      background: #f4c430; /* warm yellow */
      color: #1d3557;       /* navy text */
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: 0.3s;
      font-weight: 600;
    }

    .header-btn:hover {
      background: #e0b000;
      color: #fff;
    }

    /* CARDS */
    .card-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }
    .card {
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
      transition: 0.3s;
    }
    .card:hover { transform: translateY(-4px); }
    .card h3 { margin-bottom: 10px; color: #1d3557; }

    /* CHARTS */
    .chart-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }
    .chart-card {
      background: #fff8f0; 
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
      border-left: 5px solid #1d3557; 
    }

    .chart-card h3 {
      color: #1d3557;
      font-weight: 600;
    }

    /* MODAL */
    .modal {
      position: fixed; top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      display: none; justify-content: center; align-items: center;
    }
    .modal-content {
      background: #fff;
      padding: 20px; border-radius: 10px;
      width: 90%; max-width: 600px; text-align: center;
      max-height: 80vh; overflow-y: auto;
    }
    .close-btn {
      background: #6b4f3b; /* rich brown */
      padding: 8px 12px;
      color: #fff;
      border: none;
      border-radius: 6px;
      margin-top: 10px;
      cursor: pointer;
    }
    .close-btn:hover {
      background: #5a3f2e;
    }

  </style>
</head>
<body>
  <?php include '../Includes/sidebar.php'; ?>

  <div class="main-content">
    <!-- HEADER -->
    <header class="dashboard-header">
      <div class="header-left">
        <h1>Welcome, Student!</h1>
      </div>
      <div class="header-right">
        <button class="header-btn" id="openRiasecModal">Get RIASEC Interest Code</button> 
      </div>
    </header>

    <!-- CARDS -->
    <div class="chart-container">
      <div class="chart-card">
        <h3>Career Interests</h3>
        <canvas id="careerChart"></canvas>
      </div>
      <div class="chart-card">
        <h3>Skills Level</h3>
        <canvas id="skillsChart"></canvas>
      </div>
    </div>
  </div>

  <!-- AI Modal -->
  <div class="modal" id="riasecModal">
  <div class="modal-content">
    <h3>RIASEC Interest Code</h3>
    <p id="riasecCodeDisplay">Loading your interest code...</p>
    <button class="close-btn" id="closeRiasecModal">Close</button>
  </div>
</div>
<script>
  const skillScores = <?php echo json_encode(array_values($skillScores)); ?>;
</script>
<script>
// RIASEC Modal Logic
const riasecModal = document.getElementById('riasecModal');
const riasecCodeDisplay = document.getElementById('riasecCodeDisplay');

document.getElementById('openRiasecModal').addEventListener('click', () => {
  const code = "<?= $top_3_types ?>"; // Injected from PHP
  if (code && code.trim() !== "") {
    const formatted = code.split(',').map(letter =>
      `<span style="text-decoration:underline; font-weight:bold; margin:0 8px;">${letter}</span>`
    ).join('');
    riasecCodeDisplay.innerHTML = formatted;
  } else {
    riasecCodeDisplay.textContent = "No RIASEC code found. Please complete the survey.";
  }
  riasecModal.style.display = 'flex';
});

document.getElementById('closeRiasecModal').addEventListener('click', () => {
  riasecModal.style.display = 'none';
});

window.addEventListener('click', (e) => {
  if (e.target === riasecModal) riasecModal.style.display = 'none';
});

  function viewSurvey() {
    if (surveyData) {
      let display = '';
      for (let key in surveyData) display += `${key}: ${surveyData[key]} <br>`;
      document.getElementById('surveyResults').innerHTML = display;
    } else alert('No survey found!');
  }

  function generateTopCareers() {
    if (!surveyData) {
      document.getElementById('topCareers').innerText = "Please take the survey.";
      return;
    }
    let strand = generateCourseSuggestion().split(" ")[5];
    let careerList = {
      STEM: "Software Engineer, Data Scientist, Civil Engineer",
      ABM: "Entrepreneur, Accountant, Marketing Manager",
      HUMSS: "Teacher, Lawyer, Psychologist",
      TVL: "Chef, Automotive Technician, Electrician",
      ARTS: "Graphic Designer, Animator, Photographer"
    };
    document.getElementById('topCareers').innerText = careerList[strand] || "Career list unavailable.";
  }

  // RIASEC Chart (Dynamic from PHP)
  const riasecScores = <?= json_encode($traits) ?>;
  const ctx1 = document.getElementById('careerChart').getContext('2d');
  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: ['Realistic', 'Investigative', 'Artistic', 'Social', 'Enterprising', 'Conventional'],
      datasets: [{
        label: 'RIASEC Trait Level',
        data: [
          riasecScores.realistic || 0,
          riasecScores.investigative || 0,
          riasecScores.artistic || 0,
          riasecScores.social || 0,
          riasecScores.enterprising || 0,
          riasecScores.conventional || 0
        ],
        backgroundColor: '#1d3557',
        borderColor: '#f4c430',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          max: 10,
          title: {
            display: true,
            text: 'Score'
          }
        }
      },
      plugins: {
        legend: { display: false },
        title: {
          display: true,
          text: 'RIASEC Trait Strengths'
        }
      }
    }
  });

  
  // Career Readiness Skills Radar Chart
  const ctx2 = document.getElementById('skillsChart').getContext('2d');
  new Chart(ctx2, {
    type: 'radar',
    data: {
      labels: [
        'Communication',
        'Teamwork',
        'Problem Solving',
        'Initiative',
        'Planning & Organizing',
        'Self-Management',
        'Learning Agility',
        'Technology Literacy'
      ],
      datasets: [{
        label: 'Career Readiness Skills',
        data: skillScores, // ✅ use dynamic scores here
        backgroundColor: 'rgba(69, 123, 157, 0.2)',
        borderColor: '#457b9d',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      scales: {
        r: {
          min: 0,
          max: 10,
          ticks: { stepSize: 1 },
          pointLabels: {
            font: { size: 12 }
          }
        }
      },
      plugins: {
        title: {
          display: true,
          text: 'Career Readiness Skill Profile'
        }
      }
    }
  });
</script>
<?php include '../Includes/footer.php'?>
