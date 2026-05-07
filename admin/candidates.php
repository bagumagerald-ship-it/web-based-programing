<?php
// admin/candidates.php Manage election candidates
require_once __DIR__ . '/../config.php';
requireAdmin();

$success = flash('success');
$errors  = [];

$positions = db()->query('SELECT * FROM positions ORDER BY display_order')->fetchAll();

// ── Add candidate ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $name      = trim($_POST['full_name']    ?? '');
    $posId     = (int)($_POST['position_id'] ?? 0);
    $manifesto = trim($_POST['manifesto']    ?? '');

    if (!$name)  $errors[] = 'Candidate name is required.';
    if (!$posId) $errors[] = 'Please select a position.';

    if (!$errors) {
        $photoPath = null;
        if (!empty($_FILES['photo']['name'] ?? '') && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['photo']['tmp_name']);
            $size  = (int)($_FILES['photo']['size'] ?? 0);
            $maxBytes = 5 * 1024 * 1024; // 5MB

            if (isset($allowed[$mime]) && $size > 0 && $size <= $maxBytes) {
                $ext = $allowed[$mime];
                $uploadDirAbs = __DIR__ . '/../uploads/candidate-photos';
                if (!is_dir($uploadDirAbs)) {
                    @mkdir($uploadDirAbs, 0777, true);
                }
                $safeName = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
                $filename = $safeName . '_' . time() . '.' . $ext;
                $targetAbs = $uploadDirAbs . '/' . $filename;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetAbs)) {
                    $photoPath = 'uploads/candidate-photos/' . $filename;
                }
            }
        }

        db()->prepare(
            'INSERT INTO candidates (position_id, full_name, manifesto, photo_path) VALUES (?,?,?,?)'
        )->execute([$posId, $name, $manifesto, $photoPath]);
        flash('success', "Candidate \"$name\" added successfully.");
        redirect('admin/candidates.php');
    }
}

// ── Edit candidate ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verifyCsrf();
    $id        = (int)($_POST['candidate_id'] ?? 0);
    $name      = trim($_POST['full_name']     ?? '');
    $posId     = (int)($_POST['position_id']  ?? 0);
    $manifesto = trim($_POST['manifesto']     ?? '');

    if (!$name)  $errors[] = 'Candidate name is required.';
    if (!$posId) $errors[] = 'Please select a position.';

    if (!$errors) {
        $photoPath = null;
        if (!empty($_FILES['photo']['name'] ?? '') && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['photo']['tmp_name']);
            $size  = (int)($_FILES['photo']['size'] ?? 0);
            $maxBytes = 5 * 1024 * 1024;

            if (isset($allowed[$mime]) && $size > 0 && $size <= $maxBytes) {
                $ext = $allowed[$mime];
                $uploadDirAbs = __DIR__ . '/../uploads/candidate-photos';
                if (!is_dir($uploadDirAbs)) {
                    @mkdir($uploadDirAbs, 0777, true);
                }
                $safeName = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
                $filename = $safeName . '_' . time() . '.' . $ext;
                $targetAbs = $uploadDirAbs . '/' . $filename;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetAbs)) {
                    $photoPath = 'uploads/candidate-photos/' . $filename;
                }
            }
        }

        if ($photoPath !== null) {
            db()->prepare(
                'UPDATE candidates SET full_name=?, position_id=?, manifesto=?, photo_path=? WHERE id=?'
            )->execute([$name, $posId, $manifesto, $photoPath, $id]);
        } else {
            db()->prepare(
                'UPDATE candidates SET full_name=?, position_id=?, manifesto=? WHERE id=?'
            )->execute([$name, $posId, $manifesto, $id]);
        }
        flash('success', 'Candidate updated.');
        redirect('admin/candidates.php');
    }
}

// Delete candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $id   = (int)($_POST['candidate_id'] ?? 0);
    $name = db()->prepare('SELECT full_name FROM candidates WHERE id=? LIMIT 1');
    $name->execute([$id]);
    $row  = $name->fetch();
    db()->prepare('DELETE FROM candidates WHERE id=?')->execute([$id]);
    flash('success', 'Candidate "' . ($row['full_name'] ?? '') . '" removed.');
    redirect('admin/candidates.php');
}

// Fetch all candidates
$candidates = db()->query(
    'SELECT c.*, p.title AS position_title
     FROM candidates c
     JOIN positions p ON c.position_id = p.id
     ORDER BY p.display_order, c.full_name'
)->fetchAll();

