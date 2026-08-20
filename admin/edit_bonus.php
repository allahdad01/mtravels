<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once 'security.php';
enforce_auth();
require_permission('hr.salary');

// Include config file
require_once "../includes/db.php";

// Define variables and initialize with empty values
$bonus_user_id = $amount = $description = $bonus_date = $type = "";
$user_id_err = $amount_err = $description_err = $bonus_date_err = "";
$success_message = $error_message = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get hidden input value
    $id = $_POST["id"];
    
    // Validate user ID
    if (empty($_POST["user_id"])) {
        $user_id_err = "Please select an employee.";
    } else {
        $bonus_user_id = $_POST["user_id"];
    }
    
    // Validate amount
    if (empty($_POST["amount"])) {
        $amount_err = "Please enter the bonus amount.";
    } else {
        $amount = $_POST["amount"];
        // Check if amount is a positive number
        if (!is_numeric($amount) || $amount <= 0) {
            $amount_err = "Please enter a positive number for the bonus amount.";
        }
    }
    
    // Validate description
    if (empty($_POST["description"])) {
        $description_err = "Please enter a description.";
    } else {
        $description = $_POST["description"];
    }
    
    // Validate bonus date
    if (empty($_POST["bonus_date"])) {
        $bonus_date_err = "Please enter the bonus date.";
    } else {
        $bonus_date = $_POST["bonus_date"];
    }
    
    // Set bonus type
    $type = $_POST["type"] ?? "performance";
    
    // Check input errors before updating in database
    if (empty($user_id_err) && empty($amount_err) && empty($description_err) && empty($bonus_date_err)) {
        // Prepare an update statement
        $sql = "UPDATE salary_bonuses SET user_id=?, amount=?, description=?, bonus_date=?, type=? WHERE id=? AND tenant_id = ? AND branch_id = ?";

        try {
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $stmt->bindParam(1, $bonus_user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $amount, PDO::PARAM_STR);
            $stmt->bindParam(3, $description, PDO::PARAM_STR);
            $stmt->bindParam(4, $bonus_date, PDO::PARAM_STR);
            $stmt->bindParam(5, $type, PDO::PARAM_STR);
            $stmt->bindParam(6, $id, PDO::PARAM_INT);
            $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Records updated successfully. Redirect to landing page
                header("location: manage_bonuses.php?updated=1");
                exit();
            } else {
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
        } catch (PDOException $e) {
            $error_message = "Oops! Something went wrong. Please try again later.";
        }
    }
} else {
    // Check existence of id parameter before processing further
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        // Get URL parameter
        $id = trim($_GET["id"]);
        
        // Prepare a select statement
        $sql = "SELECT * FROM salary_bonuses WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        try {
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $param_id = $id;
            $stmt->bindParam(1, $param_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $result = $stmt->fetchAll();

                if (count($result) == 1) {
                    $row = $result[0];

                    // Retrieve individual field value
                    $bonus_user_id = $row["user_id"];
                    $amount = $row["amount"];
                    $description = $row["description"];
                    $bonus_date = $row["bonus_date"];
                    $type = $row["type"];
                } else {
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: manage_bonuses.php");
                    exit();
                }

            } else {
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
        } catch (PDOException $e) {
            $error_message = "Oops! Something went wrong. Please try again later.";
        }
    } else {
        // URL doesn't contain id parameter. Redirect to error page
        header("location: manage_bonuses.php");
        exit();
    }
}

// Fetch users with salary records
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name FROM users u JOIN salary_management sm ON u.id=sm.user_id WHERE sm.status='active' AND u.tenant_id=? AND u.branch_id=? ORDER BY u.name ASC");
    $stmt->execute([$tenant_id, $branch_id]);
    $users_with_salary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users_with_salary = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Bonus</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap & Feather Icons -->
<link rel="stylesheet" href="../assets/css/style.css">

<style>
:root {
    --ink:       #0f1117;
    --surface:   #ffffff;
    --muted:     #f4f5f7;
    --border:    #e8eaed;
    --accent:    #3d6cff;
    --accent2:   #00d9a6;
    --warn:      #ff9f43;
    --danger:    #ff4757;
    --text-sub:  #6b7280;
    --radius:    12px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 12px 40px rgba(0,0,0,.12);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: #f0f2f5;
    color: var(--ink);
}

/* ── Page wrapper ───────────────────────────────── */
.sm-page {
    padding: 28px 32px;
    max-width: 1400px;
}

/* ── Page header ────────────────────────────────── */
.page-hero {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-hero-title {
    font-family: 'Syne', sans-serif;
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -.5px;
    line-height: 1.1;
}

.page-hero-subtitle {
    font-size: 13px;
    color: var(--text-sub);
    margin-top: 4px;
    font-weight: 400;
}

.hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ── Form Card ────────────────────────────────── */
.form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.form-card-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(90deg, var(--muted) 0%, var(--muted) 100%);
}

.form-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--ink);
}

.form-card-body {
    padding: 28px;
}

/* ── Form elements ───────────────────────────────── */
.field-group {
    margin-bottom: 20px;
}

.field-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
}

.field-control, .field-textarea {
    display: block;
    width: 100%;
    padding: 11px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-family: inherit;
    font-size: 13px;
    color: var(--ink);
    background: var(--surface);
    transition: border-color .2s, box-shadow .2s;
}

