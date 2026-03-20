<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include '../includes/db_connection.php';
include '../classes/StaffAuth.php';

define('ENC_KEY', 'YourSecretKey1234567890abcdef12');
define('ENC_METHOD', 'AES-256-CBC');

$auth = new StaffAuth($conn);
$auth->requireLogin();

$staff_id = $_SESSION['staff_id'];

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

$questions = $conn->query("SELECT id, question FROM security_questions WHERE is_active = 1 ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$sq = $conn->prepare("SELECT id, question_id FROM user_security_answers WHERE user_id = ? AND user_type = 'staff'");
$sq->bind_param("i", $staff_id);
$sq->execute();
$existing_sq = $sq->get_result()->fetch_assoc();
$sq->close();

$used_question_ids = [];
if ($existing_sq) {
    $all_sq = $conn->prepare("SELECT question_id FROM user_security_answers WHERE user_id = ? AND user_type = 'staff'");
    $all_sq->bind_param("i", $staff_id);
    $all_sq->execute();
    $all_sq_result = $all_sq->get_result();
    while ($r = $all_sq_result->fetch_assoc()) {
        $used_question_ids[] = (int)$r['question_id'];
    }
    $all_sq->close();
}

$pw_success = $pw_error = $sq_success = $sq_error = $reset_error = $reset_success = '';
$open_section = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'change_password') {
        $open_section = 'password';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password_hash FROM staff_info WHERE id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!is_safe_input($current) || !is_safe_input($new) || !is_safe_input($confirm)) {
            $pw_error = 'Invalid characters detected in input.';
        } elseif (!password_verify($current, $staff['password_hash'])) {
            $pw_error = 'Current password is incorrect.';
        } elseif (empty($new)) {
            $pw_error = 'New password cannot be empty.';
        } elseif ($new !== $confirm) {
            $pw_error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE staff_info SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $staff_id);
            $stmt->execute();
            $stmt->close();
            $pw_success = 'Password updated successfully.';
        }
    }

    if ($_POST['action'] === 'save_security_question') {
        $open_section = 'security';
        $question_id  = (int)($_POST['question_id'] ?? 0);
        $answer       = trim($_POST['answer'] ?? '');

        if (!$question_id) {
            $sq_error = 'Please select a security question.';
        } elseif (in_array($question_id, $used_question_ids)) {
            $sq_error = 'This question has already been used. Please choose a different one.';
        } elseif (!is_safe_input($answer)) {
            $sq_error = 'Invalid characters detected in input.';
        } elseif (strlen($answer) < 2) {
            $sq_error = 'Answer must be at least 2 characters.';
        } else {
            $question_text = '';
            foreach ($questions as $q) {
                if ((int)$q['id'] === $question_id) {
                    $question_text = $q['question'];
                    break;
                }
            }

            $combined    = strtolower($question_text . '|' . $answer);
            $answer_hash = password_hash($combined, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO user_security_answers (user_id, user_type, question_id, answer_hash)
                VALUES (?, 'staff', ?, ?)
                ON DUPLICATE KEY UPDATE question_id = ?, answer_hash = ?
            ");
            $stmt->bind_param("iissi", $staff_id, $question_id, $answer_hash, $question_id, $answer_hash);
            $stmt->execute();
            $stmt->close();
            $sq_success = 'Security question saved successfully.';

            $sq2 = $conn->prepare("SELECT id, question_id FROM user_security_answers WHERE user_id = ? AND user_type = 'staff'");
            $sq2->bind_param("i", $staff_id);
            $sq2->execute();
            $existing_sq = $sq2->get_result()->fetch_assoc();
            $sq2->close();

            $used_question_ids = [];
            if ($existing_sq) {
                $used_question_ids[] = (int)$existing_sq['question_id'];
            }
        }
    }

    if ($_POST['action'] === 'reset_security') {
        $open_section = 'security';
        $confirm_pw   = $_POST['confirm_reset_password'] ?? '';

        $stmt = $conn->prepare("SELECT password_hash FROM staff_info WHERE id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($confirm_pw, $staff['password_hash'])) {
            $reset_error = 'Incorrect password. Security questions were not reset.';
        } else {
            $stmt = $conn->prepare("DELETE FROM user_security_answers WHERE user_id = ? AND user_type = 'staff'");
            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $stmt->close();
            $existing_sq       = null;
            $used_question_ids = [];
            $reset_success     = 'All security questions have been reset.';
        }
    }
}

