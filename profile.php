<?php
session_start();
require_once __DIR__ . '/../db/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT student_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['student_id']) {
        throw new Exception("Student ID not linked to user.");
    }

    $stmt = $pdo->prepare("
    SELECT full_name, lrn, grade_level, strand, profile_image
    FROM students
    WHERE id = ?
  ");
    $stmt->execute([$user['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student profile not found.");
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (in_array($file['type'], $allowedTypes) && $file['size'] <= 2 * 1024 * 1024) {
      $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($file['name']));

        $filename = uniqid() . '_' . basename($file['name']);
        $target = '../img/profile_pictures/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $stmt = $pdo->prepare("SELECT student_id FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stmt = $pdo->prepare("UPDATE students SET profile_image = ? WHERE id = ?");
                $stmt->execute([$filename, $user['student_id']]);
                $_SESSION['success_message'] = "Profile picture updated!";
            }
        } else {
            $_SESSION['success_message'] = "Upload failed.";
        }
    } else {
        $_SESSION['success_message'] = "Invalid file type or size.";
    }

    header("Location: profile.php");
    exit;
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
    background: #fdfdfb;
    display: flex;
    font-family: 'Poppins', sans-serif;
  }

  .profile-card {
    max-width: 800px;
    margin: 40px auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    border-left: 6px solid #FFC107;
  }

  .profile-img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    margin-bottom: 10px;
    border: 3px solid #FFC107;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  }

  h3.fw-bold {
    color: #002147;
  }

  .text-center.mb-4 p {
    color: #6B4F3B;
  }

  .btn-warning {
    background-color: #FFC107;
    border: none;
    color: #000;
    font-weight: 500;
  }

  .btn-warning:hover {
    background-color: #e0ac00;
  }

  .btn-outline-danger {
    border-color: #6B4F3B;
    color: #6B4F3B;
  }

  .btn-outline-danger:hover {
    background-color: #6B4F3B;
    color: #fff;
  }

  .alert-success {
    background-color: #fff8e1;
    border-left: 5px solid #FFC107;
    color: #6B4F3B;
  }

  .alert-danger {
    background-color: #fbe9e7;
    border-left: 5px solid #d32f2f;
    color: #6B4F3B;
  }

  .modal-content {
    border-left: 5px solid #002147;
  }

  .modal-title {
    color: #002147;
  }

  .btn-danger {
    background-color: #d32f2f;
    border: none;
  }

  .btn-danger:hover {
    background-color: #b71c1c;
  }
</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="profile-card text-center">
        <?php if (!empty($_SESSION['success_message'])): ?>
          <div class="alert alert-success">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
          </div>
        <?php endif; ?>

        <h3 class="fw-bold mb-4" style="color: #001f3f;">Student Profile</h3>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
          <!-- Profile Picture -->
          <?php
          $profileImage = !empty($student['profile_image'])
            ? '../img/profile_pictures/' . $student['profile_image']
            : '../img/defaultprofile.webp';
          ?>
          <form action="profile.php" method="POST" enctype="multipart/form-data" class="position-relative d-inline-block" style="width: 120px; height: 120px;">
            <input type="file" name="profile_image" id="profileInput" accept="image/*" style="display: none;" onchange="this.form.submit()">

            <!-- Clickable image -->
            <label for="profileInput" style="cursor: pointer;">
              <img src="<?php echo $profileImage; ?>" alt="Profile Picture" class="profile-img">
              
              <!-- Overlay text -->
              <div class="position-absolute top-50 start-50 translate-middle text-white fw-semibold"
                  style="background-color: rgba(0,0,0,0.4); padding: 4px 10px; border-radius: 6px; font-size: 0.9rem; font-family: 'Segoe UI', sans-serif;">
                Upload
              </div>
            </label>
          </form>

          <!-- Key Info -->
          <div class="text-center mb-4" style="margin-top: 12px; font-size: 1.25rem; font-weight: 500; line-height: 1.6; color: #212529;">
            <p><?php echo htmlspecialchars($student['full_name']); ?></p>
            <p><?php echo htmlspecialchars($student['lrn']); ?></p>
            <p>Grade: <?php echo htmlspecialchars($student['grade_level']); ?> | Strand: <?php echo htmlspecialchars($student['strand']); ?></p>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex justify-content-center gap-3">
            <a href="edit-profile.php" class="btn btn-warning">📝 Edit Details</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../Includes/footer.php'?>
