<?php
// admin/users.php — Admin manages voter accounts
require_once __DIR__ . '/../config.php';
requireAdmin();

$success   = flash('success');
$addOk     = flash('addOk');
$addErr    = flash('addErr');
$errors    = [];

// ── Add single voter ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();

    $reg   = strtoupper(trim($_POST['reg_number'] ?? ''));
    $name  = trim($_POST['full_name']  ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $errs = [];
    if (!$reg)                   $errs[] = 'Registration number is required.';
    elseif (!isValidRegNumber($reg))
        $errs[] = 'Invalid reg number format. Use: 2023-B072-31712';

    if (!$name || strlen($name) < 3) $errs[] = 'Full name must be at least 3 characters.';

    if (!$email)                 $errs[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errs[] = 'Enter a valid email address.';
    elseif (!isUmuStudentEmail($email))
        $errs[] = 'Email must end with @stud.umu.ac.ug (e.g. baguma.gerald@stud.umu.ac.ug)';

    if (!$pass || strlen($pass) < 6) $errs[] = 'Password must be at least 6 characters.';

    if (!$errs) {
        $chk = db()->prepare('SELECT id FROM users WHERE reg_number=? OR email=? LIMIT 1');
        $chk->execute([$reg, $email]);
        if ($chk->fetch()) {
            $errs[] = 'A voter with that registration number or email already exists.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

            // Optional photo upload (admin add)
            $photoPath = null;
            if (!empty($_FILES['photo']['name'] ?? '') && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                $tmpName = (string)($_FILES['photo']['tmp_name'] ?? '');
                $size    = (int)($_FILES['photo']['size'] ?? 0);

                if ($tmpName !== '' && $size > 0) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($tmpName);

                    $maxBytes = 5 * 1024 * 1024; // 5MB
                    if (isset($allowed[$mime]) && $size <= $maxBytes) {
                        $ext = $allowed[$mime];
                        $uploadDirAbs = __DIR__ . '/../uploads/user-photos';
                        if (!is_dir($uploadDirAbs)) {
                            @mkdir($uploadDirAbs, 0777, true);
                        }

                        $safeReg = preg_replace('/[^A-Z0-9\-]/', '', $reg);
                        $filename = $safeReg . '_' . time() . '.' . $ext;
                        $targetAbs = $uploadDirAbs . '/' . $filename;

                        if (move_uploaded_file($tmpName, $targetAbs)) {
                            $photoPath = 'uploads/user-photos/' . $filename;
                        }
                    }
                }
            }

            $stmt = db()->prepare(
                'INSERT INTO users (reg_number, full_name, email, password, role, photo_path) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$reg, $name, $email, $hash, 'student', $photoPath]);
            flash('addOk', "Voter \"$name\" added successfully.");
            redirect('admin/users.php');

        }
    }
    // Store errors in flash and redirect back (keeps modal open via JS)
    flash('addErr', implode('|||', $errs));
    redirect('admin/users.php#addVoter');
}

// ── Bulk CSV import ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk') {
    verifyCsrf();
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('addErr', 'CSV upload failed. Please try again.');
        redirect('admin/users.php');
    }

    $content  = file_get_contents($_FILES['csv_file']['tmp_name']);
    $lines    = array_filter(explode("\n", str_replace("\r", "", $content)));
    $imported = $failed = 0;
    $failMsgs = [];

    foreach ($lines as $i => $line) {
        if ($i === 0) continue; // skip header row
        $row = str_getcsv(trim($line));
        if (count($row) < 4) continue;
        [$r, $n, $em, $p] = array_map('trim', $row);
        $r  = strtoupper($r);
        $em = strtolower($em);

        $rowErrs = [];
        if (!isValidRegNumber($r))        $rowErrs[] = "Bad reg: $r";
        if (strlen($n) < 3)               $rowErrs[] = "Bad name: $n";
        if (!isUmuStudentEmail($em))      $rowErrs[] = "Bad email: $em";
        if (strlen($p) < 6)               $rowErrs[] = "Short password";

        if ($rowErrs) {
            $failed++;
            $failMsgs[] = 'Row ' . ($i + 1) . ': ' . implode(', ', $rowErrs);
            continue;
        }

        $chk = db()->prepare('SELECT id FROM users WHERE reg_number=? OR email=? LIMIT 1');
        $chk->execute([$r, $em]);
        if ($chk->fetch()) {
            $failMsgs[] = "Row " . ($i+1) . ": Duplicate ($em)";
            continue;
        }

        $hash = password_hash($p, PASSWORD_BCRYPT, ['cost' => 10]);
        db()->prepare('INSERT INTO users (reg_number, full_name, email, password, role, photo_path) VALUES (?,?,?,?,?,?)')
            ->execute([$r, $n, $em, $hash, 'student', null]);
        $imported++;
    }

    $msg = "$imported voter(s) imported.";
    if ($failed) $msg .= " $failed row(s) failed.";
    flash('addOk', $msg);
    redirect('admin/users.php');
}

