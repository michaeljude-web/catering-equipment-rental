<?php
session_start();
include 'includes/db_connection.php';

define('ENC_KEY', 'YourSecretKey1234567890abcdef12');
define('ENC_METHOD', 'AES-256-CBC');

if (!empty($_SESSION['admin_logged_in']) || !empty($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
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

$step  = (int)($_SESSION['fp_step'] ?? 1);
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'reset') {
        unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_user_type'],
              $_SESSION['fp_question'], $_SESSION['fp_question_id']);
        header('Location: forgot_password.php');
        exit();
    }

    if ($step === 1 && isset($_POST['username'])) {
        $username = trim($_POST['username'] ?? '');

        if (!is_safe_input($username) || empty($username)) {
            $error = 'Invalid input.';
        } else {
            $user_id   = null;
            $user_type = null;
            $question  = null;
            $question_id = null;

            $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($admin) {
                $user_id   = $admin['id'];
                $user_type = 'admin';
            } else {
                $stmt = $conn->prepare("SELECT id FROM staff_info WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $staff = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($staff) {
                    $user_id   = $staff['id'];
                    $user_type = 'staff';
                }
            }

            if (!$user_id) {
                $error = 'Username not found.';
            } else {
                $sq = $conn->prepare("
                    SELECT usa.question_id, sq.question
                    FROM user_security_answers usa
                    JOIN security_questions sq ON usa.question_id = sq.id
                    WHERE usa.user_id = ? AND usa.user_type = ?
                    LIMIT 1
                ");
                $sq->bind_param("is", $user_id, $user_type);
                $sq->execute();
                $sq_row = $sq->get_result()->fetch_assoc();
                $sq->close();

                if (!$sq_row) {
                    $error = 'No security question set for this account. Please contact your administrator.';
                } else {
                    $_SESSION['fp_step']        = 2;
                    $_SESSION['fp_user_id']     = $user_id;
                    $_SESSION['fp_user_type']   = $user_type;
                    $_SESSION['fp_question']    = $sq_row['question'];
                    $_SESSION['fp_question_id'] = $sq_row['question_id'];
                    $_SESSION['fp_username']    = $username;
                    header('Location: forgot_password.php');
                    exit();
                }
            }
        }
    }

    elseif ($step === 2 && isset($_POST['answer'])) {
        $answer      = trim($_POST['answer'] ?? '');
        $user_id     = $_SESSION['fp_user_id'];
        $user_type   = $_SESSION['fp_user_type'];
        $question_id = $_SESSION['fp_question_id'];
        $question    = $_SESSION['fp_question'];

        if (!is_safe_input($answer) || empty($answer)) {
            $error = 'Invalid input.';
        } else {
            $stmt = $conn->prepare("SELECT answer_hash FROM user_security_answers WHERE user_id = ? AND user_type = ? AND question_id = ?");
            $stmt->bind_param("isi", $user_id, $user_type, $question_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $combined = strtolower($question . '|' . $answer);

            if (!$row || !password_verify($combined, $row['answer_hash'])) {
                $error = 'Incorrect answer.';
            } else {
                $_SESSION['fp_step']     = 3;
                $_SESSION['fp_verified'] = true;
                header('Location: forgot_password.php');
                exit();
            }
        }
    }

    elseif ($step === 3 && isset($_POST['new_password']) && !empty($_SESSION['fp_verified'])) {
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user_id   = $_SESSION['fp_user_id'];
        $user_type = $_SESSION['fp_user_type'];

        if (!is_safe_input($new) || !is_safe_input($confirm)) {
            $error = 'Invalid characters detected.';
        } elseif (empty($new)) {
            $error = 'Password cannot be empty.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash  = password_hash($new, PASSWORD_DEFAULT);
            $table = $user_type === 'admin' ? 'admin' : 'staff_info';
            $col   = $user_type === 'admin' ? 'password' : 'password';

            $stmt = $conn->prepare("UPDATE {$table} SET {$col} = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_user_type'],
                  $_SESSION['fp_question'], $_SESSION['fp_question_id'], $_SESSION['fp_verified'],
                  $_SESSION['fp_username']);

            header('Location: login.php?reset=1');
            exit();
        }
    }
}

$step     = (int)($_SESSION['fp_step'] ?? 1);
$question = $_SESSION['fp_question'] ?? '';
$username = $_SESSION['fp_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password</title>
<link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/font/css/all.min.css">
<style>
body { background:#f0f4f8; min-height:100vh; display:flex; align-items:center; justify-content:center; }
.fp-card { width:100%; max-width:420px; background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); overflow:hidden; }
.fp-header { background:linear-gradient(135deg,#0d6efd,#0a58ca); padding:32px 32px 24px; color:#fff; }
.fp-header h2 { font-size:20px; font-weight:700; margin:0 0 4px; }
.fp-header p  { font-size:13px; opacity:.85; margin:0; }
.fp-body { padding:28px 32px; }
.step-dots { display:flex; gap:8px; margin-bottom:24px; }
.step-dot { width:8px; height:8px; border-radius:50%; background:#dee2e6; transition:background .3s; }
.step-dot.active  { background:#0d6efd; }
.step-dot.done    { background:#198754; }
.form-label { font-size:12px; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.form-control { border-radius:8px; border:1.5px solid #e2e8f0; font-size:14px; padding:10px 13px; }
.form-control:focus { border-color:#0d6efd; box-shadow:0 0 0 3px rgba(13,110,253,.08); }
.btn-main { width:100%; padding:11px; border-radius:8px; font-size:15px; font-weight:600; }
.question-box { background:#f8f9fa; border-left:3px solid #0d6efd; border-radius:0 8px 8px 0; padding:12px 14px; font-size:14px; color:#1a1a2e; margin-bottom:16px; }
.back-link { font-size:13px; color:#6c757d; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
.back-link:hover { color:#0d6efd; }
.alert { border-radius:8px; font-size:13px; }
</style>
</head>
<body>

<div class="fp-card">
    <div class="fp-header">
        <div style="font-size:28px;margin-bottom:10px;"><i class="fas fa-key"></i></div>
        <h2>Forgot Password</h2>
        <p>
            <?php if ($step === 1): ?>Verify your identity to reset your password.
            <?php elseif ($step === 2): ?>Answer your security question.
            <?php else: ?>Set your new password.
            <?php endif; ?>
        </p>
    </div>

    <div class="fp-body">
        <div class="step-dots">
            <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
            <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
            <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="POST">
            <div class="mb-4">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your username"
                    maxlength="50" autocomplete="off" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-main">
                <i class="fas fa-arrow-right me-2"></i>Continue
            </button>
        </form>

        <?php elseif ($step === 2): ?>
        <div class="question-box">
            <div style="font-size:11px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Security Question</div>
            <?= htmlspecialchars($question) ?>
        </div>
        <form method="POST">
            <div class="mb-4">
                <label class="form-label">Your Answer</label>
                <input type="text" name="answer" class="form-control" placeholder="Type your answer"
                    maxlength="100" autocomplete="off" required autofocus>
                <div class="form-text small text-muted mt-1">Answer is not case-sensitive.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-main mb-3">
                <i class="fas fa-check me-2"></i>Verify Answer
            </button>
        </form>
        <form method="POST">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="back-link border-0 bg-transparent p-0">
                <i class="fas fa-arrow-left"></i> Back to username
            </button>
        </form>

        <?php elseif ($step === 3): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" name="new_password" class="form-control border-end-0" required>
                    <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('new_password',this)">
                        <i class="fas fa-eye fa-sm"></i>
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" class="form-control border-end-0" required>
                    <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('confirm_password',this)">
                        <i class="fas fa-eye fa-sm"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-success btn-main">
                <i class="fas fa-save me-2"></i>Reset Password
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</div>

<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/font/js/all.min.js"></script>
<script>
function togglePw(name, btn) {
    const input = document.querySelector('[name="' + name + '"]');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%', '#', '/'];
function stripBlocked(val) {
    let out = val;
    blocked.forEach(c => { out = out.split(c).join(''); });
    return out;
}
document.querySelectorAll('input[type=text], input[type=password]').forEach(input => {
    input.addEventListener('input', function() {
        const orig = this.value;
        const clean = stripBlocked(orig);
        if (clean !== orig) this.value = clean;
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        let text = (e.clipboardData || window.clipboardData).getData('text');
        this.value += stripBlocked(text);
    });
});
</script>
</body>
</html>