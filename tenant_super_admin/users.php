<?php
include 'header.php';
require_once '../includes/UserAddonManager.php';

$tenant_id       = $_SESSION['tenant_id'];
$userAddonManager = new UserAddonManager($pdo, $tenant_id);
$usageStats      = $userAddonManager->getUsageStats();
$canAddMoreUsers = $usageStats['can_add_more'];
$availableSlots  = $usageStats['available_slots'];
$usagePercentage = $usageStats['usage_percentage'];

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? ''; $role = $_POST['role'] ?? '';
                $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
                $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
                if (empty($name)||empty($email)||empty($password)||empty($role)) { $message='Name, email, password, and role are required.'; $messageType='danger'; }
                elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $message='Please enter a valid email address.'; $messageType='danger'; }
                elseif (strlen($password)<6) { $message='Password must be at least 6 characters long.'; $messageType='danger'; }
                elseif (!$canAddMoreUsers) { $message='You have reached your user limit ('.$usageStats['max_users'].' users). Please request additional user slots.'; $messageType='danger'; }
                else {
                    require_once '../includes/PasswordValidator.php';
                    $validation = PasswordValidator::validate($password, false);
                    if (!$validation['valid']) { $message='Password does not meet requirements: '.implode(', ',$validation['errors']); $messageType='danger'; }
                    else {
                        try {
                            $stmt=$pdo->prepare("SELECT id FROM users WHERE email=? AND tenant_id=?"); $stmt->execute([$email,$tenant_id]);
                            if ($stmt->fetch()) { $message='Email address already exists.'; $messageType='danger'; }
                            else {
                                $hp=password_hash($password,PASSWORD_DEFAULT);
                                $pdo->prepare("INSERT INTO users (tenant_id,branch_id,name,email,password,role,phone,address,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")->execute([$tenant_id,$branch_id,$name,$email,$hp,$role,$phone,$address]);
                                logActivity($pdo,$tenant_id,$_SESSION['user_id'],'create','users',$pdo->lastInsertId(),null,json_encode(compact('name','email','role','branch_id')));
                                $message='User created successfully.'; $messageType='success';
                            }
                        } catch (PDOException $e) { $message='Error creating user: '.$e->getMessage(); $messageType='danger'; }
                    }
                }
                break;
            case 'update':
                $user_id=intval($_POST['user_id']??0); $name=trim($_POST['name']??''); $email=trim($_POST['email']??'');
                $role=$_POST['role']??''; $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??'');
                $branch_id=!empty($_POST['branch_id'])?$_POST['branch_id']:null; $status=$_POST['status']??'active';
                if (empty($name)||empty($email)||empty($role)||!$user_id) { $message='Name, email, role, and user ID are required.'; $messageType='danger'; }
                elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $message='Please enter a valid email address.'; $messageType='danger'; }
                else {
                    try {
                        $stmt=$pdo->prepare("SELECT id FROM users WHERE email=? AND tenant_id=? AND id!=?"); $stmt->execute([$email,$tenant_id,$user_id]);
                        if ($stmt->fetch()) { $message='Email address already exists.'; $messageType='danger'; }
                        else {
                            $stmt=$pdo->prepare("SELECT * FROM users WHERE id=? AND tenant_id=?"); $stmt->execute([$user_id,$tenant_id]); $old=$stmt->fetch(PDO::FETCH_ASSOC);
                            $pdo->prepare("UPDATE users SET name=?,email=?,role=?,phone=?,address=?,branch_id=?,updated_at=NOW() WHERE id=? AND tenant_id=?")->execute([$name,$email,$role,$phone,$address,$branch_id,$user_id,$tenant_id]);
                            logActivity($pdo,$tenant_id,$_SESSION['user_id'],'update','users',$user_id,json_encode($old),json_encode(compact('name','email','role','branch_id')));
                            $message='User updated successfully.'; $messageType='success';
                        }
                    } catch (PDOException $e) { $message='Error updating user: '.$e->getMessage(); $messageType='danger'; }
                }
                break;
            case 'reset_password':
                $user_id=intval($_POST['user_id']??0); $new_password=$_POST['new_password']??'';
                if (!$user_id||empty($new_password)) { $message='User ID and new password are required.'; $messageType='danger'; }
                elseif (strlen($new_password)<6) { $message='Password must be at least 6 characters long.'; $messageType='danger'; }
                else {
                    require_once '../includes/PasswordValidator.php'; $v=PasswordValidator::validate($new_password);
                    if (!$v['valid']) { $message='Password does not meet requirements: '.implode(', ',$v['errors']); $messageType='danger'; }
                    else {
                        try {
                            $pdo->prepare("UPDATE users SET password=?,updated_at=NOW() WHERE id=? AND tenant_id=?")->execute([password_hash($new_password,PASSWORD_DEFAULT),$user_id,$tenant_id]);
                            logActivity($pdo,$tenant_id,$_SESSION['user_id'],'reset_password','users',$user_id,null,null);
                            $message='Password reset successfully.'; $messageType='success';
                        } catch (PDOException $e) { $message='Error resetting password: '.$e->getMessage(); $messageType='danger'; }
                    }
                }
                break;
            case 'delete':
                $user_id=intval($_POST['user_id']??0);
                $permanent = !empty($_POST['permanent']);
                if (!$user_id) { $message='Invalid user ID.'; $messageType='danger'; }
                elseif ($user_id===intval($_SESSION['user_id'])) { $message='You cannot delete your own account.'; $messageType='danger'; }
                else {
                    try {
                        $stmt=$pdo->prepare("SELECT id, name, role FROM users WHERE id=? AND tenant_id=?"); $stmt->execute([$user_id,$tenant_id]);
                        $target=$stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$target) { $message='User not found.'; $messageType='danger'; }
                        elseif ($target['role']==='tenant_super_admin') {
                            $stmt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id=? AND role='tenant_super_admin' AND fired=0 AND id!=?"); $stmt->execute([$tenant_id,$user_id]);
                            if ($stmt->fetchColumn()==0) { $message='Cannot delete the last super admin. You need at least one super admin account.'; $messageType='danger'; }
                            else { $deletable=true; }
                        } else { $deletable=true; }
                        if (!empty($deletable)) {
                            if ($permanent) {
                                $pdo->beginTransaction();
                                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                                try {
                                    $owned = [
                                        ['attendance',          'DELETE FROM attendance WHERE user_id = ?', [$user_id]],
                                        ['totp_secrets',        'DELETE FROM totp_secrets WHERE user_id = ?', [$user_id]],
                                        ['totp_recovery_codes', 'DELETE FROM totp_recovery_codes WHERE user_id = ?', [$user_id]],
                                        ['user_online_sessions','DELETE FROM user_online_sessions WHERE user_id = ?', [$user_id]],
                                        ['user_typing_status',  'DELETE FROM user_typing_status WHERE user_id = ?', [$user_id]],
                                        ['user_tutorial_learned','DELETE FROM user_tutorial_learned WHERE user_id = ?', [$user_id]],
                                        ['floating_tasks',      'DELETE FROM floating_tasks WHERE user_id = ?', [$user_id]],
                                        ['password_resets',     'DELETE FROM password_resets WHERE user_id = ?', [$user_id]],
                                        ['user_agreements',     'DELETE FROM user_agreements WHERE user_id = ?', [$user_id]],
                                        ['user_documents',      'DELETE FROM user_documents WHERE user_id = ?', [$user_id]],
                                        ['user_blocks',         'DELETE FROM user_blocks WHERE user_id = ? OR blocked_user_id = ?', [$user_id,$user_id]],
                                        ['user_mutes',          'DELETE FROM user_mutes WHERE user_id = ? OR muted_user_id = ?', [$user_id,$user_id]],
                                        ['performance_reviews', 'DELETE FROM performance_reviews WHERE user_id = ?', [$user_id]],
                                        ['payroll_details',     'DELETE FROM payroll_details WHERE user_id = ?', [$user_id]],
                                        ['salary_adjustments',  'DELETE FROM salary_adjustments WHERE user_id = ?', [$user_id]],
                                        ['salary_advances',     'DELETE FROM salary_advances WHERE user_id = ?', [$user_id]],
                                        ['salary_bonuses',      'DELETE FROM salary_bonuses WHERE user_id = ?', [$user_id]],
                                        ['salary_deductions',   'DELETE FROM salary_deductions WHERE user_id = ?', [$user_id]],
                                        ['salary_payments',     'DELETE FROM salary_payments WHERE user_id = ?', [$user_id]],
                                        ['employee_terminations','DELETE FROM employee_terminations WHERE employee_id = ?', [$user_id]],
                                        ['chat_group_members',  'DELETE FROM chat_group_members WHERE member_id = ?', [$user_id]],
                                        ['activity_log',        'DELETE FROM activity_log WHERE user_id = ?', [$user_id]],
                                    ];
                                    foreach ($owned as [$t, $sql, $params]) {
                                        $pdo->prepare($sql)->execute($params);
                                    }
                                    $pdo->prepare("UPDATE subscription_payments SET processed_by = NULL WHERE processed_by = ?")->execute([$user_id]);
                                    $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?")->execute([$user_id,$tenant_id]);
                                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                                    $pdo->commit();
                                    logActivity($pdo,$tenant_id,$_SESSION['user_id'],'hard_delete','users',$user_id,null,json_encode(['name'=>$target['name'],'role'=>$target['role']]));
                                    $message='User permanently deleted. All their owned records were removed.'; $messageType='success';
                                } catch (Exception $e) {
                                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                                    throw $e;
                                }
                            } else {
                                $pdo->prepare("UPDATE users SET fired=1,fired_at=NOW(),deleted_at=NOW(),updated_at=NOW() WHERE id=? AND tenant_id=?")->execute([$user_id,$tenant_id]);
                                logActivity($pdo,$tenant_id,$_SESSION['user_id'],'delete','users',$user_id,null,json_encode(['name'=>$target['name'],'role'=>$target['role']]));
                                $message='User deleted successfully. Their historical records are kept.'; $messageType='success';
                            }
                        }
                    } catch (PDOException $e) { $message='Error deleting user: '.$e->getMessage(); $messageType='danger'; }
                }
                break;
        }
    }
}