.field-control:focus, .field-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(61, 108, 255, .1);
}

.field-control.is-invalid {
    border-color: var(--danger);
    background: rgba(255, 71, 87, .05);
}

.field-error {
    font-size: 12px;
    color: var(--danger);
    margin-top: 6px;
    display: block;
}

/* ── Buttons ─────────────────────────────────────── */
.btn-primary, .btn-secondary {
    border: none;
    border-radius: 6px;
    padding: 11px 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    background: #2654e3;
    box-shadow: 0 4px 14px rgba(61, 108, 255, .3);
}

.btn-secondary {
    background: var(--muted);
    color: var(--ink);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: #e8eaed;
}

/* ── Alert ───────────────────────────────────────– */
.alert {
    padding: 14px 16px;
    border-radius: 6px;
    border-left: 4px solid;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert-success {
    background: rgba(0, 217, 166, .1);
    border-left-color: var(--accent2);
    color: #118b67;
}

.alert-danger {
    background: rgba(255, 71, 87, .1);
    border-left-color: var(--danger);
    color: #c41e3a;
}

/* ── Toast ────────────────────────────────────────– */
#toastWrap {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1001;
}

.toast-msg {
    background: var(--accent2);
    color: white;
    padding: 14px 18px;
    border-radius: 6px;
    box-shadow: var(--shadow-lg);
    font-size: 13px;
    font-weight: 500;
}

.toast-msg.error {
    background: var(--danger);
}

/* ── Responsive ───────────────────────────────────– */
@media (max-width: 768px) {
    .sm-page {
        padding: 16px 16px;
    }

    .page-hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .hero-actions {
        width: 100%;
    }

    .form-card-body {
        padding: 20px;
    }
}
</style>
</head>
<body>

<!-- [ Header ] start -->
<?php include("../includes/header.php"); ?>
<!-- [ Header ] end -->

<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="sm-page">

            <!-- Page header -->
            <div class="page-hero">
                <div>
                    <div class="page-hero-title"><?= __('edit_bonus') ?></div>
                    <div class="page-hero-subtitle">Update bonus record details</div>
                </div>
                <div class="hero-actions">
                    <a href="manage_bonuses.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <?= __('back') ?>
                    </a>
                </div>
            </div>

            <!-- Form Card -->
            <div class="form-card">
                <div class="form-card-header">
                    <h3><?= __('edit_bonus_details') ?></h3>
                </div>
                <div class="form-card-body">
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong><?= __('error') ?></strong> <?= $error_message ?>
                    </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px">
                            <div class="field-group">
                                <label class="field-label"><?= __('employee') ?></label>
                                <select class="field-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" name="user_id">
                                    <option value=""><?= __('select_employee') ?></option>
                                    <?php foreach ($users_with_salary as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?php echo ($bonus_user_id == $emp['id']) ? 'selected' : ''; ?>><?= $emp['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($user_id_err)): ?>
                                <span class="field-error"><?= $user_id_err ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('bonus_amount') ?></label>
                                <input type="number" class="field-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" name="amount" step="0.01" min="0" value="<?php echo $amount; ?>">
                                <?php if (!empty($amount_err)): ?>
                                <span class="field-error"><?= $amount_err ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('bonus_type') ?></label>
                                <select class="field-control" name="type">
                                    <option value="performance" <?php echo ($type == 'performance') ? 'selected' : ''; ?>><?= __('performance_bonus') ?></option>
                                    <option value="holiday" <?php echo ($type == 'holiday') ? 'selected' : ''; ?>><?= __('holiday_bonus') ?></option>
                                    <option value="other" <?php echo ($type == 'other') ? 'selected' : ''; ?>><?= __('other') ?></option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('bonus_date') ?></label>
                                <input type="date" class="field-control <?php echo (!empty($bonus_date_err)) ? 'is-invalid' : ''; ?>" name="bonus_date" value="<?php echo $bonus_date; ?>">
                                <?php if (!empty($bonus_date_err)): ?>
                                <span class="field-error"><?= $bonus_date_err ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="field-label"><?= __('description') ?></label>
                            <textarea class="field-textarea <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>" name="description" rows="4"><?php echo $description; ?></textarea>
                            <?php if (!empty($description_err)): ?>
                            <span class="field-error"><?= $description_err ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:10px; margin-top:28px">
                            <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                            <button type="submit" class="btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                                <?= __('update_bonus') ?>
                            </button>
                            <a href="manage_bonuses.php" class="btn-secondary"><?= __('cancel') ?></a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Toast wrapper -->
<div id="toastWrap">
    <div class="toast-msg" id="toastMsg">
        <span id="toastText"></span>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// ── Toast ─────────────────────────────────────────
function showToast(msg, isError = false) {
    const wrap = document.getElementById('toastWrap');
    const toastMsg = document.getElementById('toastMsg');
    const text = document.getElementById('toastText');
    text.textContent = msg;
    toastMsg.className = 'toast-msg' + (isError ? ' error' : '');
    wrap.style.display = 'block';
    setTimeout(() => { wrap.style.display = 'none'; }, 3500);
}

<?php if (!empty($error_message)): ?>
document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes($error_message) ?>', true));
<?php endif; ?>
</script>
</body>
</html>