// ── Approve / Reject registration ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    verifyCsrf();
    $id = (int)($_POST['user_id'] ?? 0);
    $val = isset($_POST['approved']) ? (int)$_POST['approved'] : 0;

    if ($id <= 0) {
        flash('addErr', 'Invalid user.');
        redirect('admin/users.php');
    }

    // Only allow toggling non-admins
    $roleRow = db()->prepare('SELECT role FROM users WHERE id=? LIMIT 1');
    $roleRow->execute([$id]);
    $role = $roleRow->fetch()['role'] ?? '';

    if ($role !== 'student') {
        flash('addErr', 'Only student registrations can be approved.');
        redirect('admin/users.php');
    }

    // Ensure session flag cannot incorrectly block the student
    db()->prepare('UPDATE users SET is_approved=? WHERE id=?')->execute([$val === 1 ? 1 : 0, $id]);
    flash('success', $val === 1 ? 'Student approved successfully.' : 'Student approval revoked.');

    // Redirect with a fragment to ensure admin page fully reloads
    redirect('admin/users.php');
}

// ── Delete user ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id === (int)currentUser()['id']) {
        $errors[] = 'You cannot delete your own account.';
    } else {
        db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        flash('success', 'Voter deleted.');
        redirect('admin/users.php');
    }
}

// ── Reset vote ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_vote') {
    verifyCsrf();
    $id = (int)($_POST['user_id'] ?? 0);
    db()->beginTransaction();
    db()->prepare('DELETE FROM votes WHERE user_id=?')->execute([$id]);
    // Recompute all tallies
    db()->exec('UPDATE candidates c SET vote_count =
        (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id)');
    db()->prepare('UPDATE users SET has_voted=0 WHERE id=?')->execute([$id]);
    db()->commit();
    flash('success', 'Vote reset successfully.');
    redirect('admin/users.php');
}

// ── Fetch users (with search) ─────────────────────────────────
$search = trim($_GET['q']    ?? '');
$role   = trim($_GET['role'] ?? '');