try {
    $stmt=$pdo->prepare("SELECT u.*,b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id=b.id WHERE u.tenant_id=? AND u.fired=0 ORDER BY u.created_at DESC");
    $stmt->execute([$tenant_id]); $users=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $users=[]; }

try {
    $stmt=$pdo->prepare("SELECT id,name,code FROM branches WHERE tenant_id=? AND status='active' ORDER BY name");
    $stmt->execute([$tenant_id]); $branches=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $branches=[]; }

$userRoles = ['admin'=>'Branch Admin','sales'=>'Sales','finance'=>'Finance'];
if (hasFeature('umrah_bookings', $allowed_features ?? [])) { $userRoles['umrah']='Umrah'; }

function logActivity($pdo,$tenant_id,$user_id,$action,$table_name,$record_id,$old_values,$new_values) {
    try {
        $pdo->prepare("INSERT INTO activity_log (tenant_id,user_id,action,table_name,record_id,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$tenant_id,$user_id,$action,$table_name,$record_id,$old_values,$new_values,$_SERVER['REMOTE_ADDR'],$_SERVER['HTTP_USER_AGENT']]);
    } catch (PDOException $e) { error_log("Failed to log activity: ".$e->getMessage()); }
}

$usage_pct = min(100, intval($usagePercentage));
$role_colors = ['admin'=>'var(--blue)','sales'=>'var(--green)','finance'=>'var(--purple)','umrah'=>'var(--teal)','visa'=>'var(--amber)'];
$role_bg     = ['admin'=>'rgba(64,153,255,0.1)','sales'=>'rgba(34,197,94,0.1)','finance'=>'rgba(139,92,246,0.1)','umrah'=>'rgba(46,216,182,0.1)','visa'=>'rgba(245,158,11,0.1)'];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:    #2ed8b6; --blue:   #4099ff;
    --grad:    linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);
    --surface: #f4f7fe; --card-bg:#ffffff; --border: #e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:  #22c55e; --amber: #f59e0b; --red: #ef4444; --purple:#8b5cf6;
    --radius: 14px;
    --shadow: 0 2px 12px rgba(64,153,255,0.08);
    --shadow-md:0 6px 24px rgba(64,153,255,0.13);
}

