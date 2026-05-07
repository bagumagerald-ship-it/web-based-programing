<?php
// 
$pageTitle = $pageTitle ?? APP_NAME;
$assetBase = isset($isAdminPage) ? '../assets' : 'assets';
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
  <link rel="stylesheet" href="<?= $assetBase ?>/style.css" />
</head>
<body>

<div class="top-banner">
  <div class="banner-stripe red"></div>
  <div class="banner-stripe yellow"></div>
  <div class="banner-stripe black"></div>
</div>

<nav class="main-nav">
  <a href="<?= BASE ?>/vote.php" class="nav-brand">
    <div class="nav-logo-mark">
      <img src="<?= $assetBase ?>/logo.jpeg" alt="UMU logo" class="nav-school-badge" />
    </div>

    <div class="nav-brand-text">
      <span class="brand-full">Uganda Martyrs University</span>
      <span class="brand-sub">Student Guild Elections</span>
    </div>
  </a>

  <?php $u = currentUser(); if ($u): ?>
    <?php $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>

    <div class="nav-links">
    <?php if ($u['role'] === 'admin'): ?>
      <a href="<?= BASE ?>/admin/dashboard.php" class="nav-link <?= $scriptName === 'dashboard.php' ? 'active' : '' ?>">

         Admin Panel
      </a>
    <?php else: ?>
      <a href="<?= BASE ?>/vote.php" class="nav-link <?= $scriptName === 'vote.php' ? 'active' : '' ?>">
         🗳 Vote
      </a>
      <a href="<?= BASE ?>/results.php" class="nav-link <?= $scriptName === 'results.php' ? 'active' : '' ?>">
         📊 Results
      </a>
    <?php endif; ?>

    <div class="nav-user">
      <?php if (!empty($u['photo_path'])): ?>
        <span class="user-avatar" style="background:none;overflow:hidden;width:36px;height:36px">
          <img
            class="user-avatar-img"
            src="<?= BASE . '/' . ltrim($u['photo_path'], '/') ?>"
            alt="<?= e($u['name']) ?> photo"
            onerror="this.style.display='none'"
            style="width:36px;height:36px;border-radius:50%;object-fit:cover;display:block"
          />
        </span>
      <?php else: ?>
        <span class="user-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
      <?php endif; ?>
      <span class="user-name"><?= e(explode(' ', $u['name'])[0]) ?></span>
    </div>
    <a href="<?= BASE ?>/logout.php" class="btn-logout">Logout</a>
  </div>
  <?php endif; ?>
</nav>
