<?php
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$max_attempts = 5;
$ban_seconds_duration = 20;

if (empty($_COOKIE['device_id'])) {
    $device_id = bin2hex(random_bytes(16));
    setcookie('device_id', $device_id, time() + (60 * 60 * 24 * 30), '/', '', false, true);
} else {
    $device_id = preg_replace('/[^a-f0-9]/', '', $_COOKIE['device_id']);
}

$attempt_key = 'attempts_' . $device_id;
$ban_key     = 'ban_until_' . $device_id;

$attempts  = isset($_COOKIE[$attempt_key]) ? (int)$_COOKIE[$attempt_key] : 0;
$ban_until = isset($_COOKIE[$ban_key])     ? (int)$_COOKIE[$ban_key]     : 0;

$is_banned     = $ban_until > time();
$ban_secs_left = max(0, $ban_until - time());

function is_safe_input($val) {
    $blocked = ["'", '"', ';', '--', '#', '/*', '*/', 'SELECT', 'INSERT', 'UPDATE',
                'DELETE', 'DROP', 'UNION', 'OR ', 'AND ', '<script', '</script',
                '<', '>', '\\', '/', '=', '%', '&', '|', '`', 'EXEC', 'CAST',
                'CHAR(', 'alert(', 'onerror', 'onload'];
    $upper = strtoupper($val);
    foreach ($blocked as $b) {
        if (str_contains($upper, strtoupper($b))) return false;
    }
    return true;
}

$message      = '';
$message_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_banned) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!is_safe_input($username) || !is_safe_input($password)) {
        $message = 'Invalid characters detected in input.';
    } elseif (empty($username) || empty($password)) {
        $message = 'Please fill in all fields.';
    } else {
        $auth = new AdminAuth($conn);

        if ($auth->login($username, $password)) {
            // Reset attempts on success
            setcookie($attempt_key, 0, time() - 3600, '/', '', false, true);
            setcookie($ban_key,     0, time() - 3600, '/', '', false, true);
            header('Location: dashboard.php');
            exit();
        } else {
            $attempts++;
            $remaining = $max_attempts - $attempts;

            if ($attempts >= $max_attempts) {
                $ban_until = time() + $ban_seconds_duration;
                setcookie($ban_key,     $ban_until, $ban_until + 3600, '/', '', false, true);
                setcookie($attempt_key, $attempts,  time() + 3600,     '/', '', false, true);
                $is_banned     = true;
                $ban_secs_left = $ban_seconds_duration;
                $message       = "Too many failed attempts. Try again in {$ban_seconds_duration} seconds.";
            } else {
                setcookie($attempt_key, $attempts, time() + 3600, '/', '', false, true);
                $message = $remaining === 1
                    ? 'Invalid credentials. 1 attempt remaining before lockout.'
                    : "Invalid credentials. {$remaining} attempts remaining.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">

<form method="post" action="" class="bg-white p-4 rounded shadow" style="width:320px;">
    <h2 class="h4 text-center mb-3">
        <i class="fas fa-user-shield me-2"></i> Admin Login
    </h2>

    <?php if ($is_banned): ?>
        <div class="alert alert-danger text-center py-2">
            <i class="fas fa-ban me-1"></i>
            Too many failed attempts.<br>
            <strong>Try again in <?= $ban_secs_left ?> second<?= $ban_secs_left !== 1 ? 's' : '' ?>.</strong>
        </div>
    <?php elseif ($message): ?>
        <div class="alert alert-danger text-center py-2">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($attempts > 0 && !$is_banned): ?>
        <div class="d-flex justify-content-end mb-2">
            <small class="text-muted">
                <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                <?= $max_attempts - $attempts ?> attempt<?= ($max_attempts - $attempts) !== 1 ? 's' : '' ?> left
            </small>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <input
            type="text"
            name="username"
            placeholder="Username"
            class="form-control"
            required
            autofocus
            <?= $is_banned ? 'disabled' : '' ?>
            maxlength="50"
            pattern="[a-zA-Z0-9_]+"
            title="Only letters, numbers, and underscores allowed"
            autocomplete="off"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <input
            type="password"
            name="password"
            placeholder="Password"
            class="form-control"
            required
            <?= $is_banned ? 'disabled' : '' ?>
            maxlength="72"
            autocomplete="off">
    </div>

    <button type="submit" class="btn btn-primary w-100" <?= $is_banned ? 'disabled' : '' ?>>
        <i class="fas fa-sign-in-alt me-1"></i>
        <?= $is_banned ? 'Account Locked' : 'Login' ?>
    </button>

    <?php if ($is_banned): ?>
        <p class="text-center text-muted mt-3 small">
            <i class="fas fa-clock me-1"></i> Unlocks in <?= $ban_secs_left ?> second<?= $ban_secs_left !== 1 ? 's' : '' ?>
        </p>
    <?php endif; ?>
</form>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>

<script>
const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%'];
document.querySelectorAll('input[type=text], input[type=password]').forEach(input => {
    input.addEventListener('input', function () {
        blocked.forEach(c => {
            if (this.value.includes(c)) {
                this.value = this.value.split(c).join('');
            }
        });
    });
    input.addEventListener('paste', function (e) {
        e.preventDefault();
        let text = (e.clipboardData || window.clipboardData).getData('text');
        blocked.forEach(c => { text = text.split(c).join(''); });
        this.value += text;
    });
});
</script>
</body>
</html>