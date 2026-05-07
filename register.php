<?php
// register.php — Student self-registration (pending admin approval)
require_once __DIR__ . '/config.php';
startSession();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $firstName   = trim($_POST['first_name'] ?? '');
    $secondName  = trim($_POST['second_name'] ?? '');
    $studentNum  = strtoupper(trim($_POST['student_number'] ?? ''));
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $password    = $_POST['password'] ?? '';

    // Photo upload
    $photoPath = null;

    if (empty($_FILES['photo']['name'] ?? '')) {
        $errors[] = 'Photo is required.';
    } elseif (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Photo upload failed. Please try again.';
    } else {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['photo']['tmp_name']);
        $size  = (int)($_FILES['photo']['size'] ?? 0);

        $maxBytes = 5 * 1024 * 1024; // 5MB
        if (!isset($allowed[$mime])) {
            $errors[] = 'Invalid photo type. Use JPG, PNG, or WEBP.';
        } elseif ($size <= 0 || $size > $maxBytes) {
        $errors[] = 'Photo must be between 1B and 5MB.';
        }

        if (!$errors) {
            $ext = $allowed[$mime];
            $uploadDirAbs = __DIR__ . '/uploads/user-photos';

            if (!is_dir($uploadDirAbs)) {

                @mkdir($uploadDirAbs, 0777, true);
            }

            $safeReg = preg_replace('/[^A-Z0-9\-]/', '', $studentNum);
            $filename = $safeReg . '_' . time() . '.' . $ext;
            $targetAbs = $uploadDirAbs . '/' . $filename;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $targetAbs)) {
                $errors[] = 'Could not save uploaded photo. Please try again.';
            } else {
                // Store relative path for web access
                $photoPath = 'uploads/user-photos/' . $filename;
            }
        }
    }

    if (!$firstName || strlen($firstName) < 2) {

        $errors[] = 'First name is required.';
    }
    if (!$secondName || strlen($secondName) < 2) {
        $errors[] = 'Second name is required.';
    }

    if (!$studentNum) {
        $errors[] = 'Student number is required.';
    } elseif (!isValidRegNumber($studentNum)) {
        $errors[] = 'Invalid student number format. Use: 2023-B072-31712';
    }

    if (!$email) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (!isUmuStudentEmail($email)) {
        $errors[] = 'Email must end with @stud.umu.ac.ug (e.g. baguma.gerald@stud.umu.ac.ug)';
    }

    if (!$password || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (!$errors) {
        $fullName = $firstName . ' ' . $secondName;

        // Create pending student (is_approved=0)
        $chk = db()->prepare('SELECT id FROM users WHERE reg_number=? OR email=? LIMIT 1');
        $chk->execute([$studentNum, $email]);
        if ($chk->fetch()) {
            $errors[] = 'An account with that student number or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            db()->prepare(
                'INSERT INTO users (reg_number, first_name, second_name, full_name, email, password, role, is_approved, photo_path) VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$studentNum, $firstName, $secondName, $fullName, $email, $hash, 'student', 0, $photoPath]);

            redirect('index.php?msg=registered_pending');

        }
    }
}

$pageTitle = 'Student Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?> — UMU Voting</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>

<div class="top-banner">
  <div class="banner-stripe red"></div>
  <div class="banner-stripe yellow"></div>
  <div class="banner-stripe black"></div>
</div>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="auth-logo-block" aria-hidden="true">
        <img src="assets/logo.jpeg" alt="UMU logo" class="auth-school-badge" />
      </div>
      <h1>UMU Registration</h1>
      <p>Account will be approved by admin</p>
    </div>

    <div class="auth-card-body">

      <?php if (($_GET['msg'] ?? '') === 'registered_pending'): ?>
        <div class="alert alert-success" data-auto-dismiss>
          ✅ Registration submitted! You can login only after admin approval.
        </div>
      <?php endif; ?>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error">❌ <?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="POST" action="register.php" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />

        <div class="form-group">
          <label class="form-label">First Name</label>
          <input class="form-control" type="text" name="first_name" required minlength="2" value="<?= e($_POST['first_name'] ?? '') ?>" />
        </div>


        <div class="form-group">
          <label class="form-label">Second Name</label>
          <input class="form-control" type="text" name="second_name" required minlength="2" value="<?= e($_POST['second_name'] ?? '') ?>" />
        </div>

        <div class="form-group">
          <label class="form-label">Student Number</label>
          <input class="form-control" type="text" name="student_number" required value="<?= e($_POST['student_number'] ?? '') ?>" placeholder="2023-B072-31712" />
          <div style="font-size:.73rem;color:var(--grey-400);margin-top:.3rem">
            Format: YYYY-A000-00000
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="baguma.gerald@stud.umu.ac.ug" />
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="password" required minlength="6" placeholder="Min 6 characters" />
        </div>

        <div class="form-group" style="margin-top:1rem">
          <label class="form-label">Photo *</label>
          <input class="form-control" type="file" name="photo" accept="image/*" required />
          <div style="font-size:.73rem;color:var(--grey-400);margin-top:.3rem">
            Upload a clear JPG/PNG/WEBP (max 5MB).
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-2" style="font-size:1rem;padding:.9rem">

          Register &rarr;
        </button>
      </form>

      <div class="divider"></div>

      <p class="text-center text-grey" style="font-size:.87rem">
        Already have an account? <a href="index.php" style="color:var(--red);font-weight:700">Sign in</a>
      </p>

    </div>
  </div>
</div>

<script src="assets/main.js"></script>
</body>
</html>

