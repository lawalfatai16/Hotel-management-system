<?php
/**
 * TEMPORARY HELPER: generates a password hash you can paste into the database.
 * Delete this file once you're done. Do not leave it on a live/production server.
 */
$hash = null;
$password = $_POST['password'] ?? '';
if ($password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Password Hash Generator</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 560px; margin: 60px auto; padding: 0 20px; color: #241812; }
  h1 { font-size: 20px; }
  input[type=text] { width: 100%; padding: 10px; font-size: 14px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
  button { margin-top: 12px; padding: 10px 20px; background: #7A4A2B; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
  .result { margin-top: 24px; padding: 16px; background: #F5EFE4; border-radius: 8px; word-break: break-all; font-family: monospace; font-size: 13px; }
  .warn { margin-top: 24px; padding: 12px 16px; background: #FBEAEA; color: #8A2E2E; border-radius: 8px; font-size: 13px; }
</style>
</head>
<body>
  <h1>Generate a Password Hash</h1>
  <p>Type the password you want to log in with, then copy the hash it produces.</p>
  <form method="POST">
    <input type="text" name="password" placeholder="e.g. ChangeMe!2026" value="<?= htmlspecialchars($password) ?>" autofocus>
    <button type="submit">Generate Hash</button>
  </form>
  <?php if ($hash): ?>
    <div class="result"><?= $hash ?></div>
    <p>Copy the text above, then run this in phpMyAdmin's SQL tab (or wherever you manage the database):</p>
    <div class="result">UPDATE users SET password_hash = '<?= $hash ?>' WHERE username = 'admin';</div>
  <?php endif; ?>
  <div class="warn">Warning: delete this file (generate_hash.php) from your server once you're done. It shouldn't stay on a live system.</div>
</body>
</html>
