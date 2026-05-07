<?php

require_once __DIR__ . '/config.php';

$adminPassword = 'umu@2026';
$hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $stmt = db()->prepare("UPDATE users SET password=? WHERE reg_number='ADMIN-A000-00001'");
    $stmt->execute([$hash]);
    $affected = $stmt->rowCount();

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Setup — UMU Voting</title>
    <style>
      body{font-family:sans-serif;background:#faf9f6;display:flex;align-items:center;
           justify-content:center;min-height:100vh;margin:0}
      .box{background:#fff;border-radius:12px;padding:2rem 2.5rem;max-width:520px;width:100%;
           box-shadow:0 8px 32px rgba(0,0,0,.12)}
      h1{color:#0d0d0d;font-size:1.5rem;margin-bottom:1rem}
      .ok{color:#1a7a3c;background:#edfaf3;border-left:4px solid #1a7a3c;
          padding:.85rem 1rem;border-radius:6px;margin-bottom:1rem}
      .warn{color:#7a5800;background:#fffbec;border-left:4px solid #f5b800;
            padding:.85rem 1rem;border-radius:6px;margin-bottom:1rem}
      code{background:#f2f2f2;padding:.2em .5em;border-radius:4px;font-size:.9em}
      table{width:100%;border-collapse:collapse;margin:.75rem 0}
      td{padding:.5rem .75rem;border-bottom:1px solid #eee;font-size:.9rem}
      td:first-child{color:#555;width:140px}
      a{color:#c0001a;font-weight:600}
    </style></head><body><div class="box">';

    if ($affected > 0) {
        echo '<h1>✅ Setup Complete</h1>';
        echo '<div class="ok">Admin password has been set successfully.</div>';
        echo '<table>
          <tr><td>Email</td><td><strong>admin@umu.ac.ug</strong></td></tr>
          <tr><td>Reg Number</td><td><strong>ADMIN-A000-00001</strong></td></tr>
          <tr><td>Password</td><td><strong>' . htmlspecialchars($adminPassword) . '</strong></td></tr>
        </table>';
        echo '<div class="warn">
          <strong>⚠ Security:</strong> Delete this file from your server now!<br>
          <code>rm setup_admin.php</code>
        </div>';
        echo '<p><a href="index.php">→ Go to Login Page</a></p>';
    } else {
        echo '<h1>⚠ No rows updated</h1>';
        echo '<p>Admin account not found. Make sure you imported <code>database.sql</code> first.</p>';
    }

    echo '</div></body></html>';

} catch (Exception $ex) {
    echo '<div style="color:red;font-family:monospace;padding:2rem">'
       . '<strong>Error:</strong> ' . htmlspecialchars($ex->getMessage()) . '</div>';
}