$sql    = 'SELECT * FROM users WHERE 1=1';
$params = [];
if ($search) {
    $sql    .= ' AND (full_name LIKE ? OR reg_number LIKE ? OR email LIKE ?)';
    $like    = "%$search%";
    $params  = [$like, $like, $like];
}
if ($role) {
    $sql    .= ' AND role=?';
    $params[] = $role;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Parse stored add errors
$addErrList = $addErr ? explode('|||', $addErr) : [];

$pageTitle   = 'Manage Voters';
$isAdminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <h1>Manage <span>Voters</span></h1>
      <div class="page-subtitle">Add and manage registered voter accounts</div>
    </div>
    <button class="btn btn-primary btn-sm" data-modal-open="addVoterModal">+ Add Voter</button>
  </div>

  <div class="admin-layout">
    <aside class="admin-sidebar">
      <nav class="sidebar-nav">
        <div class="sidebar-nav-header">Admin Menu</div>
        <a href="dashboard.php"  class="sidebar-nav-link">📊 Dashboard</a>
        <a href="candidates.php" class="sidebar-nav-link">👤 Candidates</a>
        <a href="users.php"      class="sidebar-nav-link active">👥 Voters</a>
        <a href="results.php"    class="sidebar-nav-link">🏆 Results</a>
        <a href="settings.php"   class="sidebar-nav-link">⚙️ Settings</a>
        <a href="../logout.php"  class="sidebar-nav-link">🚪 Logout</a>
      </nav>
    </aside>

    <div class="admin-main">

      <?php if ($success): ?>
        <div class="alert alert-success" data-auto-dismiss>✅ <?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($addOk): ?>
        <div class="alert alert-success" data-auto-dismiss>✅ <?= e($addOk) ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error">❌ <?= e($e) ?></div>
      <?php endforeach; ?>
      <?php if ($addErrList): ?>
        <div class="alert alert-error">
          <div>❌ Could not add voter:<br>
            <?php foreach ($addErrList as $ae): ?>
              &bull; <?= e($ae) ?><br>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Bulk import form (hidden file input) -->
      <form method="POST" action="users.php" enctype="multipart/form-data" id="csvForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="bulk">
        <input type="file" name="csv_file" id="csvUpload" accept=".csv" style="display:none">
      </form>

      <!-- Search & filter -->
      <form method="GET" action="users.php" class="search-bar">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="search-input"
            placeholder="Search by name, reg number or email…"
            value="<?= e($search) ?>" id="tableSearch" />
        </div>
        <select name="role" class="form-control" style="width:140px;padding:.68rem 1rem">
          <option value="">All roles</option>
          <option value="student" <?= $role==='student'?'selected':'' ?>>Students only</option>
          <option value="admin"   <?= $role==='admin'  ?'selected':'' ?>>Admins only</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($search || $role): ?>
          <a href="users.php" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
        <label for="csvUpload" class="btn btn-secondary btn-sm" style="cursor:pointer"
          title="Import voters from CSV file">
          📥 Import CSV
        </label>
        <button type="button" class="btn btn-outline btn-sm" onclick="downloadCSVTemplate()">
          ⬇ Template
        </button>
      </form>

      <p style="font-size:.82rem;color:var(--grey-600);margin-bottom:1rem">
        <?= count($users) ?> record<?= count($users) !== 1 ? 's' : '' ?> found
      </p>

      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Reg Number</th>
              <th>Email</th>
              <th>Role</th>
              <th>Voted</th>
              <th>Added</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr data-search-row>
              <td style="color:var(--grey-400);font-size:.82rem"><?= $i + 1 ?></td>
              <td><strong><?= e($u['full_name']) ?></strong></td>
              <td><code><?= e($u['reg_number']) ?></code></td>
              <td style="font-size:.83rem"><?= e($u['email']) ?></td>
              <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
              <td>
                <?php if ($u['has_voted']): ?>
                  <span class="badge badge-voted">✓ Voted</span>
                <?php else: ?>
                  <span style="font-size:.8rem;color:var(--grey-400)">Not yet</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.8rem;color:var(--grey-600)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                  <?php if ((int)$u['role'] === 0) {} ?>

                  <?php if (($u['role'] ?? '') === 'student' && (int)($u['is_approved'] ?? 0) === 0): ?>
                    <form method="POST" action="users.php" style="display:inline">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="approved" value="1">
                      <button type="submit" class="btn btn-success btn-sm"
                        data-confirm="Approve <?= e($u['full_name']) ?>?">
                        Approve
                      </button>
                    </form>

                    <form method="POST" action="users.php" style="display:inline">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="approved" value="0">
                      <button type="submit" class="btn btn-outline btn-sm"
                        data-confirm="Reject <?= e($u['full_name']) ?>?">
                        Reject
                      </button>
                    </form>
                  <?php endif; ?>

                  <?php if ($u['has_voted'] && $u['role'] === 'student'): ?>
                  <form method="POST" action="users.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action"  value="reset_vote">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-yellow btn-sm"
                      data-confirm="Reset vote for <?= e($u['full_name']) ?>? This will remove their cast ballot.">
                      Reset Vote
                    </button>
                  </form>
                  <?php endif; ?>

                  <?php if ((int)$u['id'] !== (int)currentUser()['id']): ?>
                  <form method="POST" action="users.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action"  value="delete">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Permanently delete voter <?= e($u['full_name']) ?>?">
                      Delete
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
            </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:var(--grey-400);padding:2.5rem">
                No voters found. Use the <strong>Add Voter</strong> button to register students.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- CSV format help -->
      <details style="margin-top:1rem;font-size:.82rem;color:var(--grey-600)">
        <summary style="cursor:pointer;font-weight:600">CSV Import Format</summary>
        <div style="margin-top:.75rem;background:var(--grey-50);padding:1rem;
                    border-radius:var(--radius-sm);border:1px solid var(--grey-200)">
          <p style="margin-bottom:.5rem">File must have a header row then one voter per line:</p>
          <code style="display:block;margin-bottom:.5rem">reg_number,full_name,email,password</code>
          <code>2023-B072-31712,Baguma Gerald,baguma.gerald@stud.umu.ac.ug,Pass@1234</code>
          <p style="margin-top:.5rem">All emails must end in @stud.umu.ac.ug &bull; Reg format: YYYY-A000-00000</p>
        </div>
      </details>
    </div>
  </div>
</div>

<!-- Add Voter Modal -->
<div class="modal-overlay" id="addVoterModal" <?= $addErrList ? 'style="display:flex"' : '' ?>>
  <div class="modal">
    <div class="modal-header">
      Add New Voter
      <button type="button" class="modal-close" data-modal-close="addVoterModal">&times;</button>
    </div>
    <form method="POST" action="users.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Registration Number *</label>
          <input class="form-control" type="text" name="reg_number"
            placeholder="2023-B072-31712" required
            pattern="\d{4}-[A-Za-z]\d{3}-\d{5}"
            title="Format: 2023-B072-31712" />
          <div style="font-size:.72rem;color:var(--grey-400);margin-top:.25rem">
            Format: YYYY-A000-00000
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-control" type="text" name="full_name"
            placeholder="As on student ID" required minlength="3" />
        </div>
        <div class="form-group">
          <label class="form-label">University Email *</label>
          <input class="form-control" type="email" name="email"
            placeholder="baguma.gerald@stud.umu.ac.ug" required />
          <div style="font-size:.72rem;color:var(--grey-400);margin-top:.25rem">
            Must end with @stud.umu.ac.ug
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Password *</label>
          <input class="form-control" type="password" name="password"
            placeholder="Min 6 characters" required minlength="6" />
          <div style="font-size:.72rem;color:var(--grey-400);margin-top:.25rem">
            Provide this password to the student securely.
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Student Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept="image/*" />
          <div style="font-size:.72rem;color:var(--grey-400);margin-top:.25rem">
            JPG/PNG/WEBP (max 5MB). Shown on the ballot.
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" data-modal-close="addVoterModal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Add Voter</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
