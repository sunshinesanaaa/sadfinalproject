<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

if (($_SESSION['user']['role'] ?? null) === 'admin') {
    header('Location: admin.php');
    exit;
}
if (($_SESSION['user']['role'] ?? null) === 'customer') {
    header('Location: account.php');
    exit;
}

$errors = [];
$values = ['username' => '', 'full_name' => '', 'email' => ''];
$password = '';
$passwordConfirmation = '';
$characterCount = static function (string $value): int {
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your registration form expired. Please try again.';
    } else {
        foreach (array_keys($values) as $field) {
            $input = $_POST[$field] ?? '';
            $values[$field] = is_string($input) ? trim($input) : '';
        }
        $passwordInput = $_POST['password'] ?? '';
        $confirmationInput = $_POST['password_confirmation'] ?? '';
        $password = is_string($passwordInput) ? $passwordInput : '';
        $passwordConfirmation = is_string($confirmationInput) ? $confirmationInput : '';

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $values['username'])) {
            $errors[] = 'Username must be 3–30 characters and use only letters, numbers, or underscores.';
        }
        if ($values['full_name'] === '' || $characterCount($values['full_name']) > 120) {
            $errors[] = 'Enter your name (up to 120 characters).';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($characterCount($password) < 16) {
            $errors[] = 'Password must be at least 16 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must include at least one uppercase letter.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'The passwords do not match.';
        }

        if ($errors === []) {
            try {
                $statement = storeDatabase()->prepare(
                    "INSERT INTO users (username, full_name, email, password_hash, role)
                     VALUES (:username, :full_name, :email, :password_hash, 'customer')"
                );
                $statement->execute([
                    'username' => $values['username'],
                    'full_name' => $values['full_name'],
                    'email' => $values['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) storeDatabase()->lastInsertId(),
                    'username' => $values['username'],
                    'full_name' => $values['full_name'],
                    'role' => 'customer',
                ];
                unset($_SESSION['csrf_token']);
                header('Location: checkout.php');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'That username or email is already registered.';
                } else {
                    error_log('Salevision registration database error: ' . $exception->getMessage());
                    $errors[] = 'We could not create your account right now. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account — Golden Glow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"><link rel="stylesheet" href="auth.css">
</head>
<body>
<?php renderStoreHeader(); ?>
<main class="auth-shell">
  <div class="auth-side"><a href="index.php" class="brand"><img src="assets/gglogo.png" alt="Golden Glow"><span class="brand-word">Golden <b>Glow</b></span></a><blockquote>"Skin that remembers its own light."<cite>Golden Glow Skincare</cite></blockquote><div></div></div>
  <div class="auth-form-wrap"><div class="auth-form">
    <h1>Create your account</h1><p class="sub">Create an account to continue with your order.</p>
    <?php if ($errors !== []): ?><div class="auth-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= storeEscape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="register.php">
      <input type="hidden" name="csrf_token" value="<?= storeEscape(csrfToken()) ?>">
      <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="120" autocomplete="name" required value="<?= storeEscape($values['full_name']) ?>"></div>
      <div class="field"><label for="username">Username</label><input id="username" name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" autocomplete="username" required value="<?= storeEscape($values['username']) ?>"></div>
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= storeEscape($values['email']) ?>"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="16" autocomplete="new-password" required><small class="password-guidance">Use at least 16 characters and include at least one uppercase letter.</small></div>
      <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="16" autocomplete="new-password" required></div>
      <button type="submit" class="btn btn-gold btn-block">Create account</button>
    </form>
    <p class="role-note">Already have an account? <a href="login.php?next=checkout">Sign in</a>.</p>
  </div></div>
</main>
<?php renderStoreFooter(); ?>
</body>
</html>
