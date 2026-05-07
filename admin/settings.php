<?php
// admin/settings.php — Election settings
require_once __DIR__ . '/../config.php';
requireAdmin();

$success = flash('success');
$errors  = [];

// ── Save general settings ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verifyCsrf();
    $title = trim($_POST['election_title'] ?? '');
    $open  = isset($_POST['voting_open']) ? '1' : '0';

    if (!$title) {
        $errors[] = 'Election title cannot be empty.';
    } else {
        db()->prepare("UPDATE settings SET setting_value=? WHERE setting_key='election_title'")->execute([$title]);
        db()->prepare("UPDATE settings SET setting_value=? WHERE setting_key='voting_open'")->execute([$open]);
        flash('success', 'Settings saved successfully.');
        redirect('admin/settings.php');
    }
}

// ── Add position ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_position') {
    verifyCsrf();
    $title = trim($_POST['pos_title'] ?? '');
    if (!$title) {
        $errors[] = 'Position title cannot be empty.';
    } else {
        // Check for duplicate
        $dup = db()->prepare('SELECT id FROM positions WHERE title=? LIMIT 1');
        $dup->execute([$title]);
        if ($dup->fetch()) {
            $errors[] = "A position named \"$title\" already exists.";
        } else {
            $maxOrd = db()->query('SELECT COALESCE(MAX(display_order),0)+1 FROM positions')->fetchColumn();
            db()->prepare('INSERT INTO positions (title, display_order) VALUES (?,?)')->execute([$title, $maxOrd]);
            flash('success', "Position \"$title\" added.");
            redirect('admin/settings.php');
        }
    }
}

// ── Delete position ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_position') {
    verifyCsrf();
    $id = (int)($_POST['position_id'] ?? 0);
    // Check if any votes have been cast for candidates in this position
    $voteCheck = db()->prepare(
        'SELECT COUNT(*) FROM votes WHERE position_id=?'
    );
    $voteCheck->execute([$id]);
    if ((int)$voteCheck->fetchColumn() > 0) {
        $errors[] = 'Cannot delete a position that already has votes cast. Reset all votes first.';
    } else {
        $nameRow = db()->prepare('SELECT title FROM positions WHERE id=? LIMIT 1');
        $nameRow->execute([$id]);
        $posName = $nameRow->fetch()['title'] ?? '';
        db()->prepare('DELETE FROM positions WHERE id=?')->execute([$id]);
        flash('success', "Position \"$posName\" deleted.");
        redirect('admin/settings.php');
    }
}

// ── Reset ALL votes ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_all') {
    verifyCsrf();
    db()->exec('DELETE FROM votes');
    db()->exec('UPDATE candidates SET vote_count = 0');
    db()->exec('UPDATE users SET has_voted = 0 WHERE role = "student"');
    flash('success', 'All votes have been reset. Election is ready to start fresh.');
    redirect('admin/settings.php');
}

// ── Fetch current settings ────────────────────────────────────
$settings = [];
foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$positions = db()->query('SELECT * FROM positions ORDER BY display_order')->fetchAll();

