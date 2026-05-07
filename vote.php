<?php
// vote.php — Student voting interface
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
if (!$user) redirect('index.php');
if (($user['role'] ?? '') === 'admin') redirect('admin/dashboard.php');
// Refresh approval status from DB (prevents stale session after admin approves)
if (!empty($user['id'])) {
    $rowAppr = db()->prepare('SELECT is_approved, has_voted FROM users WHERE id=? AND role="student" LIMIT 1');
    $rowAppr->execute([(int)$user['id']]);
    $row2 = $rowAppr->fetch();
    if ($row2) {
        $user['is_approved'] = (int)($row2['is_approved'] ?? 0);
        $_SESSION['is_approved'] = (int)($row2['is_approved'] ?? 0);
        $_SESSION['has_voted'] = (bool)($row2['has_voted'] ?? false);
    }
}

if ((int)($user['is_approved'] ?? 0) !== 1) {
    redirect('index.php?msg=approval_required');
}


$votingOpen = isVotingOpen();
$errors     = [];
$submitted  = false;

// ── Handle vote submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $votingOpen && !$user['has_voted']) {
    verifyCsrf();

    $positions = db()->query('SELECT id FROM positions ORDER BY display_order')->fetchAll();

    // Validate: one candidate per position
    foreach ($positions as $pos) {
        if (empty($_POST['position_' . $pos['id']])) {
            $errors[] = 'You must select a candidate for every position.';
            break;
        }
        $chk = db()->prepare('SELECT id FROM candidates WHERE id=? AND position_id=? LIMIT 1');
        $chk->execute([(int)$_POST['position_' . $pos['id']], (int)$pos['id']]);
        if (!$chk->fetch()) {
            $errors[] = 'Invalid candidate selected. Please try again.';
            break;
        }
    }

    if (!$errors) {
        try {
            db()->beginTransaction();
            foreach ($positions as $pos) {
                $cid   = (int)$_POST['position_' . $pos['id']];
                $posId = (int)$pos['id'];

                db()->prepare('INSERT INTO votes (user_id, candidate_id, position_id) VALUES (?,?,?)')
                    ->execute([$user['id'], $cid, $posId]);

                db()->prepare('UPDATE candidates SET vote_count = vote_count + 1 WHERE id=?')
                    ->execute([$cid]);
            }
            db()->prepare('UPDATE users SET has_voted=1 WHERE id=?')->execute([$user['id']]);
            $_SESSION['has_voted'] = true;
            db()->commit();
            $submitted = true;
        } catch (Exception $ex) {
            db()->rollBack();
            $errors[] = 'A system error occurred. Please try again.';
        }
    }
}

// Re-read from DB to avoid stale session
$row = db()->prepare('SELECT has_voted FROM users WHERE id=? LIMIT 1');
$row->execute([$user['id']]);
$alreadyVoted = $submitted || (bool)($row->fetch()['has_voted'] ?? false);

// Fetch data
$positions = db()->query('SELECT id, title FROM positions ORDER BY display_order')->fetchAll();
$candidates = db()->query(
    'SELECT c.id, c.position_id, c.full_name, c.manifesto, c.photo_path
     FROM candidates c
     JOIN positions p ON c.position_id = p.id
     ORDER BY p.display_order, c.full_name'
)->fetchAll();

$byPos = [];
foreach ($candidates as $c) $byPos[$c['position_id']][] = $c;

$pageTitle = 'Cast Your Vote';
include __DIR__ . '/includes/header.php';
?>