$pageTitle   = 'Manage Candidates';
$isAdminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <h1>Manage <span>Candidates</span></h1>
      <div class="page-subtitle">Add, edit or remove election candidates</div>
    </div>
    <button class="btn btn-primary btn-sm" data-modal-open="addCandidateModal">
      + Add Candidate
    </button>
  </div>

  <div class="admin-layout">
    <aside class="admin-sidebar">
      <nav class="sidebar-nav">
        <div class="sidebar-nav-header">Admin Menu</div>
        <a href="dashboard.php"  class="sidebar-nav-link">📊 Dashboard</a>
        <a href="candidates.php" class="sidebar-nav-link active">👤 Candidates</a>
        <a href="users.php"      class="sidebar-nav-link">👥 Voters</a>
        <a href="results.php"    class="sidebar-nav-link">🏆 Results</a>
        <a href="settings.php"   class="sidebar-nav-link">⚙️ Settings</a>
        <a href="../logout.php"  class="sidebar-nav-link">🚪 Logout</a>
      </nav>

      <!-- Counts by position -->
      <div style="background:var(--white);border-radius:var(--radius-md);
                  box-shadow:var(--shadow-sm);overflow:hidden">
        <div style="background:var(--black);color:var(--yellow);font-size:.68rem;
                    font-weight:700;text-transform:uppercase;letter-spacing:.1em;
                    padding:.7rem 1.15rem">
          Candidates Per Position
        </div>
        <?php foreach ($positions as $p):
          $cnt = db()->prepare('SELECT COUNT(*) FROM candidates WHERE position_id=?');
          $cnt->execute([$p['id']]);
          $c = (int)$cnt->fetchColumn();
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:.7rem 1.15rem;border-bottom:1px solid var(--grey-100);
                    font-size:.82rem;">
          <span style="color:var(--grey-600)"><?= e($p['title']) ?></span>
          <strong style="color:<?= $c > 0 ? 'var(--black)' : 'var(--grey-300)' ?>"><?= $c ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </aside>

    <div class="admin-main">
      <?php if ($success): ?>
        <div class="alert alert-success" data-auto-dismiss>✅ <?= e($success) ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error">❌ <?= e($err) ?></div>
      <?php endforeach; ?>

      <!-- Search bar -->
      <div class="search-bar">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" id="tableSearch" class="search-input"
            placeholder="Search candidates by name or position…" />
        </div>
        <span style="font-size:.82rem;color:var(--grey-600)">
          <?= count($candidates) ?> candidate<?= count($candidates) !== 1 ? 's' : '' ?>
        </span>
      </div>

      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Candidate</th>
              <th>Position</th>
              <th>Votes</th>
              <th>Manifesto</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$candidates): ?>
            <tr>
              <td colspan="6" style="text-align:center;color:var(--grey-400);padding:2.5rem">
                No candidates yet. Click <strong>Add Candidate</strong> to get started.
              </td>
            </tr>
            <?php endif; ?>

            <?php foreach ($candidates as $i => $c): ?>
            <tr data-search-row>
              <td style="color:var(--grey-400);font-size:.82rem"><?= $i + 1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:.75rem">
                  <div style="width:34px;height:34px;border-radius:50%;background:var(--black);
                              color:var(--yellow);display:flex;align-items:center;
                              justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0">
                    <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
                  </div>
                  <strong><?= e($c['full_name']) ?></strong>
                </div>
              </td>
              <td style="font-size:.85rem"><?= e($c['position_title']) ?></td>
              <td>
                <strong style="color:var(--red);font-size:1.05rem"><?= $c['vote_count'] ?></strong>
              </td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;
                         white-space:nowrap;font-size:.82rem;color:var(--grey-600)">
                <?= $c['manifesto'] ? e($c['manifesto']) : '<em style="color:var(--grey-300)">—</em>' ?>
              </td>
              <td>
                <div style="display:flex;gap:.4rem">
                  <button class="btn btn-yellow btn-sm"
                    data-edit-candidate="<?= $c['id'] ?>"
                    data-name="<?= e($c['full_name']) ?>"
                    data-position="<?= $c['position_id'] ?>"
                    data-manifesto="<?= e($c['manifesto'] ?? '') ?>">
                    Edit
                  </button>
                  <form method="POST" action="candidates.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action"       value="delete">
                    <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Delete candidate <?= e($c['full_name']) ?>? This cannot be undone.">
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div><!-- .data-table-wrap -->
    </div><!-- .admin-main -->
  </div><!-- .admin-layout -->
</div><!-- .page-wrap -->

<!-- ── Add Candidate Modal -->
<div class="modal-overlay" id="addCandidateModal">
  <div class="modal">
    <div class="modal-header">
      Add New Candidate
      <button type="button" class="modal-close" data-modal-close="addCandidateModal">&times;</button>
    </div>
    <form method="POST" action="candidates.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action"     value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-control" type="text" name="full_name"
            placeholder="Candidate's full name" required />
        </div>
        <div class="form-group">
          <label class="form-label">Position *</label>
          <select class="form-control" name="position_id" required>
            <option value="">— Select a position —</option>
            <?php foreach ($positions as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Manifesto (optional)</label>
          <textarea class="form-control" name="manifesto" rows="3"
            placeholder="Brief manifesto or vision statement…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Candidate Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept="image/*" />
          <div style="font-size:.72rem;color:var(--grey-400);margin-top:.25rem">
            JPG/PNG/WEBP (max 5MB). Shown on ballot and results.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm"
          data-modal-close="addCandidateModal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Add Candidate</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Candidate Modal  -->
<div class="modal-overlay" id="editCandidateModal">
  <div class="modal">
    <div class="modal-header">
      Edit Candidate
      <button type="button" class="modal-close" data-modal-close="editCandidateModal">&times;</button>
    </div>
    <form method="POST" action="candidates.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token"    value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action"         value="edit">
      <input type="hidden" name="candidate_id"   value="">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-control" type="text" name="full_name" required />
        </div>
        <div class="form-group">
          <label class="form-label">Position *</label>
          <select class="form-control" name="position_id" required>
            <option value="">— Select a position —</option>
            <?php foreach ($positions as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Manifesto</label>
          <textarea class="form-control" name="manifesto" rows="3"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Candidate Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept="image/*" />
          <div style="font-size:.72rem;color:var(--grey-400);margin-top:.25rem">
            Upload a new photo to replace the current one.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm"
          data-modal-close="editCandidateModal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