*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

/* Header */
.dash-header{background:var(--grad);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(64,153,255,0.22);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Alerts */
.dash-alert{display:flex;align-items:flex-start;gap:12px;padding:14px 20px;border-radius:var(--radius);margin-bottom:16px;font-size:14px;font-weight:500;animation:slideDown .3s ease}
.dash-alert-success{background:#dcfce7;color:#166534;border-left:4px solid var(--green)}
.dash-alert-danger {background:#fee2e2;color:#991b1b;border-left:4px solid var(--red)}
.dash-alert-warning{background:#fef3c7;color:#92400e;border-left:4px solid var(--amber)}
.dash-alert .close-btn{background:none;border:none;cursor:pointer;opacity:.5;font-size:18px;line-height:1;padding:0;color:inherit;margin-left:auto;flex-shrink:0}
.dash-alert .close-btn:hover{opacity:1}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* Top stat strip */
.stat-strip{display:grid;grid-template-columns:280px 1fr;gap:18px;margin-bottom:20px}
@media(max-width:900px){.stat-strip{grid-template-columns:1fr}}

.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:var(--grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(64,153,255,0.1);color:var(--blue);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Usage card */
.usage-nums{font-size:32px;font-weight:800;font-family:'JetBrains Mono',monospace;color:var(--blue);line-height:1;margin-bottom:3px}
.usage-nums span{font-size:16px;font-weight:600;color:var(--text-sub)}
.usage-sublabel{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ubar{height:7px;background:var(--border);border-radius:99px;overflow:hidden;margin-bottom:5px}
.ubar-fill{height:100%;border-radius:99px;background:var(--grad);transition:width .6s ease}
.ubar-fill.warn{background:linear-gradient(90deg,var(--amber),var(--red))}
.ubar-meta{display:flex;justify-content:space-between;font-size:11px;color:var(--text-sub);font-weight:600;margin-bottom:14px}
.usage-split{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px}
.usage-split-cell{background:var(--card-bg);padding:10px;text-align:center}
.usc-label{font-size:10px;font-weight:600;color:var(--text-sub);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.usc-val{font-size:16px;font-weight:800;font-family:'JetBrains Mono',monospace}
.usc-val.blue{color:var(--blue)} .usc-val.green{color:var(--green)}

/* Action card */
.action-card-body{padding:24px;display:flex;flex-direction:column;height:100%}
.action-card-body h6{font-size:15px;font-weight:700;margin-bottom:6px}
.action-card-body p{font-size:13px;color:var(--text-sub);margin-bottom:auto;padding-bottom:20px}
.action-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.btn-create{display:inline-flex;align-items:center;gap:7px;background:var(--grad);color:#fff;border:none;border-radius:10px;padding:11px 22px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none}
.btn-create:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 16px rgba(64,153,255,.3);color:#fff}
.btn-create:disabled,.btn-create.disabled{opacity:.4;cursor:not-allowed;transform:none;pointer-events:none}
.btn-request{display:inline-flex;align-items:center;gap:7px;background:rgba(245,158,11,.1);color:var(--amber);border:1.5px solid rgba(245,158,11,.25);border-radius:10px;padding:11px 20px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none}
.btn-request:hover{background:rgba(245,158,11,.2);color:var(--amber);text-decoration:none}
.avail-note{font-size:12px;color:var(--text-sub);font-weight:600}

/* Search */
.search-wrap{display:flex;align-items:center;gap:8px;margin-left:auto}
.search-input{border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;width:220px;transition:border-color .2s}
.search-input:focus{border-color:var(--blue);background:#fff}

/* Table */
.user-table{width:100%;border-collapse:collapse}
.user-table thead th{background:var(--surface);padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap}
.user-table tbody tr{transition:background .15s}
.user-table tbody tr:hover{background:var(--surface)}
.user-table tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:14px;vertical-align:middle}
.user-table tbody tr:last-child td{border-bottom:none}

/* User cell */
.user-cell{display:flex;align-items:center;gap:12px}
.user-avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0}
.user-name{font-weight:700;color:var(--text-main);line-height:1.2}
.user-id{font-size:11px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}

.td-email a{color:var(--blue);text-decoration:none;font-size:13px}
.td-email a:hover{text-decoration:underline}

/* Role pills */
.role-pill{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 11px;font-size:12px;font-weight:700;text-transform:capitalize}

/* Branch pill */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(64,153,255,.08);color:var(--blue);border-radius:20px;padding:4px 11px;font-size:12px;font-weight:600}
.td-phone{font-size:12px;color:var(--text-sub)}
.td-date{font-size:12px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}

/* Action buttons */
.act-btn{width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;font-size:13px;margin:0 2px}
.act-btn.edit{color:var(--blue)}   .act-btn.edit:hover{background:rgba(64,153,255,.1);border-color:var(--blue)}
.act-btn.key{color:var(--amber)}   .act-btn.key:hover{background:rgba(245,158,11,.1);border-color:var(--amber)}
.act-btn.del{color:var(--red)}     .act-btn.del:hover{background:rgba(239,68,68,.1);border-color:var(--red)}
.act-btn.del-hard{color:#fff;background:var(--red);border-color:var(--red)}   .act-btn.del-hard:hover{background:#dc2626;border-color:#dc2626}

/* Empty */
.empty-state{text-align:center;padding:60px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state h5{font-weight:700;margin-bottom:6px}
.empty-state p{color:var(--text-sub);font-size:14px;margin-bottom:20px}

/* Modals */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:var(--grad);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:16px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}
.modal-body{padding:24px}
.modal-footer{border-top:1px solid var(--border);padding:16px 24px;display:flex;justify-content:flex-end;gap:8px}

/* Reset password modal header */
.modal-header.key-header{background:linear-gradient(135deg,var(--amber),#f97316)}

.form-group label{font-size:12px;font-weight:700;color:var(--text-sub);margin-bottom:6px;display:block;text-transform:uppercase;letter-spacing:.5px}
.form-control{border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-family:inherit;font-size:14px;transition:border-color .2s;background:var(--surface);color:var(--text-main)}
.form-control:focus{border-color:var(--blue);outline:none;box-shadow:0 0 0 3px rgba(64,153,255,.12);background:#fff}
.form-text{font-size:11px;color:var(--text-sub);margin-top:4px}

.btn-modal-primary{background:var(--grad);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s}
.btn-modal-primary:hover{opacity:.9;transform:translateY(-1px)}
.btn-modal-amber{background:linear-gradient(135deg,var(--amber),#f97316);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s}
.btn-modal-amber:hover{opacity:.9}
.btn-modal-secondary{background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-modal-secondary:hover{border-color:var(--text-sub);color:var(--text-main)}

.warn-box{background:#fef3c7;border-left:4px solid var(--amber);border-radius:10px;padding:12px 16px;font-size:13px;color:#92400e;display:flex;gap:10px;margin-top:12px}

/* Overrides */
.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-users" style="margin-right:8px;"></i>User Management</h4>
            <p>Manage your team members and their access permissions</p>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
    <div class="dash-alert dash-alert-<?= $messageType ?>">
        <i class="feather icon-<?= $messageType==='success'?'check-circle':'alert-circle' ?>"></i>
        <div style="flex:1;"><?= htmlspecialchars($message) ?></div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Stat strip -->
    <div class="stat-strip">
        <!-- Usage card -->
        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-head">
                <h6><span class="ico"><i class="feather icon-users"></i></span>User Usage</h6>
            </div>
            <div class="dash-card-body">
                <div class="usage-nums"><?= $usageStats['current_users'] ?><span> / <?= $usageStats['max_users'] ?></span></div>
                <div class="usage-sublabel">Users Used</div>
                <div class="ubar"><div class="ubar-fill <?= $usage_pct>=75?'warn':'' ?>" style="width:<?= $usage_pct ?>%"></div></div>
                <div class="ubar-meta"><span><?= $usage_pct ?>% used</span><span><?= $availableSlots ?> slots left</span></div>
                <div class="usage-split">
                    <div class="usage-split-cell">
                        <div class="usc-label">Base</div>
                        <div class="usc-val blue"><?= $usageStats['base_users'] ?></div>
                    </div>
                    <div class="usage-split-cell">
                        <div class="usc-label">Add-ons</div>
                        <div class="usc-val green">+<?= $usageStats['additional_users'] ?></div>
                    </div>
                </div>
                <?php if (!$canAddMoreUsers): ?>
                <div class="dash-alert dash-alert-warning" style="margin:0;padding:10px 14px;font-size:12px;">
                    <i class="feather icon-alert-triangle"></i>
                    <span>User limit reached. <a href="request_user_addon.php" style="font-weight:700;color:inherit;">Request more slots</a></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action card -->
        <div class="dash-card" style="margin-bottom:0;">
            <div class="action-card-body">
                <h6><i class="feather icon-user-plus" style="margin-right:7px;color:var(--blue);"></i>Add Team Members</h6>
                <p>Create new user accounts and assign roles and branches to control access across your organization.</p>
                <div class="action-row">
                    <?php if ($canAddMoreUsers): ?>
                    <button type="button" class="btn-create" data-toggle="modal" data-target="#createUserModal">
                        <i class="feather icon-plus"></i>Create New User
                    </button>
                    <span class="avail-note"><?= $availableSlots ?> user<?= $availableSlots!=1?'s':'' ?> available</span>
                    <?php else: ?>
                    <button class="btn-create disabled" disabled><i class="feather icon-plus"></i>Create New User</button>
                    <a href="request_user_addon.php" class="btn-request"><i class="feather icon-plus-circle"></i>Request More Users</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>All Users</h6>
            <span class="count-badge"><?= count($users) ?> user<?= count($users)!==1?'s':'' ?></span>
            <div class="search-wrap" style="margin-left:auto;">
                <input type="text" class="search-input" id="userSearch" placeholder="Search users...">
            </div>
        </div>

        <?php if (!empty($users)): ?>
        <div style="overflow-x:auto;">
            <table class="user-table" id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Phone</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $role   = $user['role'];
                        $rColor = $role_colors[$role] ?? 'var(--text-sub)';
                        $rBg    = $role_bg[$role]    ?? 'rgba(107,122,153,0.1)';
                        $pic    = $user['profile_pic'] ?: 'default-avatar.jpg';
                        $picSrc = strpos($pic,'assets/')!==false ? '../'.htmlspecialchars($pic) : '../assets/images/user/'.htmlspecialchars($pic);
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <img src="<?= $picSrc ?>" class="user-avatar" alt="">
                                <div>
                                    <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                    <div class="user-id">ID <?= $user['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="td-email"><a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a></td>
                        <td>
                            <span class="role-pill" style="background:<?= $rBg ?>;color:<?= $rColor ?>;">
                                <?= htmlspecialchars($userRoles[$role] ?? ucfirst($role)) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['branch_name']): ?>
                                <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($user['branch_name']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--border);font-style:italic;font-size:12px;">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-phone">
                            <?= $user['phone'] ? '<a href="tel:'.htmlspecialchars($user['phone']).'" style="color:var(--text-sub);text-decoration:none;"><i class="feather icon-phone" style="margin-right:3px;"></i>'.htmlspecialchars($user['phone']).'</a>' : '<span style="color:var(--border);">— </span>' ?>
                        </td>
                        <td class="td-date"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <button class="act-btn edit" onclick="editUser(<?= $user['id'] ?>)" title="Edit User"><i class="feather icon-edit"></i></button>
                            <button class="act-btn key" onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'],ENT_QUOTES) ?>')" title="Reset Password"><i class="fas fa-key"></i></button>
                            <?php if ($user['id'] !== intval($_SESSION['user_id'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user &quot;<?= htmlspecialchars($user['name'],ENT_QUOTES) ?>&quot;? They will lose access immediately and be removed from this list. Their historical records (salary, tickets, activity) are kept. This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
                                <button type="submit" class="act-btn del" title="Delete User (keeps history)"><i class="feather icon-trash-2"></i></button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('PERMANENTLY DELETE user &quot;<?= htmlspecialchars($user['name'],ENT_QUOTES) ?>&quot;? This removes them AND all their owned records (attendance, salary, documents, reviews, activity log, chat group memberships, etc.). Subscription payment logs referencing them are unassigned. This CANNOT be undone!')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="permanent" value="1">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
                                <button type="submit" class="act-btn del-hard" title="Delete User Permanently (removes all their data)"><i class="feather icon-x-octagon"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-users"></i>
            <h5>No users yet</h5>
            <p>Create your first user to start building your team.</p>
            <?php if ($canAddMoreUsers): ?>
            <button type="button" class="btn-create" data-toggle="modal" data-target="#createUserModal">
                <i class="feather icon-plus"></i>Create First User
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-user-plus" style="margin-right:8px;"></i>Create New User</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Full Name *</label><input type="text" class="form-control" name="name" required placeholder="John Doe"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Email Address *</label><input type="email" class="form-control" name="email" required placeholder="john@example.com"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" class="form-control" id="userPassword" name="password" required minlength="6">
<div class="form-text">Min 6 characters. Tip: mix uppercase, lowercase, numbers & special characters for a stronger password</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role *</label>
                                <select class="form-control" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($userRoles as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Phone</label><input type="tel" class="form-control" name="phone" placeholder="+1 000 000 0000"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch</label>
                                <select class="form-control" name="branch_id">
                                    <option value="">Select Branch (Optional)</option>
                                    <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> &middot; <?= htmlspecialchars($b['code']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea class="form-control" name="address" rows="2" placeholder="Optional address..."></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal-primary"><i class="feather icon-user-plus" style="margin-right:5px;"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-edit" style="margin-right:8px;"></i>Edit User</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="editUserId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Full Name *</label><input type="text" class="form-control" id="editUserName" name="name" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Email Address *</label><input type="email" class="form-control" id="editUserEmail" name="email" required></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role *</label>
                                <select class="form-control" id="editUserRole" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($userRoles as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch</label>
                                <select class="form-control" id="editUserBranch" name="branch_id">
                                    <option value="">Select Branch (Optional)</option>
                                    <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> &middot; <?= htmlspecialchars($b['code']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Phone</label><input type="tel" class="form-control" id="editUserPhone" name="phone"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" id="editUserStatus" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea class="form-control" id="editUserAddress" name="address" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal-primary"><i class="feather icon-save" style="margin-right:5px;"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header key-header">
                <h5 class="modal-title"><i class="fas fa-key" style="margin-right:8px;"></i>Reset Password</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
                <div class="modal-body">
                    <p style="font-size:14px;color:var(--text-main);">Reset password for <strong id="resetUserName"></strong></p>
                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6">
                        <div class="form-text">Min 6 chars with uppercase, lowercase, numbers & special characters</div>
                    </div>
                    <div class="warn-box">
                        <i class="feather icon-alert-triangle" style="flex-shrink:0;margin-top:1px;"></i>
                        <span>This will change the user's password immediately. Inform the user of their new credentials.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal-amber"><i class="fas fa-key" style="margin-right:5px;"></i>Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Live search
    $('#userSearch').on('keyup', function() {
        const q = $(this).val().toLowerCase();
        $('#usersTable tbody tr').each(function() {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });
});

const canAddMoreUsers = <?= $canAddMoreUsers ? 'true' : 'false' ?>;

$('#createUserModal').on('show.bs.modal', function(e) {
    if (!canAddMoreUsers) { e.preventDefault(); alert('You have reached your user limit. Please request additional user slots.'); }
});

function editUser(userId) {
    $.ajax({
        url: 'get_user.php', type: 'GET', data: { id: userId }, dataType: 'json',
        success: function(r) {
            if (r.success) {
                const u = r.user;
                $('#editUserId').val(u.id); $('#editUserName').val(u.name);
                $('#editUserEmail').val(u.email); $('#editUserRole').val(u.role);
                $('#editUserPhone').val(u.phone||''); $('#editUserAddress').val(u.address||'');
                $('#editUserBranch').val(u.branch_id||'');
                $('#editUserStatus').val(u.fired ? 'inactive' : 'active');
                $('#editUserModal').modal('show');
            } else { alert('Error loading user: '+(r.message||'Unknown error')); }
        },
        error: function(xhr,s,e) { alert('Error loading user data: '+e); }
    });
}

function resetPassword(id, name) {
    $('#resetUserId').val(id); $('#resetUserName').text('"'+name+'"');
    $('#resetPasswordModal').modal('show');
}
</script>

<?php include 'footer.php'; ?>