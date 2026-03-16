<?php
session_start();
include '../includes/db_connection.php';
define('ENC_KEY', 'YourSecretKey1234567890abcdef12');
include '../classes/StaffAuth.php';

$auth = new StaffAuth($conn);

if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$max_attempts         = 5;
$ban_seconds_duration = 20;
$type                 = 'staff';

$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua          = $_SERVER['HTTP_USER_AGENT']      ?? '';
$lang        = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$encoding    = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$device_hash = hash('sha256', $ua . $lang . $encoding);

$stmt = $conn->prepare("SELECT attempts, ban_until FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?");
$stmt->bind_param("sss", $ip, $device_hash, $type);
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

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && !$is_banned) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!is_safe_input($username) || !is_safe_input($password)) {
        $error_message = 'Invalid characters detected in input.';
    } elseif (!empty($username) && !empty($password)) {
        if ($auth->login($username, $password)) {
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ? AND type = ?");
            $stmt->bind_param("sss", $ip, $device_hash, $type);
            $stmt->execute();
            $stmt->close();
            header("Location: dashboard.php");
            exit();
        } else {
            $attempts++;
            $remaining = $max_attempts - $attempts;

            if ($attempts >= $max_attempts) {
                $ban_until     = time() + $ban_seconds_duration;
                $is_banned     = true;
                $ban_secs_left = $ban_seconds_duration;
                $error_message = "Too many failed attempts. Try again in {$ban_seconds_duration} seconds.";

                $stmt = $conn->prepare("
                    INSERT INTO login_attempts (ip_address, device_hash, type, attempts, ban_until)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE attempts = ?, ban_until = ?
                ");
                $stmt->bind_param("sssiiii", $ip, $device_hash, $type, $attempts, $ban_until, $attempts, $ban_until);
                $stmt->execute();
                $stmt->close();
            } else {
                $error_message = $remaining === 1
                    ? 'Invalid credentials. 1 attempt remaining before lockout.'
                    : "Invalid credentials. {$remaining} attempts remaining.";

                $stmt = $conn->prepare("
                    INSERT INTO login_attempts (ip_address, device_hash, type, attempts, ban_until)
                    VALUES (?, ?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE attempts = ?
                ");
                $stmt->bind_param("sssii", $ip, $device_hash, $type, $attempts, $attempts);
                $stmt->execute();
                $stmt->close();
            }
        }
    } else {
        $error_message = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                        <h1 class="h4 fw-bold">Login</h1>
                    </div>

                    <?php if ($is_banned): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-ban me-1"></i>
                        Too many failed attempts.<br>
                        <strong>Try again in <span id="countdown"><?= $ban_secs_left ?></span> second<?= $ban_secs_left !== 1 ? 's' : '' ?>.</strong>
                    </div>
                    <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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

                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text"
                                       class="form-control border-start-0 ps-0"
                                       id="username" name="username"
                                       placeholder="Enter username"
                                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                       maxlength="50" pattern="[a-zA-Z0-9_]+"
                                       title="Only letters, numbers, and underscores allowed"
                                       autocomplete="off" required autofocus
                                       <?= $is_banned ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password"
                                       class="form-control border-start-0 ps-0"
                                       id="password" name="password"
                                       placeholder="Enter password"
                                       maxlength="72" autocomplete="off" required
                                       <?= $is_banned ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="login" class="btn btn-primary btn-lg" <?= $is_banned ? 'disabled' : '' ?>>
                                <i class="fas fa-sign-in-alt me-2"></i><?= $is_banned ? 'Account Locked' : 'Login' ?>
                            </button>
                        </div>
                    </form>

                    <?php if ($is_banned): ?>
                    <p class="text-center text-muted mt-3 small">
                        <i class="fas fa-clock me-1"></i> Unlocks in <span id="countdown2"><?= $ban_secs_left ?></span>s
                    </p>
                    <?php endif; ?>

                    <hr class="my-4">
                </div>
            </div>
        </div>
    </div>
</div>

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