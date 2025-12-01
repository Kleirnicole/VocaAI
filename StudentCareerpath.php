<?php
session_start();
require_once '../db/config.php';

$student_id = $_SESSION['student_id'] ?? null;
$recommended_course = null;
$matched_column = null;
$schools = [];

if ($student_id) {
    $stmt = $pdo->prepare("
        SELECT ar.recommended_career
        FROM ai_recommendations ar
        JOIN survey_answers sa ON ar.survey_id = sa.id
        WHERE sa.student_id = ?
        ORDER BY ar.date_generated DESC
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $recommended_course = trim($stmt->fetchColumn());
}

// Map keywords to column names
$course_map = [
    'Accountancy (BSA)' => 'accountacy',
    'Business Administration (BSBA)' => 'business_administration',
    'Entrepreneurship (BSE)' => 'entrepreneurship',
    'Real Estate Management (BSREM)' => 'real_estate_management',
    'Financial Management' => 'financial_management',
    'Bachelor of Science in Agribusiness' => 'agribusiness',
    'Bachelor of Science in Cooperative Management' => 'cooperative_management',
    'Legal Management' => 'legal_management',
    'Customs Administration (BSCA)' => 'customs_administration',
    'Hotel and Restaurant Management (BSHRM)' => 'hotel_restaurant_management',
    'Tourism Management (BSTM)' => 'tourism_management',
    'Bachelor of Science in Hospitality Management (BSHM)' => 'hospitality_management',
    'Civil Engineering (BSCE)' => 'civil_engineering',
    'Electrical Engineering (BSEE)' => 'electrical_engineering',
    'Computer Engineering (BSCPE)' => 'computer_engineering',
    'Electronics Engineering (BSECE)' => 'electronics_engineering',
    'Geodetic Engineering (BSGE)' => 'geodetic_engineering',
    'Biology (BS Bio)' => 'biology',
    'Chemistry (BS Chem)' => 'chemistry',
    'Mathematics (BS Math)' => 'mathematics',
    'Environmental Science (BSES)' => 'environmental_science',
    'Statistics (BS Stat)' => 'statistics',
    'Bachelor of Forensic Science (BFS)' => 'forensic_science',
    'Mechanical Engineering (BSME)' => 'mechanical_engineering',
    'Agro-Forestry (BSAF)' => 'agro_forestry',
    'Chemical Engineering (BSChE)' => 'chemical_engineering',
    'Industrial Engineering (BSIE)' => 'industrial_engineering',
    'Economics (AB/BS Econ)' => 'economics',
    'Marine Engineering (BSMarE)' => 'marine_engineering',
    'Architecture (BS Arch)' => 'architecture',
    'Nursing (BSN)' => 'nursing',
    'Medical Technology (BS MedTech) / Medical Laboratory Science' => 'medical_technology',
    'Radiologic Technology (BSRadTech)' => 'radiologic_technology',
    'Pharmacy (BS Pharm)' => 'pharmacy',
    'Physical Therapy (BSPT)' => 'physical_therapy',
    'Psychology (AB/BS Psych)' => 'psychology',
    'Nutrition and Dietetics (BSND)' => 'nutrition_dietetics',
    'Information Technology (BSIT)' => 'information_technology',
    'Information Systems (BSIS)' => 'information_systems',
    'Computer Science (BSCS)' => 'computer_science',
    'Entertainment and Multimedia Computing (BS EMC)' => 'entertainment_multimedia_computing',
    'Library and Information Science (BLIS)' => 'library_information_system',
    'Education' => 'education',
    'Social Work (BS Social Work)' => 'social_work',
    'Communication (AB Comm)' => 'communication',
    'Bachelor of Arts in English' => 'arts_english',
    'Political Science (AB Pol Sci)' => 'political_science',
    'Fine Arts (BFA)' => 'fine_arts',
    'Agriculture (BSA)' => 'agriculture',
    'Fisheries (BSFi)' => 'fisheries',
    'Bachelor of Science in Criminology (BS Crim)' => 'criminology',
    'Bachelor in Technology and Livelihood Education (BTLED)' => 'technology_livelihood',
    'Bachelor of Science in Public Safety (BSPS)' => 'public_safety',
    'Bachelor of Science in Industrial Security Management' => 'industrial_security',
    'Bachelor of Science in Accounting Information Systems (BSAIS)' => 'accounting_information_systems',
    'Diploma in Midwifery' => 'midwifery'
];

// Match normalized course to column
$matched_column = $course_map[$recommended_course] ?? null;
if ($matched_column) {
    // Whitelist column to prevent SQL injection
    $allowed_columns = array_values($course_map);
    if (in_array($matched_column, $allowed_columns)) {
        $query = "
            SELECT s.name, s.location
            FROM school_offer so
            JOIN school s ON s.id = so.school_id
            WHERE so.$matched_column = 1
        ";

        $params = [];

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
  body {
    background-color: #fdfdfb;
  }

  .text-primary {
    color: #002147 !important; /* Navy Blue */
  }

  .bg-primary {
    background-color: #002147 !important;
  }

  .badge.bg-info {
    background-color: #FFC107 !important; /* Yellow */
    color: #000 !important;
  }

  .alert-info {
    background-color: #fff8e1;
    border-left: 5px solid #FFC107;
    color: #6B4F3B; /* Brown */
  }

  .list-group-item {
    border-left: 4px solid #002147;
    background-color: #fff;
  }

  .list-group-item:hover {
    background-color: #f9f9f9;
  }

  .card {
    border: 1px solid #ddd;
    border-radius: 8px;
  }

  h2.text-primary {
    border-left: 6px solid #FFC107;
    padding-left: 12px;
  }

  .bi-geo-alt-fill {
    color: #6B4F3B;
  }

  .bi-info-circle-fill {
    color: #FFC107;
  }
</style>
</head>
<body>
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="container mt-4">
      <h2 class="mb-3 text-primary">🎓 Schools Offering: <?= htmlspecialchars(ucwords($recommended_course ?? 'No recommendation yet')) ?></h2>

      <?php if ($recommended_course): ?>

        <div class="card p-3 shadow-sm">
          <h5 class="mb-3">🔗 Suggested Schools</h5>
          <?php if ($schools): ?>
            <ul class="list-group">
              <?php foreach ($schools as $school): ?>
                <li class="list-group-item">
                  <div class="fw-bold text-primary fs-5"><?= htmlspecialchars($school['name']) ?></div>
                  <div class="text-muted mb-1"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($school['location']) ?></div>
                  <div><span class="badge bg-info text-dark">Offers: <?= htmlspecialchars(ucwords($recommended_course)) ?></span></div>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="mt-4 alert alert-info">
              <i class="bi bi-info-circle-fill"></i>
              <strong>Advisory:</strong> This list includes only schools currently mapped to our database. For a more complete directory of colleges and universities offering <?= htmlspecialchars(ucwords($recommended_course)) ?>, you may also visit <a href="https://www.finduniversity.ph/" target="_blank">FindUniversity.ph</a> or consult CHED’s official listings.
            </div>
          <?php else: ?>
            <p class="text-muted">No schools found offering <?= htmlspecialchars($recommended_course) ?> based on your filters.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-warning">No AI recommendation found. Please complete the RIASEC survey first.</div>
      <?php endif; ?>
    </div>
  </div>
  
<?php include '../Includes/footer.php'?>