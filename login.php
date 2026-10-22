<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

if (($_SESSION['user']['role'] ?? null) === 'admin') {
    header('Location: admin.php');
    exit;
}

$nextInput = $_POST['next'] ?? $_GET['next'] ?? 'shop';
$next = is_string($nextInput) && in_array($nextInput, ['checkout', 'shop', 'cart', 'account'], true)
    ? $nextInput
    : 'shop';
$error = '';
$databaseUnavailable = false;
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your sign-in request expired. Please try again.';
    } else {
        $usernameInput = $_POST['username'] ?? '';
        $username = is_string($usernameInput) ? trim($usernameInput) : '';
        $usernameValue = $username;
        $password = $_POST['password'] ?? '';

        if ($username === '' || !is_string($password) || $password === '') {
            $error = 'Enter your username and password.';
        } else {
            try {
                $pdo = require __DIR__ . '/db.php';
                $statement = $pdo->prepare(
                    "SELECT id, username, full_name, password_hash, role
                     FROM users
                     WHERE username = :username
                     LIMIT 1"
                );
                $statement->execute(['username' => $username]);
                $user = $statement->fetch();

                if (
                    $user !== false
                    && is_string($user['password_hash'])
                    && password_verify($password, $user['password_hash'])
                ) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id' => (int) $user['id'],
                        'username' => (string) $user['username'],
                        'full_name' => (string) $user['full_name'],
                        'role' => (string) $user['role'],
                    ];
                    unset($_SESSION['csrf_token']);

                    if ($user['role'] === 'admin') {
                        header('Location: admin.php');
                    } else {
                        header('Location: ' . $next . '.php');
                    }
                    exit;
                }

                $error = 'Invalid username or password.';
            } catch (PDOException $exception) {
                error_log('Salevision login database error: ' . $exception->getMessage());
                $databaseUnavailable = true;
                $error = 'Sign-in is temporarily unavailable. Check the database connection and try again.';
            }
        }
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <a href="index.php" class="brand">
      <img src="assets/gglogo.png" alt="Golden Glow">
      <span class="brand-word">Golden <b>Glow</b></span>
    </a>
    <nav class="nav-links">
      <a href="index.php">Home</a>
      <a href="shop.php">Shop</a>
      <a href="index.php#story">Our Story</a>
      <a href="index.php#story">Contact</a>
    </nav>
    <div class="top-actions">
      <a href="cart.php" class="icon-link" title="Cart">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
      </a>
    </div>
  </div>
</div>

<div class="auth-shell">
  <div class="auth-side">
    <a href="index.php" class="brand"><img src="assets/gglogo.png" alt="Golden Glow"><span class="brand-word">Golden <b>Glow</b></span></a>
    <blockquote>"Skin that remembers its own light."<cite>Golden Glow Skincare</cite></blockquote>
    <div></div>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-form">
      <h1>Welcome back</h1>
      <p class="sub">Sign in to continue to your account.</p>
      <?php if ($error !== ''): ?>
        <p class="auth-error" role="alert" <?= $databaseUnavailable ? 'data-database-error="true"' : '' ?>>
          <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
      <?php endif; ?>
      <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" autocomplete="username" required autofocus value="<?= htmlspecialchars($usernameValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-gold btn-block">Sign in</button>
      </form>
      <p class="role-note">New to Golden Glow? <a href="register.php">Create an account</a>.</p>
    </div>
  </div>
</div>
<?php renderStoreFooter(); ?>
</body>
</html>
