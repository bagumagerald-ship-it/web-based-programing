<?php
// includes/footer.php
$assetBase = isset($isAdminPage) ? '../assets' : 'assets';
?>
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-logo">UMU</div>
    <p class="footer-text">
      &copy; <?= date('Y') ?> Uganda Martyrs University &mdash; Student Guild Elections<br>
      <small>Faculty of Science &bull; Dept. of Computer Science &amp; Information Technology</small>
    </p>
    <div class="footer-stripes">
      <span class="fs red"></span>
      <span class="fs yellow"></span>
      <span class="fs black"></span>
    </div>
  </div>
</footer>

<script src="<?= $assetBase ?>/main.js"></script>
</body>
</html>