$staff_firstname = $_SESSION['staff_firstname'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
.settings-item { border:1px solid #e9ecef; border-radius:12px; overflow:hidden; margin-bottom:12px; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.04); }
.settings-trigger { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; cursor:pointer; user-select:none; transition:background .15s; }
.settings-trigger:hover { background:#f8f9fa; }
.settings-trigger-left { display:flex; align-items:center; gap:14px; }
.settings-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.settings-title { font-weight:600; font-size:15px; color:#1a1a2e; margin:0; }
.settings-subtitle { font-size:12px; color:#6c757d; margin:0; }
.settings-chevron { color:#adb5bd; transition:transform .25s; font-size:13px; }
.settings-chevron.open { transform:rotate(180deg); }
.settings-body { padding:0 22px; max-height:0; overflow:hidden; transition:max-height .3s ease, padding .3s ease; }
.settings-body.open { max-height:700px; padding:4px 22px 22px; }
.divider { height:1px; background:#f0f0f0; margin:0 22px; }
.form-label { font-size:12px; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.form-control, .form-select { border-radius:8px; border:1.5px solid #e2e8f0; font-size:14px; padding:9px 12px; }
.form-control:focus, .form-select:focus { border-color:#0d6efd; box-shadow:0 0 0 3px rgba(13,110,253,.08); }
.btn-save { padding:9px 20px; border-radius:8px; font-size:14px; font-weight:500; }
.badge-status { font-size:11px; padding:4px 10px; border-radius:20px; font-weight:500; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="flex-grow-1 p-4">
    <div class="mb-4">
        <h1 class="h4 mb-1">Settings</h1>
        <p class="text-muted small mb-0">Manage your account.</p>
    </div>

    <div style="max-width:620px;">

        <div class="settings-item">
            <div class="settings-trigger" onclick="toggleSection('password')">
                <div class="settings-trigger-left">
                    <div class="settings-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <p class="settings-title">Change Password</p>
                        <p class="settings-subtitle">Update your account password</p>
                    </div>
                </div>
                <i class="fas fa-chevron-down settings-chevron" id="chevron-password"></i>
            </div>
            <div class="divider"></div>
            <div class="settings-body" id="body-password">
                <div class="pt-4">
                    <?php if ($pw_success): ?>
                    <div class="alert alert-success py-2 small border-0 rounded-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($pw_success) ?></div>
                    <?php elseif ($pw_error): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($pw_error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" class="form-control border-end-0" required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('current_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" class="form-control border-end-0" required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('new_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="form-control border-end-0" required>
                                <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('confirm_password',this)"><i class="fas fa-eye fa-sm"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-save"><i class="fas fa-save me-1"></i> Update Password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="settings-item">
            <div class="settings-trigger" onclick="toggleSection('security')">
                <div class="settings-trigger-left">
                    <div class="settings-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <p class="settings-title">Security Question</p>
                        <p class="settings-subtitle">Used for password recovery</p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($existing_sq): ?>
                    <span class="badge-status bg-success bg-opacity-10 text-success"><i class="fas fa-check me-1"></i>Set</span>
                    <?php else: ?>
                    <span class="badge-status bg-warning bg-opacity-10 text-warning"><i class="fas fa-exclamation me-1"></i>Not set</span>
                    <?php endif; ?>
                    <i class="fas fa-chevron-down settings-chevron" id="chevron-security"></i>
                </div>
            </div>
            <div class="divider"></div>
            <div class="settings-body" id="body-security">
                <div class="pt-4">
                    <?php if ($sq_success): ?>
                    <div class="alert alert-success py-2 small border-0 rounded-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($sq_success) ?></div>
                    <?php elseif ($sq_error): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($sq_error) ?></div>
                    <?php elseif ($reset_success): ?>
                    <div class="alert alert-success py-2 small border-0 rounded-3"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($reset_success) ?></div>
                    <?php elseif ($reset_error): ?>
                    <div class="alert alert-danger py-2 small border-0 rounded-3"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($reset_error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_security_question">
                        <div class="mb-3">
                            <label class="form-label">Select Question</label>
                            <select name="question_id" class="form-select" required>
                                <option value="">— Choose a question —</option>
                                <?php foreach ($questions as $q):
                                    $is_used = in_array((int)$q['id'], $used_question_ids);
                                ?>
                                <option value="<?= $q['id'] ?>" <?= $is_used ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($q['question']) ?><?= $is_used ? ' (already used)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Your Answer</label>
                            <input type="text" name="answer" class="form-control" required maxlength="100"
                                placeholder="<?= $existing_sq ? 'Enter new answer to update' : 'Type your answer' ?>">
                           
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-success btn-save">
                                <i class="fas fa-save me-1"></i> <?= $existing_sq ? 'Update' : 'Save' ?> Question
                            </button>
                            <?php if ($existing_sq): ?>
                            <button type="button" class="btn btn-danger btn-save" data-bs-toggle="modal" data-bs-target="#resetModal">
                                <i class="fas fa-trash me-1"></i> Reset All
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</main>

<?php if ($existing_sq): ?>
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-danger"><i class="fas fa-triangle-exclamation me-2"></i>Reset Security Questions</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_security">
                <div class="modal-body">
                    <p class="text-muted small mb-3">This will delete all your security questions. Enter your password to confirm.</p>
                    <label class="form-label" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;">Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_reset_password" class="form-control border-end-0" style="border-radius:8px 0 0 8px;border:1.5px solid #e2e8f0;" required>
                        <button type="button" class="btn btn-outline-secondary border-start-0" style="border-radius:0 8px 8px 0;border:1.5px solid #e2e8f0;" onclick="togglePw('confirm_reset_password',this)">
                            <i class="fas fa-eye fa-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash me-1"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
function toggleSection(id) {
    const body    = document.getElementById('body-' + id);
    const chevron = document.getElementById('chevron-' + id);
    const isOpen  = body.classList.contains('open');
    document.querySelectorAll('.settings-body').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('.settings-chevron').forEach(c => c.classList.remove('open'));
    if (!isOpen) {
        body.classList.add('open');
        chevron.classList.add('open');
    }
}

function togglePw(fieldName, btn) {
    const input = document.querySelector('[name="' + fieldName + '"]');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

<?php if ($open_section): ?>
document.addEventListener('DOMContentLoaded', () => { toggleSection('<?= $open_section ?>'); });
<?php endif; ?>

const blocked = ["'", '"', ';', '--', '<', '>', '\\', '=', '`', '|', '&', '%', '#', '/'];
function stripBlocked(val) {
    let out = val;
    blocked.forEach(c => { out = out.split(c).join(''); });
    return out;
}
document.querySelectorAll('input[type=text], input[type=password]').forEach(input => {
    input.addEventListener('input', function() {
        const orig = this.value;
        const cleaned = stripBlocked(orig);
        if (cleaned !== orig) this.value = cleaned;
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