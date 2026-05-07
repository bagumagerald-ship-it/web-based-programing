<?php
// index.php — Login page
require_once __DIR__ . '/config.php';
startSession();

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'vote.php');
}

$errors = [];
$fEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fEmail    = trim($_POST['email']      ?? '');
    $password  = $_POST['password'] ?? '';


    // Validate inputs
    if (!$fEmail)   $errors[] = 'University email is required.';
    elseif (!filter_var($fEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Enter a valid email address.';
    elseif (!isUmuStudentEmail($fEmail) && $fEmail !== 'admin@umu.ac.ug')
        $errors[] = 'Only @stud.umu.ac.ug emails are allowed (e.g. baguma.gerald@stud.umu.ac.ug).';

    if (!$password) $errors[] = 'Password is required.';


    if (!$errors) {
        // Look up by email (single factor)
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$fEmail]);
        $user = $stmt->fetch();


        if ($user && password_verify($password, $user['password'])) {
            if (($user['role'] ?? '') === 'student' && (int)($user['is_approved'] ?? 0) !== 1) {
                $errors[] = 'Your registration is pending approval by the admin.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email']= $user['email'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['has_voted'] = (bool)$user['has_voted'];
                $_SESSION['photo_path'] = (string)($user['photo_path'] ?? '');
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'vote.php');

            }
        } else {
            $errors[] = 'Incorrect email or password.';
        }

    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — UMU Student Guild Voting</title>
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

      <h1><?php
        // Safe query even if DB not yet set up
        try { echo e(electionTitle()); } catch(Exception $e) { echo 'UMU Guild Elections'; }
      ?></h1>
      <p>Secure Online Voting Portal</p>
    </div>

    <div class="auth-card-body">

      <?php if (($_GET['msg'] ?? '') === 'login_required'): ?>
        <div class="alert alert-warning" data-auto-dismiss>
          ⚠️ Please sign in to access that page.
        </div>
      <?php endif; ?>

      <?php if (($_GET['msg'] ?? '') === 'logged_out'): ?>
        <div class="alert alert-success" data-auto-dismiss>
          ✅ You have been logged out successfully.
        </div>
      <?php endif; ?>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error">❌ <?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="POST" action="index.php" novalidate autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />

        <div class="form-group">
          <label class="form-label" for="email">University Email</label>
          <input
            class="form-control <?= (!empty($errors) && !$fEmail) ? 'is-invalid' : '' ?>"
            type="email"
            id="email"
            name="email"
            value="<?= e($fEmail) ?>"
            placeholder="baguma.gerald@stud.umu.ac.ug"
            required
            autocomplete="username"
          />
          <div style="font-size:.73rem;color:var(--grey-400);margin-top:.3rem">
            Format: firstname.lastname@stud.umu.ac.ug
          </div>
        </div>

        <div style="height:0"></div>


        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input
            class="form-control"
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
            autocomplete="current-password"
          />
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-2" style="font-size:1rem;padding:.9rem">
          Sign In &rarr;
        </button>
      </form>

      <div class="divider"></div>

      <p class="text-center text-grey" style="font-size:.87rem">
        Not registered? <a href="register.php">Register&rarr;</a>.
      </p>

    </div><!-- .auth-card-body -->
  </div><!-- .auth-card -->
</div><!-- .auth-page -->

<script src="assets/main.js"></script>
</body>
</html>
