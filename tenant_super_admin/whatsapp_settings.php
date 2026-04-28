<?php
/**
 * WhatsApp Settings Management Interface
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/CsrfProtection.php';
require_once '../includes/CommunicationAddonManager.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['user_id'];
$communicationAddonManager = new CommunicationAddonManager($pdo, $tenant_id);
$has_whatsapp_addon = $communicationAddonManager->hasActiveAddon($tenant_id, 'whatsapp');

if (!$has_whatsapp_addon) {
    $_SESSION['comm_addon_error'] = 'Please purchase the WhatsApp add-on first.';
    header('Location: request_communication_addon.php');
    exit();
}

// Handle AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Security token validation failed']);
        exit;
    }
    if (isset($_POST['action'])) { handleAjaxRequest(); }
    exit;
}

$whatsapp_settings = loadWhatsAppSettings($tenant_id);
if (empty($whatsapp_settings)) {
    ensureDefaultSettings($tenant_id);
    $whatsapp_settings = loadWhatsAppSettings($tenant_id);
}
$whatsapp_settings = array_merge(getDefaultSettings(), $whatsapp_settings);
$analytics = loadAnalytics($tenant_id);

/* â”€â”€ DB helpers â”€â”€ */
function loadWhatsAppSettings($tid) {
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id=?");
        $s->execute([$tid]); return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) { error_log($e->getMessage()); return []; }
}
function getDefaultSettings() {
    return ['provider'=>'meta','api_token'=>'','phone_number_id'=>'','webhook_verify_token'=>'','webhook_url'=>'','auto_notifications'=>1,'real_time_notifications'=>0,'max_messages_per_hour'=>1000,'retry_attempts'=>3,'status'=>'inactive'];
}
function ensureDefaultSettings($tid) {
    global $pdo;
    $s=$pdo->prepare("SELECT id FROM whatsapp_settings WHERE tenant_id=?"); $s->execute([$tid]);
    if(!$s->fetch()){
        try{$pdo->prepare("INSERT INTO whatsapp_settings(tenant_id,provider,api_token,phone_number_id,webhook_verify_token,webhook_url,status,auto_notifications,real_time_notifications,max_messages_per_hour,retry_attempts,created_at,updated_at) VALUES(?,'meta','','','','','inactive',1,0,1000,3,NOW(),NOW())")->execute([$tid]);}catch(Exception $e){error_log($e->getMessage());}
    }
}
function loadAnalytics($tid) {
    global $pdo;
    try{ $s=$pdo->prepare("SELECT * FROM whatsapp_analytics WHERE tenant_id=? ORDER BY date DESC LIMIT 30"); $s->execute([$tid]); return $s->fetchAll(PDO::FETCH_ASSOC); }catch(Exception $e){ return []; }
}

