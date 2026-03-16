<?php
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$max_attempts         = 5;
$ban_seconds_duration = 20;

$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua          = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang        = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$encoding    = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$device_hash = hash('sha256', $ua . $lang . $encoding);

$stmt = $conn->prepare("SELECT attempts, ban_until FROM login_attempts WHERE ip_address = ? AND device_hash = ?");
$stmt->bind_param("ss", $ip, $device_hash);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attempts  = $row ? (int)$row['attempts']  : 0;
$ban_until = $row ? (int)$row['ban_until'] : 0;

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

$message = '';

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
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ?");
            $stmt->bind_param("ss", $ip, $device_hash);
            $stmt->execute();
            $stmt->close();
            header('Location: dashboard.php');
            exit();
        } else {
            $attempts++;
            $remaining = $max_attempts - $attempts;

            if ($attempts >= $max_attempts) {
                $ban_until     = time() + $ban_seconds_duration;
                $is_banned     = true;
                $ban_secs_left = $ban_seconds_duration;
                $message       = "Too many failed attempts. Try again in {$ban_seconds_duration} seconds.";

                $stmt = $conn->prepare("
                    INSERT INTO login_attempts (ip_address, device_hash, attempts, ban_until)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE attempts = ?, ban_until = ?
                ");
                $stmt->bind_param("ssiiii", $ip, $device_hash, $attempts, $ban_until, $attempts, $ban_until);
                $stmt->execute();
                $stmt->close();
            } else {
                $message = $remaining === 1
                    ? 'Invalid credentials. 1 attempt remaining before lockout.'
                    : "Invalid credentials. {$remaining} attempts remaining.";

                $stmt = $conn->prepare("
                    INSERT INTO login_attempts (ip_address, device_hash, attempts, ban_until)
                    VALUES (?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE attempts = ?
                ");
                $stmt->bind_param("ssii", $ip, $device_hash, $attempts, $attempts);
                $stmt->execute();
                $stmt->close();
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
            <strong>Try again in <span id="countdown"><?= $ban_secs_left ?></span> second<?= $ban_secs_left !== 1 ? 's' : '' ?>.</strong>
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
        <input type="text" name="username" placeholder="Username" class="form-control"
            required autofocus <?= $is_banned ? 'disabled' : '' ?>
            maxlength="50" pattern="[a-zA-Z0-9_]+"
            title="Only letters, numbers, and underscores allowed"
            autocomplete="off"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <input type="password" name="password" placeholder="Password" class="form-control"
            required <?= $is_banned ? 'disabled' : '' ?>
            maxlength="72" autocomplete="off">
    </div>

    <button type="submit" class="btn btn-primary w-100" <?= $is_banned ? 'disabled' : '' ?>>
        <i class="fas fa-sign-in-alt me-1"></i>
        <?= $is_banned ? 'Account Locked' : 'Login' ?>
    </button>

    <?php if ($is_banned): ?>
        <p class="text-center text-muted mt-3 small">
            <i class="fas fa-clock me-1"></i> Unlocks in <span id="countdown2"><?= $ban_secs_left ?></span>s
        </p>
    <?php endif; ?>
</form>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
<?php if ($is_banned): ?>
let secs = <?= $ban_secs_left ?>;
const c1 = document.getElementById('countdown');
const c2 = document.getElementById('countdown2');
const timer = setInterval(() => {
    secs--;
    if (c1) c1.textContent = secs;
    if (c2) c2.textContent = secs;
    if (secs <= 0) { clearInterval(timer); location.reload(); }
}, 1000);
<?php endif; ?>

const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%'];
document.querySelectorAll('input[type=text], input[type=password]').forEach(input => {
    input.addEventListener('input', function () {
        blocked.forEach(c => { this.value = this.value.split(c).join(''); });
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