$pageTitle   = 'Election Settings';
$isAdminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <h1>Election <span>Settings</span></h1>
      <div class="page-subtitle">Configure election title, voting status and positions</div>
    </div>
  </div>

  <div class="admin-layout">
    <aside class="admin-sidebar">
      <nav class="sidebar-nav">
        <div class="sidebar-nav-header">Admin Menu</div>
        <a href="dashboard.php"  class="sidebar-nav-link">📊 Dashboard</a>
        <a href="candidates.php" class="sidebar-nav-link">👤 Candidates</a>
        <a href="users.php"      class="sidebar-nav-link">👥 Voters</a>
        <a href="results.php"    class="sidebar-nav-link">🏆 Results</a>
        <a href="settings.php"   class="sidebar-nav-link active">⚙️ Settings</a>
        <a href="../logout.php"  class="sidebar-nav-link">🚪 Logout</a>
      </nav>
    </aside>

    <div class="admin-main">
      <?php if ($success): ?>
        <div class="alert alert-success" data-auto-dismiss>✅ <?= e($success) ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error">❌ <?= e($err) ?></div>
      <?php endforeach; ?>

      <!-- ── General settings ──────────────────────────────── -->
      <div style="background:var(--white);border-radius:var(--radius-md);
                  box-shadow:var(--shadow-sm);padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="font-family:var(--font-display);font-size:1.1rem;margin-bottom:1.25rem;
                   padding-bottom:.75rem;border-bottom:2px solid var(--grey-200)">
          General Settings
        </h2>
        <form method="POST" action="settings.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action"     value="save">

          <div class="form-group">
            <label class="form-label">Election Title</label>
            <input class="form-control" type="text" name="election_title"
              value="<?= e($settings['election_title'] ?? '') ?>" required />
          </div>

          <div class="form-group">
            <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer">
              <input type="checkbox" name="voting_open" value="1"
                <?= ($settings['voting_open'] ?? '0') === '1' ? 'checked' : '' ?>
                style="width:18px;height:18px;accent-color:var(--red);cursor:pointer" />
              <span style="font-weight:600">Voting is Open</span>
            </label>
            <p style="font-size:.8rem;color:var(--grey-600);margin-top:.3rem;margin-left:27px">
              Uncheck to prevent students from casting votes (e.g. before election day or after polls close).
            </p>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
        </form>
      </div>

      <!-- ── Positions management ──────────────────────────── -->
      <div style="background:var(--white);border-radius:var(--radius-md);
                  box-shadow:var(--shadow-sm);padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="font-family:var(--font-display);font-size:1.1rem;margin-bottom:1.25rem;
                   padding-bottom:.75rem;border-bottom:2px solid var(--grey-200)">
          Election Positions
        </h2>

        <table class="data-table" style="margin-bottom:1.25rem">
          <thead>
            <tr>
              <th>Order</th>
              <th>Position Title</th>
              <th>Candidates</th>
              <th>Votes Cast</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($positions as $p):
              $cndCnt = (int)db()->prepare('SELECT COUNT(*) FROM candidates WHERE position_id=?')
                          ->execute([$p['id']]) ? db()->query(
                            'SELECT COUNT(*) FROM candidates WHERE position_id=' . (int)$p['id']
                          )->fetchColumn() : 0;
              // Simpler approach:
              $cq = db()->prepare('SELECT COUNT(*) FROM candidates WHERE position_id=?');
              $cq->execute([$p['id']]);
              $cndCnt = (int)$cq->fetchColumn();

              $vq = db()->prepare('SELECT COUNT(*) FROM votes WHERE position_id=?');
              $vq->execute([$p['id']]);
              $votesCnt = (int)$vq->fetchColumn();
            ?>
            <tr>
              <td style="color:var(--grey-400)"><?= $p['display_order'] ?></td>
              <td><strong><?= e($p['title']) ?></strong></td>
              <td><?= $cndCnt ?> candidate<?= $cndCnt !== 1 ? 's' : '' ?></td>
              <td>
                <?php if ($votesCnt > 0): ?>
                  <span class="badge badge-voted"><?= $votesCnt ?> votes</span>
                <?php else: ?>
                  <span style="color:var(--grey-400);font-size:.82rem">None yet</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" action="settings.php" style="display:inline">
                  <input type="hidden" name="csrf_token"   value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="action"        value="delete_position">
                  <input type="hidden" name="position_id"   value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                    data-confirm="Delete position '<?= e($p['title']) ?>'? All candidates in this position will also be deleted.">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$positions): ?>
            <tr>
              <td colspan="5" style="text-align:center;color:var(--grey-400);padding:1.5rem">
                No positions yet.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Add position -->
        <form method="POST" action="settings.php"
          style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action"     value="add_position">
          <div style="flex:1;min-width:220px">
            <label class="form-label">New Position Title</label>
            <input class="form-control" type="text" name="pos_title"
              placeholder="e.g. Speaker of Parliament" required />
          </div>
          <button type="submit" class="btn btn-secondary btn-sm">+ Add Position</button>
        </form>
      </div>

      <!-- ── Danger zone ──────────────────────────────────── -->
      <div style="background:var(--white);border-radius:var(--radius-md);
                  box-shadow:var(--shadow-sm);padding:1.5rem;
                  border:2px solid rgba(192,0,26,.35)">
        <h2 style="font-family:var(--font-display);font-size:1.1rem;color:var(--red);
                   margin-bottom:.5rem">
          ⚠ Danger Zone
        </h2>
        <p style="font-size:.88rem;color:var(--grey-600);margin-bottom:1.25rem;line-height:1.6">
          Resetting all votes <strong>permanently deletes every ballot</strong> cast and resets
          all voter statuses to &ldquo;not voted&rdquo;. Candidate tallies are also zeroed out.
          Use only for testing or to restart the election. <strong>This cannot be undone.</strong>
        </p>
        <form method="POST" action="settings.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action"     value="reset_all">
          <button type="submit" class="btn btn-danger btn-sm"
            data-confirm="PERMANENTLY DELETE all votes and reset all voters? This action cannot be undone.">
            🗑 Reset All Votes
          </button>
        </form>
      </div>

    </div><!-- .admin-main -->
  </div><!-- .admin-layout -->
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
