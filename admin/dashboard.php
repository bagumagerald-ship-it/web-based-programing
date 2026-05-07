<?php
// admin/dashboard.php
require_once __DIR__ . '/../config.php';
requireAdmin();

// Toggle voting open/close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_voting'])) {
    verifyCsrf();
    $cur    = isVotingOpen();
    $newVal = $cur ? '0' : '1';
    db()->prepare("UPDATE settings SET setting_value=? WHERE setting_key='voting_open'")->execute([$newVal]);
    redirect('admin/dashboard.php');
}

// Stats
$totalStudents   = (int)db()->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn();
$votedStudents   = (int)db()->query('SELECT COUNT(*) FROM users WHERE has_voted=1 AND role="student"')->fetchColumn();
$totalCandidates = (int)db()->query('SELECT COUNT(*) FROM candidates')->fetchColumn();
$totalPositions  = (int)db()->query('SELECT COUNT(*) FROM positions')->fetchColumn();
$totalVotes      = (int)db()->query('SELECT COUNT(*) FROM votes')->fetchColumn();
$turnout         = $totalStudents > 0 ? round(($votedStudents / $totalStudents) * 100, 1) : 0;
$votingOpen      = isVotingOpen();

$recentVotes = db()->query(
    'SELECT u.full_name, u.reg_number, v.voted_at, p.title AS position
     FROM votes v
     JOIN users u ON v.user_id = u.id
     JOIN positions p ON v.position_id = p.id
     ORDER BY v.voted_at DESC LIMIT 10'
)->fetchAll();

$pageTitle    = 'Admin Dashboard';
$isAdminPage  = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <h1>Admin <span>Dashboard</span></h1>
      <div class="page-subtitle">Election control panel &mdash; <?= e(electionTitle()) ?></div>
    </div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="toggle_voting" value="1">
      <?php if ($votingOpen): ?>
        <button class="btn btn-danger btn-sm"
          onclick="return confirm('Close voting? Students will no longer be able to cast votes.')">
          ■ Close Voting
        </button>
      <?php else: ?>
        <button class="btn btn-success btn-sm"
          onclick="return confirm('Open voting? Students will be able to cast votes.')">
          ▶ Open Voting
        </button>
      <?php endif; ?>
    </form>
  </div>

  <div class="admin-layout">
    <aside class="admin-sidebar">
      <nav class="sidebar-nav">
        <div class="sidebar-nav-header">Admin Menu</div>
        <a href="dashboard.php"  class="sidebar-nav-link active">📊 Dashboard</a>
        <a href="candidates.php" class="sidebar-nav-link">👤 Candidates</a>
        <a href="users.php"      class="sidebar-nav-link">👥 Voters</a>
        <a href="results.php"    class="sidebar-nav-link">🏆 Results</a>
        <a href="settings.php"   class="sidebar-nav-link">⚙️ Settings</a>
        <a href="<?= BASE ?>/logout.php"  class="sidebar-nav-link">🚪 Logout</a>
      </nav>

      <div style="background:var(--white);border-radius:var(--radius-md);padding:1rem;
                  box-shadow:var(--shadow-sm)">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.08em;color:var(--grey-600);margin-bottom:.5rem">
          Voting Status
        </div>
        <?php if ($votingOpen): ?>
          <span class="badge badge-open">● Open</span>
        <?php else: ?>
          <span class="badge badge-closed">● Closed</span>
        <?php endif; ?>
      </div>
    </aside>

    <div class="admin-main">
      <!-- Stats -->
      <div class="stats-grid">
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
        <div class="stat-card green">
          <div class="stat-label">Candidates</div>
          <div class="stat-value"><?= number_format($totalCandidates) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Positions</div>
          <div class="stat-value"><?= number_format($totalPositions) ?></div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-label">Total Ballot Lines</div>
          <div class="stat-value"><?= number_format($totalVotes) ?></div>
        </div>
      </div>

      <!-- Turnout bar -->
      <div style="background:var(--white);border-radius:var(--radius-md);padding:1.5rem;
                  box-shadow:var(--shadow-sm);margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;margin-bottom:.75rem">
          <span style="font-weight:600">Voter Turnout Progress</span>
          <span style="font-weight:700;color:var(--red)"><?= $turnout ?>%</span>
        </div>
        <div class="vote-bar-track" style="height:14px">
          <div class="vote-bar-fill" data-pct="<?= $turnout ?>" style="width:0%"></div>
        </div>
        <div style="font-size:.8rem;color:var(--grey-600);margin-top:.5rem">
          <?= $votedStudents ?> of <?= $totalStudents ?> registered students have voted
        </div>
      </div>

      <!-- Recent activity -->
      <div style="background:var(--white);border-radius:var(--radius-md);
                  box-shadow:var(--shadow-sm);overflow:hidden">
        <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--grey-200);font-weight:600">
          Recent Voting Activity
        </div>
        <?php if ($recentVotes): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Student</th><th>Reg Number</th><th>Position</th><th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentVotes as $v): ?>
            <tr>
              <td><?= e($v['full_name']) ?></td>
              <td><code><?= e($v['reg_number']) ?></code></td>
              <td><?= e($v['position']) ?></td>
              <td style="font-size:.8rem;color:var(--grey-600)"><?= date('d M Y H:i', strtotime($v['voted_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p style="padding:1.5rem;color:var(--grey-600);font-size:.9rem">No votes have been cast yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
