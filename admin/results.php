<?php
// admin/results.php — Detailed election results for admin
require_once __DIR__ . '/../config.php';
requireAdmin();

$positions = db()->query('SELECT * FROM positions ORDER BY display_order')->fetchAll();
$candidates = db()->query(
    'SELECT c.id, c.position_id, c.full_name, c.vote_count,
            p.title AS position_title
     FROM candidates c
     JOIN positions p ON c.position_id = p.id
     ORDER BY p.display_order, c.vote_count DESC, c.full_name'
)->fetchAll();

$byPos = [];
foreach ($candidates as $c) $byPos[$c['position_id']][] = $c;

$totalStudents = (int)db()->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn();
$votedStudents = (int)db()->query('SELECT COUNT(*) FROM users WHERE has_voted=1 AND role="student"')->fetchColumn();
$turnout       = $totalStudents > 0 ? round(($votedStudents / $totalStudents) * 100, 1) : 0;

$pageTitle   = 'Election Results';
$isAdminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <h1>Election <span>Results</span></h1>
      <div class="page-subtitle"><?= e(electionTitle()) ?> — Admin view</div>
    </div>
    <span style="font-size:.78rem;color:var(--grey-600)">
      Updated: <?= date('d M Y H:i:s') ?>
    </span>
  </div>

  <div class="admin-layout">
    <aside class="admin-sidebar">
      <nav class="sidebar-nav">
        <div class="sidebar-nav-header">Admin Menu</div>
        <a href="dashboard.php"  class="sidebar-nav-link">📊 Dashboard</a>
        <a href="candidates.php" class="sidebar-nav-link">👤 Candidates</a>
        <a href="users.php"      class="sidebar-nav-link">👥 Voters</a>
        <a href="results.php"    class="sidebar-nav-link active">🏆 Results</a>
        <a href="settings.php"   class="sidebar-nav-link">⚙️ Settings</a>
        <a href="../logout.php"  class="sidebar-nav-link">🚪 Logout</a>
      </nav>
    </aside>

    <div class="admin-main">

      <!-- Summary stats -->
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat-card">
          <div class="stat-label">Registered Voters</div>
          <div class="stat-value"><?= number_format($totalStudents) ?></div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-label">Votes Cast</div>
          <div class="stat-value"><?= number_format($votedStudents) ?></div>
        </div>
        <div class="stat-card black">
          <div class="stat-label">Turnout</div>
          <div class="stat-value"><?= $turnout ?>%</div>
        </div>
      </div>

      <!-- Turnout bar -->
      <div style="background:var(--white);border-radius:var(--radius-md);padding:1.25rem 1.5rem;
                  box-shadow:var(--shadow-sm);margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.85rem">
          <span style="font-weight:600">Overall Turnout</span>
          <strong style="color:var(--red)"><?= $turnout ?>%</strong>
        </div>
        <div class="vote-bar-track" style="height:12px">
          <div class="vote-bar-fill" data-pct="<?= $turnout ?>" style="width:0%"></div>
        </div>
      </div>

      <!-- Results by position -->
      <?php foreach ($positions as $pos):
        $cands = $byPos[$pos['id']] ?? [];
        $total = array_sum(array_column($cands, 'vote_count'));
      ?>
      <div class="data-table-wrap" style="margin-bottom:1.4rem">
        <div style="background:var(--black);color:var(--white);padding:.9rem 1.4rem;
                    display:flex;justify-content:space-between;align-items:center">
          <span style="font-family:var(--font-display);font-size:1rem;font-weight:700">
            <?= e($pos['title']) ?>
          </span>
          <span style="font-size:.75rem;color:var(--grey-400)">
            <?= $total ?> vote<?= $total !== 1 ? 's' : '' ?> total
          </span>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Candidate</th>
              <th>Votes</th>
              <th>Share</th>
              <th style="width:200px">Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$cands): ?>
              <tr>
                <td colspan="5" style="text-align:center;color:var(--grey-400);padding:1.5rem">
                  No candidates for this position.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($cands as $i => $c):
                $pct = $total > 0 ? round(($c['vote_count'] / $total) * 100, 1) : 0;
                $isLeader = ($i === 0 && $total > 0);
              ?>
              <tr style="<?= $isLeader ? 'background:#fffbec' : '' ?>">
                <td>
                  <strong style="color:<?= $isLeader ? 'var(--yellow-dk)' : 'var(--grey-400)' ?>">
                    #<?= $i + 1 ?> <?= $isLeader ? '🏆' : '' ?>
                  </strong>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:.65rem">
                    <div style="width:32px;height:32px;border-radius:50%;
                                background:<?= $isLeader ? 'var(--yellow);color:var(--black)' : 'var(--grey-100);color:var(--grey-600)' ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-weight:700;font-size:.85rem;flex-shrink:0">
                      <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
                    </div>
                    <strong><?= e($c['full_name']) ?></strong>
                  </div>
                </td>
                <td>
                  <strong style="font-size:1.15rem;color:var(--red)"><?= number_format($c['vote_count']) ?></strong>
                </td>
                <td>
                  <strong><?= $pct ?>%</strong>
                </td>
                <td>
                  <div class="vote-bar-track">
                    <div class="vote-bar-fill" data-pct="<?= $pct ?>" style="width:0%"></div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>

      <p style="text-align:center;font-size:.8rem;color:var(--grey-400);margin-top:.5rem">
        <a href="results.php" style="color:var(--red)">↺ Refresh results</a>
      </p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