/* â”€â”€ AJAX dispatcher â”€â”€ */
function handleAjaxRequest() {
    global $pdo,$tenant_id,$user_id;
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']??'') {
            case 'update_settings':   updateSettings();   break;
            case 'test_connection':   testConnection();   break;
            case 'send_test_message': sendTestMessage();  break;
            case 'get_queue_status':  getQueueStatus();   break;
            case 'process_queue':     processQueue();     break;
            default: throw new Exception("Invalid action");
        }
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
}
function updateSettings(){
    global $pdo,$tenant_id;
    $d=['provider'=>$_POST['provider']??'meta','api_token'=>$_POST['api_token']??'','phone_number_id'=>$_POST['phone_number_id']??'','webhook_verify_token'=>$_POST['webhook_verify_token']??'','webhook_url'=>$_POST['webhook_url']??'','auto_notifications'=>isset($_POST['auto_notifications'])?1:0,'real_time_notifications'=>isset($_POST['real_time_notifications'])?1:0,'max_messages_per_hour'=>(int)($_POST['max_messages_per_hour']??1000),'retry_attempts'=>(int)($_POST['retry_attempts']??3),'status'=>$_POST['status']??'inactive'];
    $chk=$pdo->prepare("SELECT id FROM whatsapp_settings WHERE tenant_id=?"); $chk->execute([$tenant_id]);
    if($chk->fetch()){
        $s=$pdo->prepare("UPDATE whatsapp_settings SET provider=?,api_token=?,phone_number_id=?,webhook_verify_token=?,webhook_url=?,auto_notifications=?,real_time_notifications=?,max_messages_per_hour=?,retry_attempts=?,status=?,updated_at=NOW() WHERE tenant_id=?");
        $s->execute([$d['provider'],$d['api_token'],$d['phone_number_id'],$d['webhook_verify_token'],$d['webhook_url'],$d['auto_notifications'],$d['real_time_notifications'],$d['max_messages_per_hour'],$d['retry_attempts'],$d['status'],$tenant_id]);
    } else {
        $s=$pdo->prepare("INSERT INTO whatsapp_settings(tenant_id,provider,api_token,phone_number_id,webhook_verify_token,webhook_url,auto_notifications,real_time_notifications,max_messages_per_hour,retry_attempts,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $s->execute([$tenant_id,$d['provider'],$d['api_token'],$d['phone_number_id'],$d['webhook_verify_token'],$d['webhook_url'],$d['auto_notifications'],$d['real_time_notifications'],$d['max_messages_per_hour'],$d['retry_attempts'],$d['status']]);
    }
    echo json_encode(['success'=>true,'message'=>'Settings updated successfully']);
}
function testConnection(){
    global $pdo,$tenant_id;
    $s=$pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id=?"); $s->execute([$tenant_id]);
    $cfg=$s->fetch(PDO::FETCH_ASSOC);
    $tok=$_POST['api_token']??$cfg['api_token']??''; $pid=$_POST['phone_number_id']??$cfg['phone_number_id']??'';
    if(empty($tok)||empty($pid)) throw new Exception("API token and phone number ID are required");
    if(!function_exists('curl_init')){ echo json_encode(['success'=>false,'message'=>'cURL not available on server']); return; }
    $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>"https://graph.facebook.com/v18.0/{$pid}",CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Authorization: Bearer {$tok}","Content-Type: application/json"],CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);
    $resp=curl_exec($ch); $err=curl_error($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $time=curl_getinfo($ch,CURLINFO_TOTAL_TIME); curl_close($ch);
    if($err){ echo json_encode(['success'=>false,'message'=>'cURL error: '.$err]); return; }
    if($code===200) echo json_encode(['success'=>true,'message'=>"Connected successfully ({$time}s)"]);
    elseif($code===401) echo json_encode(['success'=>false,'message'=>'Unauthorized - invalid API token']);
    elseif($code===403) echo json_encode(['success'=>false,'message'=>'Forbidden - check account approval']);
    else echo json_encode(['success'=>false,'message'=>"HTTP {$code}: {$resp}"]);
}
function sendTestMessage(){
    global $pdo,$tenant_id;
    $phone=preg_replace('/[^0-9]/','', $_POST['phone_number']??''); $msg=$_POST['message']??'Test message';
    if(empty($phone)) throw new Exception("Phone number is required");
    $s=$pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id=?"); $s->execute([$tenant_id]); $cfg=$s->fetch(PDO::FETCH_ASSOC);
    if(!$cfg||empty($cfg['api_token'])||empty($cfg['phone_number_id'])) throw new Exception("WhatsApp not configured");
    $payload=json_encode(['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'text','text'=>['body'=>$msg]]);
    $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>"https://graph.facebook.com/v18.0/{$cfg['phone_number_id']}/messages",CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>["Authorization: Bearer {$cfg['api_token']}","Content-Type: application/json"],CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>false]);
    $resp=curl_exec($ch); $err=curl_error($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $rd=json_decode($resp,true);
    $ins=$pdo->prepare("INSERT INTO whatsapp_messages(tenant_id,phone_number,message,message_type,reference_id,status,provider_message_id,created_at) VALUES(?,?,?,'test',0,?,?,NOW())");
    if($err){ $ins->execute([$tenant_id,$phone,$msg,'failed',null]); echo json_encode(['success'=>false,'message'=>'Network error: '.$err]); return; }
    if($code===200&&isset($rd['messages'][0]['id'])){ $ins->execute([$tenant_id,$phone,$msg,'sent',$rd['messages'][0]['id']]); echo json_encode(['success'=>true,'message'=>'Test message sent!','message_id'=>$rd['messages'][0]['id']]); }
    else{ $ins->execute([$tenant_id,$phone,$msg,'failed',null]); echo json_encode(['success'=>false,'message'=>"HTTP {$code}: ".($rd['error']['message']??$resp)]); }
}
function getQueueStatus(){
    global $pdo,$tenant_id;
    $s=$pdo->prepare("SELECT status,COUNT(*) as count FROM whatsapp_messages WHERE tenant_id=? GROUP BY status"); $s->execute([$tenant_id]);
    echo json_encode(['success'=>true,'queue_status'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
}
function processQueue(){
    global $pdo,$tenant_id;
    $sm=$pdo->prepare("SELECT * FROM whatsapp_messages WHERE tenant_id=? AND status='pending' ORDER BY created_at ASC LIMIT 10"); $sm->execute([$tenant_id]); $msgs=$sm->fetchAll(PDO::FETCH_ASSOC);
    $sc=$pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id=?"); $sc->execute([$tenant_id]); $cfg=$sc->fetch(PDO::FETCH_ASSOC);
    if(!$cfg||empty($cfg['api_token'])) { echo json_encode(['success'=>false,'message'=>'Not configured']); return; }
    $ok=$fail=0;
    foreach($msgs as $m){
        $phone=preg_replace('/[^0-9]/','', $m['phone_number']);
        $payload=json_encode(['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'text','text'=>['body'=>$m['message']]]);
        $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>"https://graph.facebook.com/v18.0/{$cfg['phone_number_id']}/messages",CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>["Authorization: Bearer {$cfg['api_token']}","Content-Type: application/json"],CURLOPT_TIMEOUT=>30]);
        $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        $rd=json_decode($resp,true); $success=$code===200&&isset($rd['messages'][0]['id']);
        $upd=$pdo->prepare("UPDATE whatsapp_messages SET status=?,provider_message_id=?,sent_at=NOW() WHERE id=?");
        $upd->execute([$success?'sent':'failed',$success?$rd['messages'][0]['id']:null,$m['id']]);
        $success?$ok++:$fail++;
    }
    echo json_encode(['success'=>true,'processed'=>$ok,'failed'=>$fail,'total'=>count($msgs)]);
}

include 'header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#22c55e;--red:#ef4444;--amber:#f59e0b;
    /* WhatsApp: green brand identity */
    --c1:#25d366;--c2:#128c7e;
    --radius:14px;--shadow:0 2px 12px rgba(37,211,102,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

/* Header */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(37,211,102,.3);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;position:relative}
.dash-header p{color:rgba(255,255,255,.8);margin:0;font-size:13px;position:relative}
.test-conn-btn{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;position:relative}
.test-conn-btn:hover{background:rgba(255,255,255,.3)}
.test-conn-btn:disabled{opacity:.6;cursor:not-allowed}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px}
.dash-card-head-left{display:flex;align-items:center;gap:8px}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0}
.ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.ico-wa    {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%)}
.ico-test  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%)}
.ico-queue {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%)}
.dash-card-body{padding:20px}