<div class="page-wrap">

  <?php if ($alreadyVoted): ?>
  <!-- Voted confirmation -->
  <div class="confirm-page">
    <div class="confirm-icon">✓</div>
    <h1 class="confirm-title">Vote Successfully Cast!</h1>
    <p class="confirm-sub">
      Thank you, <strong><?= e($user['name']) ?></strong>.<br>
      Your ballot has been securely recorded. Each student may only vote once.
    </p>
    <?php if (!empty($user['photo_path'])): ?>
      <div style="display:flex;justify-content:center;margin-top:.9rem">
        <img
          src="<?= BASE . '/' . ltrim($user['photo_path'], '/') ?>"
          alt="<?= e($user['name']) ?> photo"
          style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:2px solid var(--grey-200)"
          onerror="this.style.display='none'"
        />
      </div>
    <?php endif; ?>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE ?>/results.php" class="btn btn-primary">View Live Results →</a>
      <a href="<?= BASE ?>/logout.php"  class="btn btn-outline">Sign Out</a>
    </div>
  </div>

  <?php elseif (!$votingOpen): ?>
  <!-- Voting closed -->
  <div class="confirm-page">
    <div class="confirm-icon" style="background:var(--red);color:var(--white)">✗</div>
    <h1 class="confirm-title">Voting is Currently Closed</h1>
    <p class="confirm-sub">The election period has ended or has not yet started. Please contact the guild administration.</p>
    <a href="<?= BASE ?>/results.php" class="btn btn-primary">View Results →</a>
  </div>

  <?php else: ?>
  <!-- Voting form -->
  <div class="page-header">
    <div>
      <h1><?= e(electionTitle()) ?></h1>
      <div class="page-subtitle">Select <strong>one candidate</strong> per position, then submit your ballot.</div>
    </div>
    <span style="background:var(--yellow);color:var(--black);font-size:.75rem;font-weight:700;
                 padding:.3rem 1rem;border-radius:50px;text-transform:uppercase;letter-spacing:.08em">
      🗳 Voting Open
    </span>

    <?php if (!empty($user['photo_path'])): ?>
      <div style="display:flex;align-items:center;gap:.75rem;justify-content:flex-end">
        <img
          src="<?= BASE . '/' . ltrim($user['photo_path'], '/') ?>"
          alt="<?= e($user['name']) ?> photo"
          style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid var(--grey-200)"
          onerror="this.style.display='none'"
        />
        <span style="font-size:.82rem;color:var(--grey-600);font-weight:700">Your Ballot</span>
      </div>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">❌ <?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="<?= BASE ?>/vote.php" id="voteForm">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />

    <div class="positions-list">
      <?php foreach ($positions as $pos):
        $cands = $byPos[$pos['id']] ?? [];
      ?>
      <div class="position-block" id="pos-<?= $pos['id'] ?>">
        <div class="position-title">
          <?= e($pos['title']) ?>
          <span class="position-badge">Select One</span>
        </div>

        <?php if (!$cands): ?>
          <p style="color:var(--grey-400);font-size:.9rem;padding:.5rem 0">No candidates registered for this position yet.</p>
        <?php else: ?>
        <div class="candidates-grid">
          <?php foreach ($cands as $cand): ?>
          <label class="candidate-card">
            <input
              type="radio"
              name="position_<?= $pos['id'] ?>"
              value="<?= $cand['id'] ?>"
              data-position="<?= $pos['id'] ?>"
            />
            <div style="display:flex;align-items:center;gap:.85rem">
              <div class="candidate-avatar" style="overflow:hidden;position:relative">
                <?php if (!empty($cand['photo_path'])): ?>
                  <img
                    src="<?= BASE . '/' . ltrim($cand['photo_path'], '/') ?>"
                    alt="<?= e($cand['full_name']) ?> photo"
                    style="width:100%;height:100%;object-fit:cover;display:block"
                    onerror="this.style.display='none'"
                  />
                <?php endif; ?>
                <div class="candidate-avatar-initial" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
                  <?= strtoupper(substr($cand['full_name'], 0, 1)) ?>
                </div>
              </div>
              <div class="candidate-info">
                <div class="candidate-name"><?= e($cand['full_name']) ?></div>
              </div>
              <div class="vote-indicator" style="margin-left:auto;flex-shrink:0"></div>
            </div>

            <?php if (trim($cand['manifesto'])): ?>
              <div class="candidate-manifesto"><?= e($cand['manifesto']) ?></div>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Sticky submit bar -->
    <div class="vote-submit-bar">
      <div class="vote-status-text">
        <span id="voteStatusText">Select candidates above to continue</span>
      </div>
      <button type="button" id="submitVoteBtn" class="btn btn-secondary" disabled>
        Review &amp; Submit Ballot
      </button>
    </div>
  </form>

  <!-- Confirm ballot modal -->
  <div class="modal-overlay" id="confirmVoteModal">
    <div class="modal">
      <div class="modal-header">
        Confirm Your Ballot
        <button type="button" class="modal-close" data-modal-close="confirmVoteModal">&times;</button>
      </div>
      <div class="modal-body">
        <p style="margin-bottom:1rem;font-size:.9rem;color:var(--grey-600)">
          Please review your selections. <strong>Once submitted, your vote cannot be changed.</strong>
        </p>
        <div id="voteSummary"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" id="cancelSubmitBtn">← Go Back</button>
        <button type="button" class="btn btn-primary btn-sm" id="confirmSubmitBtn">
          ✓ Confirm &amp; Submit
        </button>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
