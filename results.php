<?php
// results.php — Live election results
require_once __DIR__ . '/config.php';
requireLogin();

$positions = db()->query('SELECT * FROM positions ORDER BY display_order')->fetchAll();
$candidates = db()->query(
    'SELECT c.id, c.position_id, c.full_name, c.vote_count, c.photo_path
     FROM candidates c
     JOIN positions p ON c.position_id = p.id
     ORDER BY p.display_order, c.vote_count DESC, c.full_name'
)->fetchAll();

$byPos = [];
foreach ($candidates as $c) $byPos[$c['position_id']][] = $c;

$totalStudents = (int)db()->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn();
$votedStudents = (int)db()->query('SELECT COUNT(*) FROM users WHERE has_voted=1 AND role="student"')->fetchColumn();
$turnoutPct    = $totalStudents > 0 ? round(($votedStudents / $totalStudents) * 100, 1) : 0;

$pageTitle = 'Live Results';
include __DIR__ . '/includes/header.php';
?>

<div class="page-wrap">

  <div class="page-header">
    <div>
      <h1>Live <span>Results</span></h1>
      <div class="page-subtitle"><?= e(electionTitle()) ?></div>
    </div>
    <span class="live-badge"><span class="live-dot"></span> Live</span>
  </div>

  <!-- Turnout -->
  <div class="turnout-card">
    <div>
      <div class="turnout-label">Voter Turnout</div>
      <div class="turnout-value"><?= $turnoutPct ?>%</div>
      <div class="turnout-sub"><?= number_format($votedStudents) ?> of <?= number_format($totalStudents) ?> registered voters</div>
    </div>
    <div style="flex:1;min-width:180px">
      <div class="vote-bar-track" style="height:12px">
        <div class="vote-bar-fill" data-pct="<?= $turnoutPct ?>" style="width:0%"></div>
      </div>
    </div>
  </div>

  <!-- Results by position -->
  <?php foreach ($positions as $pos):
    $cands = $byPos[$pos['id']] ?? [];
    $total = array_sum(array_column($cands, 'vote_count'));
  ?>
  <div class="result-block">
    <div class="result-block-head"><?= e($pos['title']) ?></div>
    <div style="padding:.6rem 1.4rem">
      <?php if (!$cands): ?>
        <p style="color:var(--grey-400);font-size:.9rem;padding:.5rem 0">No candidates yet.</p>
      <?php else: ?>
        <?php foreach ($cands as $i => $c):
          $pct = $total > 0 ? round(($c['vote_count'] / $total) * 100, 1) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:.85rem;padding:.65rem 0;
                    border-bottom:1px solid var(--grey-100);<?= $i === count($cands)-1 ? 'border:none' : '' ?>">
          <!-- Avatar -->
          <div style="width:38px;height:38px;border-radius:50%;background:<?= ($i === 0 && $total > 0) ? 'var(--yellow);color:var(--black)' : 'var(--grey-100);color:var(--grey-600)' ?>;
                      display:flex;align-items:center;justify-content:center;
                      font-weight:700;font-size:.9rem;flex-shrink:0;overflow:hidden;position:relative">
            <?php if (!empty($c['photo_path'])): ?>
              <img
                src="<?= BASE . '/' . ltrim($c['photo_path'], '/') ?>"
                alt="<?= e($c['full_name']) ?> photo"
                style="width:100%;height:100%;object-fit:cover;display:block"
                onerror="this.style.display='none'"
              />
            <?php endif; ?>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
              <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
            </div>
          </div>
          <!-- Name -->
          <div style="font-weight:600;font-size:.88rem;flex:0 0 165px">
            <?= e($c['full_name']) ?>
            <?php if ($i === 0 && $total > 0): ?>
              <span class="winner-badge">★ Leading</span>
            <?php endif; ?>
          </div>
          <!-- Bar -->
          <div style="flex:1;display:flex;align-items:center;gap:.7rem">
            <div class="vote-bar-track" style="flex:1">
              <div class="vote-bar-fill" data-pct="<?= $pct ?>" style="width:0%"></div>
            </div>
            <span style="font-size:.78rem;font-weight:700;color:var(--grey-600);min-width:38px;text-align:right"><?= $pct ?>%</span>
          </div>
          <!-- Count -->
          <div style="font-size:.82rem;color:var(--grey-600);min-width:55px;text-align:right">
            <?= number_format($c['vote_count']) ?> vote<?= $c['vote_count'] !== 1 ? 's' : '' ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <p style="text-align:center;font-size:.8rem;color:var(--grey-400);margin-top:2rem">
    Results update in real-time · Last loaded: <?= date('D d M Y, H:i:s') ?>
    &nbsp;·&nbsp;
    <a href="results.php" style="color:var(--red)">Refresh</a>
  </p>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