/* Layout grid */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:900px){.two-col{grid-template-columns:1fr}}

/* System info strip */
.sys-strip{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap}
.sys-item{background:var(--surface);border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px;flex:1;min-width:80px}
.sys-item-icon{font-size:16px}
.sys-item-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block}
.sys-item-val{font-size:12px;font-weight:700;color:var(--text-main);font-family:'JetBrains Mono',monospace}

/* Form */
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-grid-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:600px){.form-grid-2,.form-grid-3{grid-template-columns:1fr}}
.form-group{margin-bottom:14px}
.form-group:last-child{margin-bottom:0}
.form-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:flex;align-items:center;gap:5px;margin-bottom:6px}
.form-label i{color:#25d366;font-size:11px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus{border-color:#25d366;background:#fff;box-shadow:0 0 0 3px rgba(37,211,102,.1)}
select.form-input{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7a99' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}
textarea.form-input{resize:vertical;min-height:120px}

/* Password toggle */
.input-pw-wrap{position:relative}
.input-pw-wrap .form-input{padding-right:42px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-sub);padding:0;font-size:14px}
.pw-toggle:hover{color:#25d366}

/* Toggle switches */
.toggle-row{display:flex;align-items:center;justify-content:space-between;background:var(--surface);border-radius:10px;padding:12px 14px;margin-bottom:10px}
.toggle-row:last-child{margin-bottom:0}
.toggle-label{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text-main)}
.toggle-label i{color:#25d366;font-size:14px}
.toggle-desc{font-size:11px;color:var(--text-sub);display:block;margin-top:2px}
.switch{position:relative;display:inline-block;width:42px;height:24px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.switch-slider{position:absolute;cursor:pointer;inset:0;background:var(--border);border-radius:24px;transition:.3s}
.switch-slider::before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.switch input:checked+.switch-slider{background:#25d366}
.switch input:checked+.switch-slider::before{transform:translateX(18px)}

/* Save btn */
.save-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:11px 24px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;margin-top:6px;transition:opacity .2s}
.save-btn:hover{opacity:.9}
.save-btn:disabled{opacity:.6;cursor:not-allowed}

/* Queue */
.queue-row{display:flex;align-items:center;justify-content:space-between;background:var(--surface);border-radius:10px;padding:10px 14px;margin-bottom:8px}
.queue-row:last-child{margin-bottom:0}
.q-badge{display:inline-flex;align-items:center;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}
.q-pending  {background:rgba(245,158,11,.12);color:#92400e}
.q-sent     {background:rgba(34,197,94,.12);color:#166534}
.q-delivered{background:rgba(8,145,178,.12);color:#0e7490}
.q-failed   {background:rgba(239,68,68,.1);color:#991b1b}
.q-expired  {background:rgba(107,122,153,.1);color:var(--text-sub)}
.q-count{font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:800;color:var(--text-main)}
.process-btn{display:inline-flex;align-items:center;gap:5px;border:1.5px solid rgba(245,158,11,.3);border-radius:8px;padding:6px 12px;background:rgba(245,158,11,.1);color:#92400e;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s}
.process-btn:hover{background:rgba(245,158,11,.2)}
.process-btn:disabled{opacity:.6;cursor:not-allowed}

/* Test form */
.send-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;margin-top:4px;transition:opacity .2s}
.send-btn:hover{opacity:.9}
.send-btn:disabled{opacity:.6;cursor:not-allowed}
.field-hint{font-size:11px;color:var(--text-sub);margin-top:5px}

/* Toast */
.notif-toast{position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
.notif-toast.success{background:#fff;border:1.5px solid rgba(37,211,102,.35);color:#166534}
.notif-toast.error  {background:#fff;border:1.5px solid rgba(239,68,68,.3);color:#991b1b}
.notif-toast.info   {background:#fff;border:1.5px solid rgba(8,145,178,.3);color:#0e7490}


/* Loading spinner */
.spin{animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-message-square" style="margin-right:8px;"></i><?php echo __('whatsapp_automation_settings'); ?></h4>
            <p><?php echo __('configure_whatsapp_notifications'); ?></p>
        </div>
        <button type="button" class="test-conn-btn" id="testConnBtn" onclick="testConnection()">
            <i class="feather icon-wifi" id="testConnIcon"></i>
            <span id="testConnText"><?php echo __('test_connection'); ?></span>
        </button>
    </div>

    <!-- Config card -->
    <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico ico-wa"><i class="feather icon-settings"></i></span>
                    <h6><?php echo __('whatsapp_configuration'); ?></h6>
                </div>
            </div>
            <div class="dash-card-body">

                <form id="waSettingsForm">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label"><i class="feather icon-server"></i><?php echo __('provider'); ?></label>
                            <select class="form-input" name="provider">
                                <option value="meta"        <?= ($whatsapp_settings['provider']??'meta')==='meta'?'selected':'' ?>>Meta WhatsApp Business API</option>
                                <option value="twilio"      <?= ($whatsapp_settings['provider']??'')==='twilio'?'selected':'' ?>>Twilio</option>
                                <option value="messagebird" <?= ($whatsapp_settings['provider']??'')==='messagebird'?'selected':'' ?>>MessageBird</option>
                                <option value="360dialog"   <?= ($whatsapp_settings['provider']??'')==='360dialog'?'selected':'' ?>>360Dialog</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="feather icon-toggle-left"></i><?php echo __('status'); ?></label>
                            <select class="form-input" name="status">
                                <option value="active"   <?= ($whatsapp_settings['status']??'')==='active'?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= ($whatsapp_settings['status']??'inactive')==='inactive'?'selected':'' ?>>Inactive</option>
                                <option value="testing"  <?= ($whatsapp_settings['status']??'')==='testing'?'selected':'' ?>>Testing</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-key"></i><?php echo __('api_token'); ?></label>
                        <div class="input-pw-wrap">
                            <input type="password" class="form-input" id="api_token" name="api_token" value="<?= htmlspecialchars($whatsapp_settings['api_token']??'') ?>" placeholder="<?php echo __('enter_api_token'); ?>">
                            <button type="button" class="pw-toggle" onclick="togglePw('api_token',this)"><i class="feather icon-eye"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-phone"></i><?php echo __('phone_number_id'); ?></label>
                        <input type="text" class="form-input" id="phone_number_id" name="phone_number_id" value="<?= htmlspecialchars($whatsapp_settings['phone_number_id']??'') ?>" placeholder="<?php echo __('enter_phone_number_id'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-shield"></i><?php echo __('webhook_verify_token'); ?></label>
                        <input type="text" class="form-input" name="webhook_verify_token" value="<?= htmlspecialchars($whatsapp_settings['webhook_verify_token']??'') ?>" placeholder="<?php echo __('enter_webhook_token'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-link"></i><?php echo __('webhook_url'); ?></label>
                        <input type="text" class="form-input" name="webhook_url" value="<?= htmlspecialchars($whatsapp_settings['webhook_url']??'') ?>" placeholder="<?php echo __('enter_webhook_url'); ?>">
                        <div class="field-hint"><i class="feather icon-info" style="font-size:10px;"></i> <?php echo __('webhook_url_hint'); ?></div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label"><i class="feather icon-clock"></i><?php echo __('max_messages_per_hour'); ?></label>
                            <input type="number" class="form-input" name="max_messages_per_hour" value="<?= $whatsapp_settings['max_messages_per_hour']??1000 ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="feather icon-refresh-cw"></i><?php echo __('retry_attempts'); ?></label>
                            <input type="number" class="form-input" name="retry_attempts" value="<?= $whatsapp_settings['retry_attempts']??3 ?>" min="0" max="10">
                        </div>
                    </div>

                    <!-- Toggles -->
                    <div class="toggle-row">
                        <div class="toggle-label">
                            <i class="feather icon-bell"></i>
                            <div>
                                <?php echo __('enable_auto_notifications'); ?>
                                <span class="toggle-desc">Send automatic booking confirmations</span>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="auto_notifications" <?= ($whatsapp_settings['auto_notifications']??1)?'checked':'' ?>>
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-label">
                            <i class="feather icon-zap"></i>
                            <div>
                                <?php echo __('real_time_notifications'); ?>
                                <span class="toggle-desc">Instant delivery without queue</span>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="real_time_notifications" <?= ($whatsapp_settings['real_time_notifications']??0)?'checked':'' ?>>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="save-btn" id="saveSettingsBtn"><i class="feather icon-save"></i><?php echo __('save_settings'); ?></button>
                </form>
            </div>
        </div>

    <!-- Bottom 2-column row: Test + Queue -->
    <div class="two-col">

        <!-- Test message -->
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico ico-test"><i class="feather icon-send"></i></span>
                    <h6><?php echo __('send_test_message'); ?></h6>
                </div>
            </div>
            <div class="dash-card-body">
                <form id="testMsgForm">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-phone"></i><?php echo __('phone_number'); ?></label>
                        <input type="text" class="form-input" id="test_phone" name="phone_number" placeholder="+1234567890" required>
                        <div class="field-hint"><?php echo __('include_country_code'); ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-message-square"></i><?php echo __('message'); ?></label>
                        <textarea class="form-input" id="test_message" name="message" rows="4" placeholder="<?php echo __('enter_test_message'); ?>"><?php echo __('default_test_message'); ?></textarea>
                    </div>
                    <button type="submit" class="send-btn" id="sendTestBtn">
                        <i class="feather icon-send" id="sendTestIcon"></i>
                        <span id="sendTestText"><?php echo __('send_test_message'); ?></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Queue status -->
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico ico-queue"><i class="feather icon-list"></i></span>
                    <h6><?php echo __('message_queue_status'); ?></h6>
                </div>
                <button class="process-btn" id="processQueueBtn" onclick="processQueue()">
                    <i class="feather icon-refresh-cw" id="processIcon"></i>
                    <span id="processText"><?php echo __('process_queue'); ?></span>
                </button>
            </div>
            <div class="dash-card-body" id="queueStatus">
                <div style="text-align:center;padding:30px;color:var(--text-sub);">
                    <i class="feather icon-loader spin" style="font-size:24px;display:block;margin-bottom:10px;"></i>
                    Loading...
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php include 'footer.php'; ?>

<script>
const CSRF = '<?= htmlspecialchars(CsrfProtection::generateToken(), ENT_QUOTES, 'UTF-8') ?>';

function post(data) {
    data.csrf_token = CSRF;
    return fetch('', { method:'POST', body: new URLSearchParams(data) }).then(r => r.json());
}

function toast(msg, type='success') {
    document.querySelectorAll('.notif-toast').forEach(n => n.remove());
    const t = document.createElement('div');
    t.className = `notif-toast ${type}`;
    const icons = {success:'check-circle', error:'alert-circle', info:'info'};
    t.innerHTML = `<i class="feather icon-${icons[type]||'info'}"></i><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.transition='opacity .4s'; t.style.opacity=0; setTimeout(()=>t.remove(), 400); }, 4000);
}

function togglePw(id, btn) {
    const inp=document.getElementById(id), icon=btn.querySelector('i');
    inp.type = inp.type==='password' ? 'text' : 'password';
    icon.className = inp.type==='text' ? 'feather icon-eye-off' : 'feather icon-eye';
}

function setLoading(btnId, iconId, textId, loading, label='') {
    const btn=document.getElementById(btnId), icon=document.getElementById(iconId), txt=document.getElementById(textId);
    if (!btn) return;
    btn.disabled = loading;
    if (icon) { icon.className = loading ? 'feather icon-loader spin' : icon.dataset.icon || icon.className; }
    if (txt && label) txt.textContent = loading ? label : txt.dataset.orig || txt.textContent;
}

/* â”€â”€ Settings form â”€â”€ */
document.getElementById('waSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action','update_settings'); fd.append('csrf_token',CSRF);
    const btn = document.getElementById('saveSettingsBtn');
    btn.disabled=true; btn.innerHTML='<i class="feather icon-loader spin"></i> Saving...';
    try {
        const r = await fetch('',{method:'POST',body:fd});
        const d = await r.json();
        toast(d.message, d.success?'success':'error');
    } catch(err){ toast('Error saving settings','error'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="feather icon-save"></i><?php echo __("save_settings"); ?>'; }
});

/* â”€â”€ Test connection â”€â”€ */
async function testConnection() {
    const tok = document.getElementById('api_token').value;
    const pid = document.getElementById('phone_number_id').value;
    if (!tok || !pid) { toast('Please fill in API Token and Phone Number ID first','error'); return; }
    const btn=document.getElementById('testConnBtn'), icon=document.getElementById('testConnIcon'), txt=document.getElementById('testConnText');
    btn.disabled=true; icon.className='feather icon-loader spin'; txt.textContent='Testing...';
    try {
        const d = await post({action:'test_connection',api_token:tok,phone_number_id:pid});
        toast(d.message, d.success?'success':'error');
    } catch(err){ toast('Error testing connection','error'); }
    finally { btn.disabled=false; icon.className='feather icon-wifi'; txt.textContent='<?php echo __("test_connection"); ?>'; }
}

/* â”€â”€ Test message form â”€â”€ */
document.getElementById('testMsgForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd=new FormData(this); fd.append('action','send_test_message'); fd.append('csrf_token',CSRF);
    const btn=document.getElementById('sendTestBtn'), icon=document.getElementById('sendTestIcon'), txt=document.getElementById('sendTestText');
    btn.disabled=true; icon.className='feather icon-loader spin'; txt.textContent='Sending...';
    try {
        const r=await fetch('',{method:'POST',body:fd}); const d=await r.json();
        toast(d.message, d.success?'success':'error');
        if(d.success) this.reset();
    } catch(err){ toast('Error sending message','error'); }
    finally { btn.disabled=false; icon.className='feather icon-send'; txt.textContent='<?php echo __("send_test_message"); ?>'; }
});

/* â”€â”€ Queue â”€â”€ */
async function loadQueueStatus() {
    try {
        const d = await post({action:'get_queue_status'});
        const el = document.getElementById('queueStatus');
        if (!d.success || !d.queue_status.length) { el.innerHTML='<p style="color:var(--text-sub);text-align:center;padding:20px 0;">No messages in queue</p>'; return; }
        const classMap = {pending:'q-pending',sent:'q-sent',delivered:'q-delivered',failed:'q-failed',expired:'q-expired'};
        el.innerHTML = d.queue_status.map(s=>`
            <div class="queue-row">
                <span class="q-badge ${classMap[s.status]||'q-expired'}">${s.status}</span>
                <span class="q-count">${s.count}</span>
            </div>`).join('');
    } catch(err){ document.getElementById('queueStatus').innerHTML='<p style="color:var(--red);">Error loading queue</p>'; }
}

async function processQueue() {
    const btn=document.getElementById('processQueueBtn'), icon=document.getElementById('processIcon'), txt=document.getElementById('processText');
    btn.disabled=true; icon.className='feather icon-loader spin'; txt.textContent='Processing...';
    try {
        const d = await post({action:'process_queue'});
        if (d.success) { toast(`Processed ${d.processed} messages${d.failed>0?` (${d.failed} failed)`:''}`, d.failed>0?'info':'success'); loadQueueStatus(); }
        else toast('Error: '+d.message,'error');
    } catch(err){ toast('Error processing queue','error'); }
    finally { btn.disabled=false; icon.className='feather icon-refresh-cw'; txt.textContent='<?php echo __("process_queue"); ?>'; }
}

// Init
document.addEventListener('DOMContentLoaded', loadQueueStatus);
</script>
