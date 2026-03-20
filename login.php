<?php
session_start();
include 'includes/db_connection.php';
define('ENC_KEY', 'YourSecretKey1234567890abcdef12');
include 'classes/StaffAuth.php';
include 'classes/AdminAuth.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin/dashboard.php');
    exit();
}
if (!empty($_SESSION['staff_logged_in'])) {
    header('Location: staff/dashboard.php');
    exit();
}

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

if ($row && !$is_banned && $ban_until > 0) {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ?");
    $stmt->bind_param("ss", $ip, $device_hash);
    $stmt->execute();
    $stmt->close();
    $attempts = 0;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_banned) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']     ?? '';

    if (!is_safe_input($username) || !is_safe_input($password)) {
        $error_message = 'Invalid characters detected in input.';
    } elseif (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        $admin_auth = new AdminAuth($conn);
        if ($admin_auth->login($username, $password)) {
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ?");
            $stmt->bind_param("ss", $ip, $device_hash);
            $stmt->execute();
            $stmt->close();
            header('Location: admin/dashboard.php');
            exit();
        }

        $staff_auth = new StaffAuth($conn);
        if ($staff_auth->login($username, $password)) {
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND device_hash = ?");
            $stmt->bind_param("ss", $ip, $device_hash);
            $stmt->execute();
            $stmt->close();
            header('Location: staff/dashboard.php');
            exit();
        }

        $attempts++;

        if ($attempts >= $max_attempts) {
            $ban_until     = time() + $ban_seconds_duration;
            $is_banned     = true;
            $ban_secs_left = $ban_seconds_duration;

            $stmt = $conn->prepare("
                INSERT INTO login_attempts (ip_address, device_hash, attempts, ban_until)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE attempts = ?, ban_until = ?
            ");
            $stmt->bind_param("ssiiii", $ip, $device_hash, $attempts, $ban_until, $attempts, $ban_until);
            $stmt->execute();
            $stmt->close();

            header('Location: login.php?banned=1');
            exit();
        } else {
            $error_message = 'Invalid username or password.';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/font/css/all.min.css">
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                        <h1 class="h4 fw-bold">Sign In</h1>
                    </div>

                    <?php if ($is_banned): ?>
                    <div class="alert alert-danger text-center" id="bannedAlert">
                        <i class="fas fa-ban me-1"></i>
                        Too many failed attempts.<br>
                        <strong>Try again in <span id="countdown"><?= $ban_secs_left ?></span> second<?= $ban_secs_left !== 1 ? 's' : '' ?>.</strong>
                    </div>
                    <?php elseif (isset($_GET['reset'])): ?>
                    <div class="alert alert-success py-2 small">
                        <i class="fas fa-check-circle me-1"></i> Password reset successfully. You can now log in.
                    </div>
                    <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                    name="username" placeholder="Enter username"
                                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                    maxlength="50" pattern="[a-zA-Z0-9_]+"
                                    title="Only letters, numbers, and underscores allowed"
                                    autocomplete="off" required autofocus
                                    <?= $is_banned ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 ps-0"
                                    name="password" placeholder="Enter password"
                                    maxlength="72" autocomplete="off" required
                                    <?= $is_banned ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" <?= $is_banned ? 'disabled' : '' ?>>
                                <i class="fas fa-sign-in-alt me-2"></i><?= $is_banned ? 'Account Locked' : 'Login' ?>
                            </button>
                        </div>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php" class="small text-decoration-none text-primary">
                                Forgot password?
                            </a>
                        </div>
                    </form>

                    <?php if ($is_banned): ?>
                    <p class="text-center text-muted mt-3 small">
                        <i class="fas fa-clock me-1"></i> Unlocks in <span id="countdown2"><?= $ban_secs_left ?></span>s
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/font/js/all.min.js"></script>
<script>
<?php if ($is_banned): ?>
(function() {
    let secs = <?= $ban_secs_left ?>;
    const c1 = document.getElementById('countdown');
    const c2 = document.getElementById('countdown2');
    const timer = setInterval(() => {
        secs--;
        if (c1) c1.textContent = secs;
        if (c2) c2.textContent = secs;
        if (secs <= 0) { clearInterval(timer); location.href = 'login.php'; }
    }, 1000);
})();
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