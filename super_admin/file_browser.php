<?php
/**
 * ╔══════════════════════════════════════════════════╗
 * ║  SaaS Super Admin — File Browser v3.0           ║
 * ║  Clean light theme · Full security hardening    ║
 * ╚══════════════════════════════════════════════════╝
 *
 * SECURITY:
 *  ✓ CSRF token (hash_equals) on every mutation
 *  ✓ Dual realpath() path traversal guard
 *  ✓ Strict MIME + extension whitelist via finfo
 *  ✓ PHP/executable header scan on upload
 *  ✓ Rate limiting (session-based, per-hour)
 *  ✓ Structured audit log (uid · IP · action · result)
 *  ✓ Security headers (CSP, X-Frame-Options …)
 *  ✓ Null-byte sanitisation in all paths
 *  ✓ Randomised safe filename generation
 *  ✓ 0644 permissions on uploaded files
 *  ✓ Super-admin role gate
 */

// ── Security headers ─────────────────────────────────────────────────
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; frame-src 'self';");

session_start();

// ── Config ────────────────────────────────────────────────────────────
define('UPLOADS_BASE',     dirname(__DIR__) . '/uploads');
define('AUDIT_LOG_PATH',   __DIR__ . '/logs/file_audit.log');
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);
define('RL_UPLOAD_HR',  30);
define('RL_DELETE_HR',  60);
define('RL_RENAME_HR',  60);

// ── Auth guard (replace with your own) ───────────────────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Forbidden']));
}
$UID = (int) $_SESSION['user_id'];
$UIP = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';

// ── CSRF ──────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

function verifyCsrf(string $t): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
}

// ── Rate limiter ──────────────────────────────────────────────────────
function rateAllow(string $action, int $max): bool
{
    $key = "rl_{$action}_" . date('YmdH');
    $_SESSION['rl'][$key] = ($_SESSION['rl'][$key] ?? 0) + 1;
    return $_SESSION['rl'][$key] <= $max;
}

// ── Audit log ─────────────────────────────────────────────────────────
function audit(string $action, string $detail, bool $ok = true): void
{
    global $UID, $UIP;
    $dir = dirname(AUDIT_LOG_PATH);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents(
        AUDIT_LOG_PATH,
        sprintf("[%s] uid=%d ip=%s action=%-14s ok=%s detail=%s\n",
            date('Y-m-d H:i:s'), $UID, $UIP,
            strtoupper($action), $ok ? 'YES' : 'NO', $detail),
        FILE_APPEND | LOCK_EX
    );
}

// ── Allowed types ─────────────────────────────────────────────────────
const ALLOWED_EXT = [
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png'  => 'image/png',
    'gif'  => 'image/gif',  'webp' => 'image/webp',  'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain', 'csv'  => 'text/csv',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip'  => 'application/zip',
    'mp4'  => 'video/mp4',  'webm' => 'video/webm',
    'mp3'  => 'audio/mpeg',
];

function validateFile(array $f): array
{
    if ($f['error'] !== UPLOAD_ERR_OK)       return ['ok'=>false,'msg'=>'Upload error '.$f['error']];
    if ($f['size'] > MAX_UPLOAD_BYTES)       return ['ok'=>false,'msg'=>'Exceeds 50 MB'];
    $name = basename($f['name']);
    if (str_contains($name, "\0"))           return ['ok'=>false,'msg'=>'Invalid filename'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!array_key_exists($ext, ALLOWED_EXT)) return ['ok'=>false,'msg'=>".$ext not permitted"];
    $fi   = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $f['tmp_name']); finfo_close($fi);
    if (ALLOWED_EXT[$ext] !== $mime)         return ['ok'=>false,'msg'=>"MIME mismatch ($mime for .$ext)"];
    $head = file_get_contents($f['tmp_name'], false, null, 0, 1024) ?: '';
    if (preg_match('/<\?(?:php|=)/i', $head)) return ['ok'=>false,'msg'=>'Embedded script detected'];
    return ['ok'=>true];
}

function safeName(string $orig): string
{
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $base = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($orig, PATHINFO_FILENAME)), 0, 60);
    return $base . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
}

// ── Path guard ────────────────────────────────────────────────────────
function safePath(string $sub = ''): string|false
{
    if (!is_dir(UPLOADS_BASE)) @mkdir(UPLOADS_BASE, 0755, true);
    $base = realpath(UPLOADS_BASE);
    if (!$base) return false;
    if ($sub === '') return $base;
    if (str_contains($sub, "\0") || str_contains($sub, '..')) return false;
    $sep  = DIRECTORY_SEPARATOR;
    $cand = $base . $sep . ltrim(str_replace(['\\','/'], $sep, $sub), $sep);
    $real = realpath($cand);
    if ($real !== false) return (str_starts_with($real, $base.$sep) || $real === $base) ? $real : false;
    return str_starts_with($cand, $base.$sep) ? $cand : false;
}

// ── Helpers ───────────────────────────────────────────────────────────
function fmtSz(int $b): string
{
    foreach (['B','KB','MB','GB'] as $u) {
        if ($b < 1024) return round($b,1).' '.$u;
        $b /= 1024;
    }
    return round($b,1).' TB';
}

function fi2arr(string $full, string $rel, bool $isDir): array
{
    return [
        'name'     => basename($full), 'path' => $rel, 'full_path' => $full,
        'size'     => $isDir ? 0 : (int)@filesize($full),
        'type'     => $isDir ? 'directory' : (@mime_content_type($full) ?: 'application/octet-stream'),
        'modified' => (int)@filemtime($full), 'is_dir' => $isDir,
    ];
}

function lsDir(string $dir, string $relBase): array
{
    if (!is_dir($dir)) return [];
    $items = [];
    foreach (array_diff(scandir($dir), ['.','..']) as $n) {
        $full = $dir.DIRECTORY_SEPARATOR.$n;
        $items[] = fi2arr($full, $relBase ? "$relBase/$n" : $n, is_dir($full));
    }
    usort($items, fn($a,$b) => $a['is_dir']===$b['is_dir']
        ? strnatcasecmp($a['name'],$b['name'])
        : ($a['is_dir'] ? -1 : 1));
    return $items;
}

function search(string $base, string $q): array
{
    $out = [];
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            if (stripos($f->getFilename(), $q) !== false) {
                $full = (string)$f;
                $rel  = ltrim(str_replace([$base,'\\'],['','/'],$full),'/');
                $out[] = fi2arr($full, $rel, $f->isDir());
            }
        }
    } catch (Exception) {}
    return $out;
}

function rmTree(string $p): bool
{
    if (is_file($p)) return @unlink($p);
    if (!is_dir($p))  return false;
    foreach (array_diff(scandir($p),['.','..']) as $f) rmTree($p.DIRECTORY_SEPARATOR.$f);
    return @rmdir($p);
}

function fileUrl(string $full): string
{
    $f = str_replace('\\','/',$full);
    return preg_match('#/uploads/(.+)$#', $f, $m) ? '../uploads/'.$m[1] : '#';
}

function tc(string $mime): string
{
    return match(true) {
        $mime==='directory'                                          => 'folder',
        str_starts_with($mime,'image/')                             => 'image',
        $mime==='application/pdf'                                   => 'pdf',
        str_starts_with($mime,'text/')                              => 'text',
        str_contains($mime,'spreadsheet')||str_contains($mime,'excel') => 'sheet',
        str_contains($mime,'document')||str_contains($mime,'word')     => 'doc',
        str_contains($mime,'presentation')||str_contains($mime,'powerpoint') => 'ppt',
        str_contains($mime,'zip')||str_contains($mime,'compressed') => 'zip',
        str_starts_with($mime,'video/')                             => 'video',
        str_starts_with($mime,'audio/')                             => 'audio',
        default                                                     => 'other',
    };
}
function ti(string $t): string { return match($t){'folder'=>'folder','image'=>'image','pdf'=>'file-text','text'=>'file-text','sheet'=>'grid','doc'=>'file','ppt'=>'monitor','zip'=>'package','video'=>'film','audio'=>'music',default=>'file'}; }
function tl(string $t): string { return match($t){'folder'=>'Folder','image'=>'Image','pdf'=>'PDF','text'=>'Text','sheet'=>'Sheet','doc'=>'Doc','ppt'=>'Slide','zip'=>'ZIP','video'=>'Video','audio'=>'Audio',default=>'File'}; }

// Ensure uploads dir
if (!is_dir(UPLOADS_BASE)) @mkdir(UPLOADS_BASE, 0755, true);

// ── Input ─────────────────────────────────────────────────────────────
$sq = mb_substr(strip_tags(trim($_GET['search']??'')),0,100);
$cf = trim(preg_replace('/[^a-zA-Z0-9_\-\/.]/','',trim($_GET['folder']??'')),'/');
$fi = trim(preg_replace('/[^a-zA-Z0-9_\-]/','',trim($_GET['filter']??'')));
$cd = safePath($cf);
if (!$cd) { $cd = safePath(); $cf = ''; }

// ── AJAX handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    $resp = ['success'=>false,'message'=>''];
    $tok  = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($tok)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'CSRF failed']); exit; }
    $action = $_POST['action'] ?? '';

    if ($action==='upload_file') {
        if (!rateAllow('upload',RL_UPLOAD_HR)) { $resp['message']='Rate limit. Try again later.'; echo json_encode($resp); exit; }
        $ok=0; $errs=[];
        for ($i=0;$i<count($_FILES['files']['name']??[]);$i++) {
            $f=['name'=>$_FILES['files']['name'][$i],'tmp_name'=>$_FILES['files']['tmp_name'][$i],
                'size'=>$_FILES['files']['size'][$i],'error'=>$_FILES['files']['error'][$i]];
            $v=validateFile($f);
            if (!$v['ok']) { $errs[]=basename($f['name']).': '.$v['msg']; audit('upload_reject',$f['name'],false); continue; }
            $s=safeName($f['name']); $t=$cd.DIRECTORY_SEPARATOR.$s;
            if (move_uploaded_file($f['tmp_name'],$t)) { @chmod($t,0644); audit('upload',"{$f['name']} → $s"); $ok++; }
            else $errs[]=basename($f['name']).': write failed';
        }
        $resp=['success'=>$ok>0,'message'=>"$ok file(s) uploaded.".($errs?' Errors: '.implode('; ',$errs):'')];
        echo json_encode($resp); exit;
    }

    if ($action==='create_folder') {
        $n=preg_replace('/[^a-zA-Z0-9_\-]/','',trim($_POST['folder_name']??''));
        if (!$n) { $resp['message']='Invalid name'; echo json_encode($resp); exit; }
        $sub=$cf?"$cf/$n":$n; $t=safePath($sub);
        if (!$t) { $resp['message']='Invalid path'; echo json_encode($resp); exit; }
        if (is_dir($t)) { $resp['message']='Already exists'; echo json_encode($resp); exit; }
        if (@mkdir($t, 0755, true)) {
            $resp = ['success' => true, 'message' => "Folder \"$n\" created"];
            audit('mkdir', $sub);
        } else {
            $resp['message'] = 'Failed to create';
        }
        echo json_encode($resp); exit;
    }

    if ($action==='delete_item') {
        if (!rateAllow('delete',RL_DELETE_HR)) { $resp['message']='Rate limit'; echo json_encode($resp); exit; }
        $sub=trim($_POST['item_path']??''); $full=safePath($sub);
        if (!$full||(!file_exists($full)&&!is_dir($full))) { $resp['message']='Not found'; audit('delete',$sub,false); echo json_encode($resp); exit; }
        if (rmTree($full)) {
            $resp = ['success' => true, 'message' => 'Deleted'];
            audit('delete', $sub);
        } else {
            $resp['message'] = 'Delete failed';
            audit('delete', $sub, false);
        }
        echo json_encode($resp); exit;
    }

    if ($action==='rename_item') {
        if (!rateAllow('rename',RL_RENAME_HR)) { $resp['message']='Rate limit'; echo json_encode($resp); exit; }
        $old=trim($_POST['old_path']??''); $nn=preg_replace('/[^a-zA-Z0-9_\-\.]/','_',trim($_POST['new_name']??''));
        $of=safePath($old);
        if (!$of||!file_exists($of)) { $resp['message']='Not found'; echo json_encode($resp); exit; }
        $nf=dirname($of).DIRECTORY_SEPARATOR.$nn;
        $b=safePath(); if (!$b||!str_starts_with(realpath(dirname($of))?:'',$b)) { $resp['message']='Invalid target'; echo json_encode($resp); exit; }
        if (@rename($of, $nf)) {
            $resp = ['success' => true, 'message' => 'Renamed'];
            audit('rename', "$old → $nn");
        } else {
            $resp['message'] = 'Rename failed';
        }
        echo json_encode($resp); exit;
    }

    http_response_code(400); echo json_encode(['success'=>false,'message'=>'Unknown action']); exit;
}

// ── Audit log endpoint ────────────────────────────────────────────────
if (isset($_GET['__audit'])) {
    echo is_file(AUDIT_LOG_PATH) ? htmlspecialchars(file_get_contents(AUDIT_LOG_PATH)?:'') : '';
    exit;
}

// ── Directory listing ─────────────────────────────────────────────────
$files    = $sq ? search($cd,$sq) : lsDir($cd,$cf);

// ── Apply filter if specified ──────────────────────────────────────────
if ($fi) {
    $files = array_filter($files, function($f) use ($fi) {
        if ($f['is_dir']) return true; // Always show folders
        
        $type = tc($f['type']);
        switch ($fi) {
            case 'images':
                return in_array($type, ['image'], true);
            case 'documents':
                return in_array($type, ['pdf', 'doc', 'text'], true);
            case 'archives':
                return in_array($type, ['zip'], true);
            default:
                return true;
        }
    });
}

$nFiles   = count(array_filter($files,fn($f)=>!$f['is_dir']));
$nFolders = count(array_filter($files,fn($f)=> $f['is_dir']));
$totSz    = array_sum(array_column($files,'size'));

function bcrumb(string $f): string
{
    $h='<li class="bc-item"><a href="?" class="bc-link">Uploads</a></li>';
    $p='';
    foreach (array_filter(explode('/',$f)) as $part) {
        $p.='/'.$part;
        $h.='<li class="bc-sep"><svg data-feather="chevron-right"></svg></li>';
        $h.='<li class="bc-item"><a href="?folder='.urlencode(trim($p,'/')).'" class="bc-link">'.htmlspecialchars($part).'</a></li>';
    }
    return $h;
}
include '../includes/header_super_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>File Manager — Super Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@1&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ════════════════════════════════════════════════════
   DESIGN: Editorial-minimal SaaS admin
   Palette: warm white · ink · precise accent
   Fonts: Instrument Sans + DM Mono
════════════════════════════════════════════════════ */
:root {
  --w:    #ffffff;
  --s0:   #faf9f8;
  --s1:   #f3f2f0;
  --s2:   #eceae7;
  --bd:   #e2dfdb;
  --bd2:  #ccc9c3;
  --i0:   #181715;
  --i1:   #3a3834;
  --i2:   #6a6760;
  --i3:   #a09d96;
  --i4:   #c8c5be;
  --ac:   #2355c7;
  --ac-l: #edf1fc;
  --ac-m: #bfcdf5;
  --ac-d: #1a44b0;
  --gn:   #176b41;
  --gn-l: #e7f5ee;
  --rd:   #c13535;
  --rd-l: #fdf0f0;
  --am:   #b35008;
  --am-l: #fef4e6;
  --cf:   #b96a0a; /* folder  */
  --ci:   #0885a8; /* image   */
  --cp:   #be2e2e; /* pdf     */
  --ct:   #5356c2; /* text    */
  --cs:   #176b41; /* sheet   */
  --cd:   #2355c7; /* doc     */
  --cz:   #6444ae; /* zip     */
  --cv:   #0e7490; /* video   */
  --ca:   #6d2eac; /* audio   */
  --r:    6px;
  --rl:   10px;
  --rxl:  14px;
  --xs:   0 1px 3px rgba(24,23,21,.06),0 1px 2px rgba(24,23,21,.04);
  --sm:   0 2px 8px rgba(24,23,21,.08),0 1px 3px rgba(24,23,21,.04);
  --md:   0 8px 24px rgba(24,23,21,.10),0 2px 6px rgba(24,23,21,.05);
  --lg:   0 20px 56px rgba(24,23,21,.14),0 4px 14px rgba(24,23,21,.06);
  --foc:  0 0 0 3px var(--ac-m);
  --t:    .14s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--s0);color:var(--i0);font-family:'Instrument Sans',sans-serif;font-size:14px;line-height:1.55;-webkit-font-smoothing:antialiased}
::selection{background:var(--ac-l);color:var(--ac-d)}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:99px}

/* ── Layout ── */
.app{display:flex;flex-direction:column;height:100vh;overflow:hidden}
.body{display:flex;flex:1;overflow:hidden}

/* ── Topbar ── */
.top{flex-shrink:0;display:flex;align-items:center;gap:16px;height:54px;padding:0 20px;background:var(--w);border-bottom:1px solid var(--bd);position:relative;z-index:50}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none;padding-right:20px;border-right:1px solid var(--bd);margin-right:4px}
.brand-title{font-family:'Instrument Serif',serif;font-style:italic;font-size:19px;color:var(--i0);letter-spacing:-.3px}
.brand-pill{font-family:'DM Mono',monospace;font-size:9.5px;font-weight:500;padding:2px 7px;border-radius:20px;background:var(--i0);color:var(--w);letter-spacing:.6px;text-transform:uppercase}
.srch{flex:1;max-width:320px;position:relative;display:flex;align-items:center}
.srch input{width:100%;height:33px;background:var(--s1);border:1px solid var(--bd);border-radius:var(--rl);font-family:'DM Mono',monospace;font-size:12.5px;color:var(--i0);padding:0 34px 0 11px;outline:none;transition:var(--t)}
.srch input::placeholder{color:var(--i3)}
.srch input:focus{background:var(--w);border-color:var(--ac);box-shadow:var(--foc)}
.srch .si{position:absolute;right:10px;color:var(--i3);pointer-events:none;display:flex;align-items:center}
.srch .si svg{width:13px;height:13px}
.topgap{flex:1}
.topact{display:flex;align-items:center;gap:6px}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:5px;padding:0 13px;height:32px;border-radius:var(--r);font-family:'Instrument Sans',sans-serif;font-size:13px;font-weight:600;border:1px solid transparent;cursor:pointer;white-space:nowrap;text-decoration:none;transition:var(--t);letter-spacing:-.1px}
.btn svg{width:13px;height:13px;flex-shrink:0}
.btn-sm{height:28px;padding:0 10px;font-size:12px}
.btn-sm svg{width:12px;height:12px}
.btn-icon{width:32px;padding:0;justify-content:center}
.btn-icon.btn-sm{width:28px}
.btn:disabled{opacity:.4;cursor:not-allowed}
.btn-p{background:var(--ac);color:#fff;border-color:var(--ac)}
.btn-p:hover:not(:disabled){background:var(--ac-d);border-color:var(--ac-d);box-shadow:var(--sm)}
.btn-o{background:var(--w);color:var(--i1);border-color:var(--bd)}
.btn-o:hover:not(:disabled){background:var(--s1);border-color:var(--bd2)}
.btn-g{background:transparent;color:var(--i2);border-color:transparent}
.btn-g:hover:not(:disabled){background:var(--s2);color:var(--i0)}
.btn-d{background:transparent;color:var(--rd);border-color:transparent}
.btn-d:hover:not(:disabled){background:var(--rd-l);border-color:rgba(193,53,53,.2)}
.btn-ds{background:var(--rd);color:#fff;border-color:var(--rd)}
.btn-ds:hover:not(:disabled){background:#a82e2e}

/* ── Sidebar ── */
.side{width:196px;min-width:196px;flex-shrink:0;background:var(--w);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;padding:14px 0}
.sb-sect{padding:10px 14px 3px}
.sb-lbl{font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:1.2px;color:var(--i3)}
.sbi{display:flex;align-items:center;gap:8px;padding:7px 14px;color:var(--i1);font-size:13px;font-weight:500;cursor:pointer;transition:var(--t);text-decoration:none;border:none;background:none;width:100%;position:relative}
.sbi svg{width:14px;height:14px;flex-shrink:0;color:var(--i3);transition:var(--t)}
.sbi:hover{background:var(--s1);color:var(--i0)}
.sbi:hover svg{color:var(--i1)}
.sbi.active{background:var(--ac-l);color:var(--ac-d);font-weight:600}
.sbi.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--ac);border-radius:0 2px 2px 0}
.sbi.active svg{color:var(--ac)}
.sbi .sc{margin-left:auto;font-family:'DM Mono',monospace;font-size:11px;color:var(--i3)}
.sb-div{height:1px;background:var(--bd);margin:8px 14px}
.sb-stats{padding:12px 14px;display:flex;flex-direction:column;gap:6px;margin-top:auto;border-top:1px solid var(--bd)}
.sb-st{display:flex;justify-content:space-between;font-size:12px}
.sb-st .l{color:var(--i3)}.sb-st .v{color:var(--i0);font-family:'DM Mono',monospace;font-weight:500;font-size:11px}

/* ── Content ── */
.cont{flex:1;display:flex;flex-direction:column;overflow:hidden}

/* Toolbar */
.toolbar{flex-shrink:0;display:flex;align-items:center;gap:10px;padding:10px 20px;background:var(--w);border-bottom:1px solid var(--bd)}
.bcs{display:flex;align-items:center;flex:1;list-style:none;min-width:0;overflow:hidden;gap:0}
.bc-item{white-space:nowrap}
.bc-link{font-size:13px;font-weight:500;color:var(--i2);text-decoration:none;transition:var(--t);padding:2px 4px;border-radius:4px}
.bc-link:hover{color:var(--i0);background:var(--s2)}
.bcs li:last-child .bc-link{color:var(--i0);font-weight:700}
.bc-sep{display:flex;align-items:center;color:var(--i4);padding:0 1px}
.bc-sep svg{width:13px;height:13px}
.tb-sep{width:1px;height:20px;background:var(--bd);flex-shrink:0}
.vt{display:flex;background:var(--s1);border-radius:var(--r);padding:2px;border:1px solid var(--bd);gap:1px}
.vt button{width:27px;height:24px;display:flex;align-items:center;justify-content:center;background:none;border:none;border-radius:4px;cursor:pointer;color:var(--i3);transition:var(--t)}
.vt button svg{width:13px;height:13px}
.vt button:hover{color:var(--i1)}
.vt button.on{background:var(--w);color:var(--i0);box-shadow:var(--xs)}

/* Stats row */
.stats{flex-shrink:0;display:flex;align-items:center;gap:20px;padding:6px 20px;background:var(--s0);border-bottom:1px solid var(--bd);font-family:'DM Mono',monospace;font-size:11.5px;color:var(--i2)}
.sp strong{color:var(--i0);font-weight:600}

/* File area */
.fa{flex:1;overflow-y:auto;padding:18px 20px}

/* ── Grid ── */
.fg{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:10px}

/* ── List ── */
.fl2{display:flex;flex-direction:column}
.lh{display:none;align-items:center;gap:12px;padding:5px 12px;font-family:'DM Mono',monospace;font-size:10.5px;font-weight:500;text-transform:uppercase;letter-spacing:.8px;color:var(--i3);border-bottom:1px solid var(--bd);margin-bottom:2px}
.lh .ln{flex:1}.lh .lty{width:64px;text-align:center}.lh .lsz{width:72px;text-align:right}.lh .ldt{width:132px;text-align:right}.lh .la{width:96px}

/* ── File card ── */
.fc{background:var(--w);border:1px solid var(--bd);border-radius:var(--rl);padding:14px 10px 10px;display:flex;flex-direction:column;align-items:center;gap:7px;text-align:center;cursor:pointer;position:relative;outline:none;transition:var(--t);user-select:none}
.fc:hover{border-color:var(--bd2);box-shadow:var(--sm);transform:translateY(-1px)}
.fc.sel{border-color:var(--ac);background:var(--ac-l);box-shadow:0 0 0 2px var(--ac-m)}
.fc-ck{position:absolute;top:8px;left:8px;width:18px;height:18px;border-radius:4px;background:var(--w);border:1.5px solid var(--bd2);display:flex;align-items:center;justify-content:center;opacity:0;transition:var(--t)}
.fc:hover .fc-ck{opacity:1}
.fc.sel .fc-ck{opacity:1;background:var(--ac);border-color:var(--ac)}
.fc-ck svg{width:10px;height:10px;color:var(--w)}
.th{width:52px;height:52px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.th img{width:100%;height:100%;object-fit:cover;border-radius:10px}
.th svg{width:22px;height:22px}
.tf{background:#fef3e2;color:var(--cf)}.ti2{background:#e0f4f8;color:var(--ci)}.tp{background:#fde8e8;color:var(--cp)}.tt{background:#eeeeff;color:var(--ct)}.ts{background:#e7f6ee;color:var(--cs)}.tdc{background:var(--ac-l);color:var(--cd)}.tpp{background:#fef3e2;color:var(--am)}.tz{background:#f0ebff;color:var(--cz)}.tv{background:#e0f6fa;color:var(--cv)}.tau{background:#f3ebff;color:var(--ca)}.to{background:var(--s2);color:var(--i3)}
.fc-n{font-size:12px;font-weight:600;color:var(--i0);width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:'DM Mono',monospace;letter-spacing:-.3px}
.fc-m{font-size:11px;color:var(--i3)}
.fc-a{display:flex;gap:3px;opacity:0;transition:var(--t);margin-top:2px}
.fc:hover .fc-a{opacity:1}

/* ── File row ── */
.fr{display:flex;align-items:center;gap:12px;padding:7px 12px;border-radius:var(--r);cursor:pointer;transition:var(--t);border:1px solid transparent;user-select:none}
.fr:hover{background:var(--w);border-color:var(--bd);box-shadow:var(--xs)}
.fr.sel{background:var(--ac-l);border-color:var(--ac-m)}
.fr .th{width:32px;height:32px;border-radius:7px}
.fr .th svg{width:15px;height:15px}
.fr .rn{flex:1;font-size:13px;font-weight:600;color:var(--i0);font-family:'DM Mono',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fr .rty{width:64px;text-align:center}
.fr .rsz{width:72px;font-size:11px;color:var(--i3);font-family:'DM Mono',monospace;text-align:right}
.fr .rdt{width:132px;font-size:11px;color:var(--i2);font-family:'DM Mono',monospace;text-align:right}
.fr .ra{width:96px;display:flex;gap:3px;justify-content:flex-end;opacity:0;transition:var(--t)}
.fr:hover .ra{opacity:1}

/* Badge */
.bdg{display:inline-block;padding:1px 7px;border-radius:20px;font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.5px}
.bf{background:#fef3e2;color:var(--cf)}.bi{background:#e0f4f8;color:var(--ci)}.bpdf{background:#fde8e8;color:var(--cp)}.bt{background:#eeeeff;color:var(--ct)}.bs{background:#e7f6ee;color:var(--cs)}.bd2{background:var(--ac-l);color:var(--cd)}.bpp{background:#fef3e2;color:var(--am)}.bz{background:#f0ebff;color:var(--cz)}.bv{background:#e0f6fa;color:var(--cv)}.ba{background:#f3ebff;color:var(--ca)}.bo{background:var(--s2);color:var(--i3)}

/* Empty */
.emp{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;height:340px;text-align:center}
.emp .ei{width:64px;height:64px;border-radius:18px;background:var(--s2);display:flex;align-items:center;justify-content:center}
.emp .ei svg{width:28px;height:28px;color:var(--i3)}
.emp h3{font-size:16px;font-weight:700;color:var(--i0)}
.emp p{font-size:13px;color:var(--i2);max-width:280px;line-height:1.6}

/* Modals */
.ov{position:fixed;inset:0;background:rgba(24,23,21,.42);z-index:999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(3px);padding:20px;animation:of .15s ease}
@keyframes of{from{opacity:0}to{opacity:1}}
@keyframes mu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.ov.open{display:flex}
.modal{background:var(--w);border:1px solid var(--bd);border-radius:var(--rxl);box-shadow:var(--lg);width:440px;max-width:100%;max-height:90vh;display:flex;flex-direction:column;animation:mu .18s cubic-bezier(.4,0,.2,1)}
.modal-lg{width:680px}
.modal-xl{width:min(92vw,1000px);height:min(88vh,740px)}
.mh{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--bd)}
.mh h2{font-size:15px;font-weight:700;color:var(--i0)}
.mb{padding:20px;overflow-y:auto;flex:1}
.mf{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--bd);flex-shrink:0}

/* Form */
.fg2{margin-bottom:14px}
.fl2x{display:block;font-size:11.5px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:var(--i2);margin-bottom:5px}
.fi{width:100%;height:38px;background:var(--w);border:1px solid var(--bd);border-radius:var(--r);color:var(--i0);font-family:'DM Mono',monospace;font-size:13px;padding:0 12px;outline:none;transition:var(--t)}
.fi:focus{border-color:var(--ac);box-shadow:var(--foc)}
.fh{font-family:'DM Mono',monospace;font-size:11px;color:var(--i3);margin-top:4px}

/* Dropzone */
.dz{border:1.5px dashed var(--bd2);border-radius:var(--rl);padding:30px 24px;text-align:center;cursor:pointer;transition:var(--t);background:var(--s1);display:flex;flex-direction:column;align-items:center;gap:8px}
.dz svg{width:34px;height:34px;color:var(--i3);transition:var(--t)}
.dz:hover,.dz.drag{border-color:var(--ac);background:var(--ac-l)}
.dz:hover svg,.dz.drag svg{color:var(--ac)}
.dz p{font-size:13px;color:var(--i1);font-weight:500}
.dz strong{color:var(--ac)}
.dz small{font-size:11px;color:var(--i3);font-family:'DM Mono',monospace}
.ufl{margin-top:10px;display:flex;flex-direction:column;gap:3px;max-height:150px;overflow-y:auto}
.ufi{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:var(--s1);border-radius:var(--r);border:1px solid var(--bd);font-size:12px;font-family:'DM Mono',monospace}
.ufi .nm{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--i0)}
.ufi .sz{color:var(--i3);margin:0 8px;white-space:nowrap}
.ufi.bad{border-color:rgba(193,53,53,.3);background:var(--rd-l)}
.sok{color:var(--gn);font-weight:600;font-size:11px}.serr{color:var(--rd);font-weight:600;font-size:11px}
.up{margin-top:12px}
.uprow{display:flex;justify-content:space-between;font-size:12px;color:var(--i2);margin-bottom:6px;font-family:'DM Mono',monospace}
.uptrk{height:5px;background:var(--s2);border-radius:99px;overflow:hidden}
.upbar{height:100%;background:var(--ac);border-radius:99px;transition:width .2s ease}
.upbar.done{background:var(--gn)}.upbar.err2{background:var(--rd)}
.upi{font-size:11px;color:var(--i3);margin-top:4px;font-family:'DM Mono',monospace}

/* Confirm */
.ci2{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.ci2.warn{background:var(--rd-l);color:var(--rd)}
.ci2 svg{width:22px;height:22px}
.ct2{font-size:15px;font-weight:700;text-align:center;color:var(--i0);margin-bottom:6px}
.cb2{font-size:13px;color:var(--i2);text-align:center;line-height:1.6}

/* Preview */
.pvb{flex:1;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--s1);position:relative}
.pvb img{max-width:100%;max-height:100%;object-fit:contain;border-radius:var(--r);box-shadow:var(--md)}
.pvb iframe{width:100%;height:100%;border:none}
.pvb pre{width:100%;height:100%;padding:20px;overflow:auto;font-family:'DM Mono',monospace;font-size:12.5px;color:var(--i1);line-height:1.7;background:var(--w)}
.pvb video{max-width:100%;max-height:100%;border-radius:var(--r)}
.pvb audio{width:360px}
.pvn{text-align:center;color:var(--i3);padding:40px}
.pvn svg{width:40px;height:40px;margin:0 auto 12px;opacity:.4;display:block}

/* Bulk bar */
.bb{position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(8px);background:var(--i0);border-radius:40px;padding:8px 18px;display:flex;align-items:center;gap:10px;box-shadow:var(--lg);z-index:300;opacity:0;pointer-events:none;transition:opacity var(--t),transform var(--t)}
.bb.show{opacity:1;pointer-events:all;transform:translateX(-50%) translateY(0)}
.bb .bcn{font-size:13px;font-weight:700;color:#fff;font-family:'DM Mono',monospace;white-space:nowrap;margin-right:4px}
.bb .bbd{width:1px;height:18px;background:rgba(255,255,255,.15)}
.bb .btn{height:29px;font-size:12px}
.bb .btn-o{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15);color:#fff}
.bb .btn-o:hover{background:rgba(255,255,255,.18)}
.bb .btn-g{color:rgba(255,255,255,.7)}
.bb .btn-g:hover{background:rgba(255,255,255,.1);color:#fff}
.bb .btn-d{color:#fca5a5}
.bb .btn-d:hover{background:rgba(248,113,113,.15);border-color:rgba(248,113,113,.3)}

/* Toasts */
.tsts{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.tst{display:flex;align-items:flex-start;gap:10px;padding:11px 14px;border-radius:var(--rl);background:var(--w);border:1px solid var(--bd);box-shadow:var(--md);font-size:13px;max-width:320px;word-break:break-word;animation:mu .2s ease;pointer-events:all}
.tst svg{width:15px;height:15px;flex-shrink:0;margin-top:1px}
.tst .tm{flex:1;color:var(--i0)}
.tok{border-color:rgba(23,107,65,.2)}.tok svg{color:var(--gn)}
.terr{border-color:rgba(193,53,53,.2)}.terr svg{color:var(--rd)}
.tinf svg{color:var(--ac)}

/* Audit */
.aud{width:100%;border-collapse:collapse;font-family:'DM Mono',monospace;font-size:11.5px}
.aud th{padding:6px 10px;text-align:left;background:var(--s1);border-bottom:1px solid var(--bd);color:var(--i2);font-weight:600;text-transform:uppercase;letter-spacing:.5px;font-size:10px}
.aud td{padding:6px 10px;border-bottom:1px solid var(--bd);color:var(--i1)}
.aud tr:hover td{background:var(--s0)}
.aok{color:var(--gn);font-weight:700}.afa{color:var(--rd);font-weight:700}

kbd{display:inline-block;padding:2px 7px;border-radius:4px;font-family:'DM Mono',monospace;font-size:11px;background:var(--s2);border:1px solid var(--bd2);color:var(--i1);line-height:1.5}
.spin{display:flex;align-items:center;justify-content:center;padding:40px}
.spin::after{content:'';width:22px;height:22px;border-radius:50%;border:2px solid var(--bd);border-top-color:var(--ac);animation:rot .65s linear infinite}
@keyframes rot{to{transform:rotate(360deg)}}
@media(max-width:860px){.side{display:none}.lh .ldt,.fr .rdt{display:none}}
@media(max-width:560px){.srch{display:none}}
</style>
</head>
<body>
<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
<div class="app">

<!-- TOP BAR -->
<header class="top">
  <a class="brand" href="?">
    <span class="brand-title">Files</span>
    <span class="brand-pill">Super Admin</span>
  </a>
  <form class="srch" action="" method="GET">
    <?php if ($cf): ?><input type="hidden" name="folder" value="<?= htmlspecialchars($cf) ?>"><?php endif; ?>
    <input type="text" name="search" placeholder="Search files and folders…" value="<?= htmlspecialchars($sq) ?>" autocomplete="off">
    <span class="si"><svg data-feather="search"></svg></span>
  </form>
  <div class="topgap"></div>
  <div class="topact">
    <button class="btn btn-g btn-icon" title="Audit log" onclick="openAudit()"><svg data-feather="shield"></svg></button>
    <button class="btn btn-g btn-icon" title="Keyboard shortcuts" onclick="Modal.open('kbModal')"><svg data-feather="help-circle"></svg></button>
  </div>
</header>

<div class="body">
<!-- SIDEBAR -->
<aside class="side">
  <div class="sb-sect"><span class="sb-lbl">Browse</span></div>
  <a class="sbi <?= !$sq&&!$cf&&!$fi?'active':'' ?>" href="<?= $cf?'?folder='.urlencode($cf):'?' ?>"><svg data-feather="hard-drive"></svg>All Files<span class="sc"><?= $nFiles+$nFolders ?></span></a>
  <a class="sbi <?= $fi==='images'?'active':'' ?>" href="?filter=images<?= $cf?'&folder='.urlencode($cf):'' ?>"><svg data-feather="image"></svg>Images</a>
  <a class="sbi <?= $fi==='documents'?'active':'' ?>" href="?filter=documents<?= $cf?'&folder='.urlencode($cf):'' ?>"><svg data-feather="file-text"></svg>Documents</a>
  <a class="sbi <?= $fi==='archives'?'active':'' ?>" href="?filter=archives<?= $cf?'&folder='.urlencode($cf):'' ?>"><svg data-feather="package"></svg>Archives</a>
  <div class="sb-div"></div>
  <div class="sb-sect"><span class="sb-lbl">Actions</span></div>
  <button class="sbi" onclick="Modal.open('upMod')"><svg data-feather="upload-cloud"></svg>Upload Files</button>
  <button class="sbi" onclick="Modal.open('mkMod')"><svg data-feather="folder-plus"></svg>New Folder</button>
  <?php if ($cf): ?>
  <a class="sbi" href="?folder=<?= urlencode(ltrim(dirname('/'.$cf),'/')) ?>"><svg data-feather="arrow-left"></svg>Go Up</a>
  <?php endif; ?>
  <div class="sb-stats">
    <div class="sb-st"><span class="l">Files</span><span class="v"><?= $nFiles ?></span></div>
    <div class="sb-st"><span class="l">Folders</span><span class="v"><?= $nFolders ?></span></div>
    <div class="sb-st"><span class="l">Total size</span><span class="v"><?= fmtSz($totSz) ?></span></div>
  </div>
</aside>

<!-- CONTENT -->
<main class="cont">
  <!-- Toolbar -->
  <div class="toolbar">
    <ol class="bcs">
      <?php if (!$sq): echo bcrumb($cf); if (!$cf): ?><li class="bc-item"><span class="bc-link" style="color:var(--i0);font-weight:700">root</span></li><?php endif; ?>
      <?php else: ?>
        <li class="bc-item"><a href="?" class="bc-link">Uploads</a></li>
        <li class="bc-sep"><svg data-feather="chevron-right"></svg></li>
        <li class="bc-item"><span class="bc-link" style="color:var(--i0);font-weight:700">Search: "<?= htmlspecialchars($sq) ?>"</span></li>
      <?php endif; ?>
    </ol>
    <div class="tb-sep"></div>
    <button class="btn btn-p btn-sm" onclick="Modal.open('upMod')"><svg data-feather="upload-cloud"></svg>Upload</button>
    <button class="btn btn-o btn-sm" onclick="Modal.open('mkMod')"><svg data-feather="folder-plus"></svg>New Folder</button>
    <div class="tb-sep"></div>
    <div class="vt" id="vt">
      <button data-v="grid" class="on" title="Grid"><svg data-feather="grid"></svg></button>
      <button data-v="list" title="List"><svg data-feather="list"></svg></button>
    </div>
  </div>

  <!-- Stats row -->
  <div class="stats">
    <span class="sp"><strong><?= count($files) ?></strong> items</span>
    <span class="sp"><strong><?= $nFiles ?></strong> files</span>
    <span class="sp"><strong><?= $nFolders ?></strong> folders</span>
    <span class="sp"><strong><?= fmtSz($totSz) ?></strong> total</span>
    <?php if ($sq): ?><span style="margin-left:auto;font-size:11px">Results for "<strong><?= htmlspecialchars($sq) ?></strong>" &ensp;<a href="?" style="color:var(--ac);text-decoration:none;font-weight:600">× clear</a></span><?php endif; ?>
  </div>

  <!-- File area -->
  <div class="fa" id="fa">
    <?php if (empty($files)): ?>
      <div class="emp">
        <div class="ei"><svg data-feather="folder-open"></svg></div>
        <h3><?= $sq ? 'No results' : 'Empty directory' ?></h3>
        <p><?= $sq ? 'No items matched "'.htmlspecialchars($sq).'".' : 'Upload files or create a folder to get started.' ?></p>
        <?php if (!$sq): ?><button class="btn btn-p" onclick="Modal.open('upMod')"><svg data-feather="upload-cloud"></svg>Upload Files</button><?php else: ?><a href="?" class="btn btn-o">Clear search</a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="lh" id="lh">
        <div style="width:32px;margin-right:12px"></div>
        <div class="ln">Name</div><div class="lty">Type</div>
        <div class="lsz">Size</div><div class="ldt">Modified</div><div class="la"></div>
      </div>
      <div class="fg" id="fgrid">
        <?php foreach ($files as $f):
          $isDir=$f['is_dir']; $t=tc($f['type']); $icon=ti($t); $lbl=tl($t);
          $url=$isDir?'?folder='.urlencode($f['path']):fileUrl($f['full_path']);
          $isImg=str_starts_with($f['type'],'image/');
          $thcls=match($t){'folder'=>'tf','image'=>'ti2','pdf'=>'tp','text'=>'tt','sheet'=>'ts','doc'=>'tdc','ppt'=>'tpp','zip'=>'tz','video'=>'tv','audio'=>'tau',default=>'to'};
          $bdgcls=match($t){'folder'=>'bf','image'=>'bi','pdf'=>'bpdf','text'=>'bt','sheet'=>'bs','doc'=>'bd2','ppt'=>'bpp','zip'=>'bz','video'=>'bv','audio'=>'ba',default=>'bo'};
        ?>
        <div class="fc"
             data-path="<?= htmlspecialchars($f['path']) ?>"
             data-name="<?= htmlspecialchars($f['name']) ?>"
             data-type="<?= htmlspecialchars($f['type']) ?>"
             data-url="<?= htmlspecialchars($url) ?>"
             data-isdir="<?= $isDir?'1':'0' ?>"
             data-size="<?= $f['size'] ?>"
             data-tc="<?= $t ?>"
             data-bdg="<?= $bdgcls ?>"
             data-lbl="<?= $lbl ?>"
             data-mod="<?= $f['modified'] ?>">
          <div class="fc-ck"><svg data-feather="check"></svg></div>
          <?php if ($isImg&&$url!=='#'): ?>
            <div class="th <?= $thcls ?>"><img src="<?= htmlspecialchars($url) ?>" alt="" loading="lazy" onerror="this.parentElement.innerHTML='<svg data-feather=\'image\'></svg>';feather.replace()"></div>
          <?php else: ?>
            <div class="th <?= $thcls ?>"><svg data-feather="<?= $icon ?>"></svg></div>
          <?php endif; ?>
          <div class="fc-n" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
          <div class="fc-m"><?= !$isDir?fmtSz($f['size']).' · ':'' ?><?= date('M j, Y',$f['modified']) ?></div>
          <div class="fc-a">
            <?php if (!$isDir): ?>
              <button class="btn btn-g btn-sm btn-icon" data-action="preview" title="Preview"><svg data-feather="eye"></svg></button>
              <a class="btn btn-g btn-sm btn-icon" href="<?= htmlspecialchars($url) ?>" download title="Download"><svg data-feather="download"></svg></a>
            <?php endif; ?>
            <button class="btn btn-g btn-sm btn-icon" data-action="rename" title="Rename"><svg data-feather="edit-2"></svg></button>
            <button class="btn btn-d btn-sm btn-icon" data-action="delete" title="Delete"><svg data-feather="trash-2"></svg></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
</div>

<!-- BULK BAR -->
<div class="bb" id="bb">
  <span class="bcn" id="bbc">0 selected</span>
  <div class="bbd"></div>
  <button class="btn btn-o btn-sm" id="bbDl"><svg data-feather="download"></svg>Download</button>
  <button class="btn btn-d btn-sm" id="bbDel"><svg data-feather="trash-2"></svg>Delete</button>
  <div class="bbd"></div>
  <button class="btn btn-g btn-sm btn-icon" id="bbCl" title="Clear"><svg data-feather="x"></svg></button>
</div>

<!-- TOASTS -->
<div class="tsts" id="tsts"></div>

<!-- UPLOAD MODAL -->
<div class="ov" id="upMod">
  <div class="modal">
    <div class="mh"><h2>Upload Files</h2><button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('upMod')"><svg data-feather="x"></svg></button></div>
    <div class="mb">
      <div class="dz" id="dz"><svg data-feather="upload-cloud"></svg><p>Drag & drop files, or <strong>click to browse</strong></p><small>PDF · DOC · XLS · PPT · Images · ZIP · Video · Audio · max 50 MB each</small><input type="file" id="fi" multiple style="display:none"></div>
      <div class="ufl" id="ufl"></div>
      <div class="up" id="upProg" style="display:none">
        <div class="uprow"><span id="upt">Uploading…</span><span id="uppct">0%</span></div>
        <div class="uptrk"><div class="upbar" id="upb" style="width:0"></div></div>
        <div class="upi" id="upi"></div>
      </div>
    </div>
    <div class="mf">
      <button class="btn btn-g" onclick="Modal.close('upMod')">Cancel</button>
      <button class="btn btn-d" id="abortBtn" style="display:none"><svg data-feather="x"></svg>Abort</button>
      <button class="btn btn-p" id="upBtn"><svg data-feather="upload-cloud"></svg>Upload</button>
    </div>
  </div>
</div>

<!-- NEW FOLDER MODAL -->
<div class="ov" id="mkMod">
  <div class="modal">
    <div class="mh"><h2>Create New Folder</h2><button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('mkMod')"><svg data-feather="x"></svg></button></div>
    <div class="mb">
      <div class="fg2">
        <label class="fl2x">Folder Name</label>
        <input class="fi" id="fnIn" type="text" placeholder="my-folder-name" maxlength="80" autocomplete="off">
        <p class="fh">Letters, numbers, hyphens, underscores only.</p>
      </div>
    </div>
    <div class="mf">
      <button class="btn btn-g" onclick="Modal.close('mkMod')">Cancel</button>
      <button class="btn btn-p" id="mkBtn"><svg data-feather="folder-plus"></svg>Create Folder</button>
    </div>
  </div>
</div>

<!-- RENAME MODAL -->
<div class="ov" id="rnMod">
  <div class="modal">
    <div class="mh"><h2>Rename</h2><button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('rnMod')"><svg data-feather="x"></svg></button></div>
    <div class="mb">
      <div class="fg2">
        <label class="fl2x">New Name</label>
        <input class="fi" id="rnIn" type="text" maxlength="120" autocomplete="off">
        <input type="hidden" id="rnOld">
        <p class="fh">Special characters become underscores.</p>
      </div>
    </div>
    <div class="mf">
      <button class="btn btn-g" onclick="Modal.close('rnMod')">Cancel</button>
      <button class="btn btn-p" id="rnBtn"><svg data-feather="edit-2"></svg>Rename</button>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="ov" id="delMod">
  <div class="modal">
    <div class="mh"><h2>Confirm Deletion</h2><button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('delMod')"><svg data-feather="x"></svg></button></div>
    <div class="mb" style="padding-top:24px">
      <div class="ci2 warn"><svg data-feather="alert-triangle"></svg></div>
      <div class="ct2">Delete <span id="dtn" style="color:var(--rd)"></span>?</div>
      <p class="cb2" id="dwarn">This action is permanent and cannot be undone.</p>
    </div>
    <div class="mf">
      <button class="btn btn-g" onclick="Modal.close('delMod')">Cancel</button>
      <button class="btn btn-ds" id="delBtn"><svg data-feather="trash-2"></svg>Delete Permanently</button>
    </div>
  </div>
</div>

<!-- PREVIEW MODAL -->
<div class="ov" id="pvMod">
  <div class="modal modal-xl" style="display:flex;flex-direction:column">
    <div class="mh">
      <h2 id="pvTitle" style="font-family:'DM Mono',monospace;font-size:13px;font-weight:500;color:var(--i1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%">Preview</h2>
      <div style="display:flex;gap:8px;flex-shrink:0">
        <a class="btn btn-o btn-sm" id="pvDl" download><svg data-feather="download"></svg>Download</a>
        <button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('pvMod')"><svg data-feather="x"></svg></button>
      </div>
    </div>
    <div class="pvb" id="pvb"><div class="spin"></div></div>
  </div>
</div>

<!-- AUDIT MODAL -->
<div class="ov" id="audMod">
  <div class="modal modal-lg" style="max-height:80vh">
    <div class="mh">
      <h2>Audit Log</h2>
      <div style="display:flex;gap:8px">
        <button class="btn btn-o btn-sm" onclick="loadAudit()"><svg data-feather="refresh-cw"></svg>Refresh</button>
        <button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('audMod')"><svg data-feather="x"></svg></button>
      </div>
    </div>
    <div class="mb" style="padding:0;overflow-x:auto"><div id="audCont"></div></div>
  </div>
</div>

<!-- SHORTCUTS MODAL -->
<div class="ov" id="kbModal">
  <div class="modal">
    <div class="mh"><h2>Keyboard Shortcuts</h2><button class="btn btn-g btn-sm btn-icon" onclick="Modal.close('kbModal')"><svg data-feather="x"></svg></button></div>
    <div class="mb">
      <table style="width:100%;border-collapse:collapse">
        <?php foreach ([['Upload files','U'],['New folder','N'],['Select all','Ctrl+A'],['Delete selected','Delete'],['Clear selection / close','Esc'],['Toggle grid/list','V'],['Show shortcuts','?']] as [$l,$k]): ?>
        <tr style="border-bottom:1px solid var(--bd)">
          <td style="padding:9px 0;color:var(--i1);font-size:13px"><?= $l ?></td>
          <td style="text-align:right;padding:9px 0">
            <?php foreach (explode('+',$k) as $i=>$kk): ?><?= $i?'<span style="color:var(--i3);margin:0 3px;font-size:11px">+</span>':'' ?><kbd><?= trim($kk) ?></kbd><?php endforeach; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
</div>
</div>
<!-- [ Main Content ] end -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
<script>
'use strict';
feather.replace();
const CSRF = '<?= $CSRF ?>';

/* ── Modal ── */
const Modal = {
  open:  id => document.getElementById(id)?.classList.add('open'),
  close: id => document.getElementById(id)?.classList.remove('open')
};
document.querySelectorAll('.ov').forEach(o => o.addEventListener('click', e => { if (e.target===o) o.classList.remove('open'); }));

/* ── Toast ── */
function toast(msg, type='info') {
  const ic={ok:'check-circle',err:'alert-circle',info:'info'};
  const el=document.createElement('div');
  el.className=`tst t${type}`;
  el.innerHTML=`<svg data-feather="${ic[type]}"></svg><span class="tm">${msg}</span>`;
  document.getElementById('tsts').prepend(el);
  feather.replace();
  setTimeout(()=>el.style.opacity='0',3600);
  setTimeout(()=>el.remove(),4000);
}

/* ── API ── */
async function api(data) {
  const fd=new FormData();
  fd.append('csrf_token',CSRF);
  Object.entries(data).forEach(([k,v])=>fd.append(k,v));
  const r=await fetch(location.href,{method:'POST',body:fd});
  if(!r.ok) throw new Error('HTTP '+r.status);
  return r.json();
}

function fmtB(b){const u=['B','KB','MB','GB'];let i=0;while(b>=1024&&i<3){b/=1024;i++;}return b.toFixed(1)+'\u00a0'+u[i];}

/* ── Selection ── */
const sel=new Set(), bb=document.getElementById('bb'), bbc=document.getElementById('bbc');
function toggleSel(c){const p=c.dataset.path;sel.has(p)?(sel.delete(p),c.classList.remove('sel')):(sel.add(p),c.classList.add('sel'));syncBB();}
function clrSel(){sel.clear();document.querySelectorAll('.fc.sel,.fr.sel').forEach(c=>c.classList.remove('sel'));syncBB();}
function selAll(){document.querySelectorAll('.fc,.fr').forEach(c=>{sel.add(c.dataset.path);c.classList.add('sel');});syncBB();}
function syncBB(){const n=sel.size;bbc.textContent=n+' selected';bb.classList.toggle('show',n>0);}
document.getElementById('bbCl').addEventListener('click',clrSel);

/* ── File area click delegation ── */
document.getElementById('fa').addEventListener('click',e=>{
  const card=e.target.closest('.fc,.fr');
  if(!card) return;
  const act=e.target.closest('[data-action]')?.dataset.action;
  if(act==='preview'){doPreview(card);return;}
  if(act==='delete'){doDelete(card.dataset.path,card.dataset.name,card.dataset.isdir==='1');return;}
  if(act==='rename'){doRename(card);return;}
  if(e.target.closest('a[download]')) return;
  if(e.ctrlKey||e.metaKey){toggleSel(card);return;}
  if(card.dataset.isdir==='1'){location.href='?folder='+encodeURIComponent(card.dataset.path);return;}
  doPreview(card);
});

/* ── Preview ── */
function doPreview(card){
  const {url,name,type}=card.dataset;
  document.getElementById('pvTitle').textContent=name;
  const dl=document.getElementById('pvDl'); dl.href=url; dl.download=name;
  const body=document.getElementById('pvb');
  body.innerHTML='<div class="spin"></div>';
  Modal.open('pvMod');
  if(type.startsWith('image/')){
    const img=new Image();
    img.onload=()=>{body.innerHTML='';body.appendChild(img);};
    img.onerror=()=>{body.innerHTML=pvNone(name,url);feather.replace();};
    img.src=url; return;
  }
  if(type==='application/pdf'){body.innerHTML=`<iframe src="${url}#toolbar=1"></iframe>`;return;}
  if(type.startsWith('text/')||type.includes('json')||type.includes('csv')||type.includes('xml')){
    fetch(url).then(r=>r.text()).then(t=>{const p=document.createElement('pre');p.textContent=t.slice(0,200000);body.innerHTML='';body.appendChild(p);}).catch(()=>{body.innerHTML=pvNone(name,url);feather.replace();});
    return;
  }
  if(type.startsWith('video/')){body.innerHTML=`<video class="pvb video" controls src="${url}" style="max-width:100%;max-height:100%"></video>`;return;}
  if(type.startsWith('audio/')){body.innerHTML=`<div style="padding:40px"><audio controls src="${url}" style="width:360px"></audio></div>`;return;}
  body.innerHTML=pvNone(name,url); feather.replace();
}
function pvNone(name,url){return `<div class="pvn"><svg data-feather="file"></svg><p style="font-size:13px;margin-bottom:14px">No preview for this file type.</p><a class="btn btn-p" href="${url}" download><svg data-feather="download"></svg>Download</a></div>`;}

/* ── Upload ── */
const dz=document.getElementById('dz'), fi=document.getElementById('fi');
const upBtn=document.getElementById('upBtn'), abortBtn=document.getElementById('abortBtn');
let xhr=null;
dz.addEventListener('click',()=>fi.click());
['dragover','dragenter'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('drag');}));
['dragleave','dragend'].forEach(ev=>dz.addEventListener(ev,()=>dz.classList.remove('drag')));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('drag');fi.files=e.dataTransfer.files;renderUFL();});
fi.addEventListener('change',renderUFL);
function renderUFL(){
  const ul=document.getElementById('ufl'); ul.innerHTML='';
  Array.from(fi.files).forEach(f=>{const ok=f.size<=50*1024*1024;const d=document.createElement('div');d.className='ufi'+(ok?'':' bad');d.innerHTML=`<span class="nm">${f.name}</span><span class="sz">${fmtB(f.size)}</span><span class="${ok?'sok':'serr'}">${ok?'✓':'> 50 MB'}</span>`;ul.appendChild(d);});
}
upBtn.addEventListener('click',()=>{
  const files=Array.from(fi.files).filter(f=>f.size<=50*1024*1024);
  if(!files.length){toast('No valid files selected','info');return;}
  const fd=new FormData(); fd.append('action','upload_file'); fd.append('csrf_token',CSRF);
  files.forEach(f=>fd.append('files[]',f));
  upBtn.disabled=true; abortBtn.style.display='';
  const prog=document.getElementById('upProg'),bar=document.getElementById('upb'),pct=document.getElementById('uppct'),info=document.getElementById('upi'),txt=document.getElementById('upt');
  prog.style.display=''; bar.className='upbar';
  xhr=new XMLHttpRequest();
  xhr.upload.addEventListener('progress',ev=>{if(!ev.lengthComputable)return;const p=Math.round(ev.loaded/ev.total*100);bar.style.width=p+'%';pct.textContent=p+'%';info.textContent=fmtB(ev.loaded)+' / '+fmtB(ev.total);});
  xhr.addEventListener('load',()=>{try{const res=JSON.parse(xhr.responseText);if(res.success){bar.classList.add('done');txt.textContent='Complete';toast(res.message,'ok');setTimeout(()=>location.reload(),900);}else{bar.classList.add('err2');txt.textContent='Failed';toast(res.message,'err');upBtn.disabled=false;abortBtn.style.display='none';}}catch{toast('Server error','err');}});
  xhr.addEventListener('error',()=>{toast('Network error','err');resetUp();});
  xhr.addEventListener('abort',()=>{toast('Upload aborted','info');resetUp();});
  xhr.open('POST',location.href); xhr.send(fd);
});
abortBtn.addEventListener('click',()=>xhr?.abort());
function resetUp(){upBtn.disabled=false;abortBtn.style.display='none';document.getElementById('upProg').style.display='none';document.getElementById('upb').style.width='0';}

/* ── Create folder ── */
document.getElementById('mkBtn').addEventListener('click',async()=>{
  const n=document.getElementById('fnIn').value.trim();
  if(!n){toast('Enter a folder name','info');return;}
  try{const r=await api({action:'create_folder',folder_name:n});if(r.success){toast(r.message,'ok');setTimeout(()=>location.reload(),500);}else toast(r.message,'err');}catch{toast('Request failed','err');}
});
document.getElementById('fnIn').addEventListener('keydown',e=>{if(e.key==='Enter')document.getElementById('mkBtn').click();});

/* ── Delete ── */
let _dp='',_dbulk=false;
function doDelete(path,name,isDir){_dp=path;_dbulk=false;document.getElementById('dtn').textContent=name;document.getElementById('dwarn').textContent=isDir?'This deletes the folder and ALL its contents.':'This action is permanent and cannot be undone.';Modal.open('delMod');}
document.getElementById('delBtn').addEventListener('click',async()=>{
  Modal.close('delMod');
  if(_dbulk){
    let ok=0,fail=0;
    for(const p of Array.from(sel)){try{const r=await api({action:'delete_item',item_path:p});r.success?ok++:fail++;}catch{fail++;}}
    toast(`Deleted ${ok} item(s)`+(fail?`, ${fail} failed`:''),fail?'err':'ok');
    setTimeout(()=>location.reload(),500);return;
  }
  try{const r=await api({action:'delete_item',item_path:_dp});if(r.success){toast(r.message,'ok');setTimeout(()=>location.reload(),400);}else toast(r.message,'err');}catch{toast('Delete failed','err');}
});

/* ── Rename ── */
function doRename(c){document.getElementById('rnIn').value=c.dataset.name;document.getElementById('rnOld').value=c.dataset.path;Modal.open('rnMod');setTimeout(()=>{const i=document.getElementById('rnIn');i.focus();i.select();},60);}
document.getElementById('rnBtn').addEventListener('click',async()=>{
  const nn=document.getElementById('rnIn').value.trim(),op=document.getElementById('rnOld').value;
  if(!nn) return;
  try{const r=await api({action:'rename_item',old_path:op,new_name:nn});Modal.close('rnMod');if(r.success){toast(r.message,'ok');setTimeout(()=>location.reload(),400);}else toast(r.message,'err');}catch{toast('Rename failed','err');}
});
document.getElementById('rnIn').addEventListener('keydown',e=>{if(e.key==='Enter')document.getElementById('rnBtn').click();});

/* ── Bulk delete ── */
document.getElementById('bbDel').addEventListener('click',()=>{if(!sel.size)return;_dbulk=true;document.getElementById('dtn').textContent=sel.size+' items';document.getElementById('dwarn').textContent=`Permanently deletes all ${sel.size} selected items.`;Modal.open('delMod');});

/* ── Bulk download ── */
document.getElementById('bbDl').addEventListener('click',()=>{sel.forEach(p=>{const c=document.querySelector(`[data-path="${CSS.escape(p)}"]`);if(!c||c.dataset.isdir==='1')return;const a=document.createElement('a');a.href=c.dataset.url;a.download=c.dataset.name;a.click();});});

/* ── View mode ── */
const vt=document.getElementById('vt'),fgrid=document.getElementById('fgrid'),lh=document.getElementById('lh');
let view=localStorage.getItem('fbv3')||'grid';

function applyView(v){
  view=v; localStorage.setItem('fbv3',v);
  vt.querySelectorAll('button').forEach(b=>b.classList.toggle('on',b.dataset.v===v));
  if(v==='list'){
    fgrid.classList.remove('fg'); fgrid.classList.add('fl2'); lh.style.display='flex';
    document.querySelectorAll('.fc').forEach(card=>{
      const isSel=card.classList.contains('sel');
      const th=card.querySelector('.th').cloneNode(true);
      const name=card.dataset.name; const tc2=card.dataset.tc; const bdg=card.dataset.bdg; const lbl=card.dataset.lbl;
      const sz=parseInt(card.dataset.size); const mod=parseInt(card.dataset.mod); const isDir=card.dataset.isdir==='1';
      const actions=card.querySelector('.fc-a').cloneNode(true);
      card.className='fr'+(isSel?' sel':'');
      const chk=document.createElement('input'); chk.type='checkbox'; chk.style.cssText='width:14px;height:14px;accent-color:var(--ac);flex-shrink:0;cursor:pointer;'; chk.checked=isSel;
      chk.addEventListener('change',()=>toggleSel(card));
      card.innerHTML='';
      th.className='th '+th.className.split(' ').find(c2=>c2.startsWith('t'))||''; th.style.cssText='width:32px;height:32px;border-radius:7px;flex-shrink:0;';
      const svgEl=th.querySelector('svg'); if(svgEl){svgEl.style.width='15px';svgEl.style.height='15px';}
      const ne=document.createElement('div'); ne.className='rn'; ne.textContent=name; ne.title=name;
      const te=document.createElement('div'); te.className='rty'; te.innerHTML=`<span class="bdg ${bdg}">${lbl}</span>`;
      const se=document.createElement('div'); se.className='rsz'; se.textContent=isDir?'—':fmtB(sz);
      const de=document.createElement('div'); de.className='rdt'; de.textContent=new Date(mod*1000).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
      actions.className='ra';
      card.append(chk,th,ne,te,se,de,actions);
    });
  } else {
    fgrid.classList.remove('fl2'); fgrid.classList.add('fg'); lh.style.display='none';
    location.reload();
  }
  feather.replace();
}

vt.querySelectorAll('button').forEach(b=>b.addEventListener('click',()=>applyView(b.dataset.v)));
if(view==='list') applyView('list');

/* ── Audit log ── */
function openAudit(){loadAudit();Modal.open('audMod');}
async function loadAudit(){
  const c=document.getElementById('audCont'); c.innerHTML='<div class="spin"></div>';
  try{
    const r=await fetch('?__audit=1'); const txt=await r.text();
    const lines=txt.trim().split('\n').filter(Boolean).reverse().slice(0,200);
    if(!lines.length){c.innerHTML='<p style="padding:20px;color:var(--i3);font-size:13px">No audit entries yet.</p>';return;}
    const rows=lines.map(l=>{
      const ts=l.match(/\[([\d\- :]+)\]/)?.[1]??'';
      const uid=l.match(/uid=(\d+)/)?.[1]??'';
      const ip=l.match(/ip=([^\s]+)/)?.[1]??'';
      const action=l.match(/action=(\S+)/)?.[1]??'';
      const ok=l.includes('ok=YES');
      const detail=l.match(/detail=(.+)$/)?.[1]??'';
      return `<tr><td>${ts}</td><td>${uid}</td><td>${ip}</td><td>${action}</td><td class="${ok?'aok':'afa'}">${ok?'✓':'✗'}</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${detail}">${detail}</td></tr>`;
    }).join('');
    c.innerHTML=`<table class="aud"><thead><tr><th>Time</th><th>UID</th><th>IP</th><th>Action</th><th>OK</th><th>Detail</th></tr></thead><tbody>${rows}</tbody></table>`;
  }catch{c.innerHTML='<p style="padding:20px;color:var(--rd);font-size:13px">Failed to load log.</p>';}
}

/* ── Keyboard shortcuts ── */
document.addEventListener('keydown',e=>{
  if(['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
  const k=e.key;
  if(k==='u'||k==='U'){Modal.open('upMod');return;}
  if(k==='n'||k==='N'){Modal.open('mkMod');return;}
  if(k==='v'||k==='V'){applyView(view==='grid'?'list':'grid');return;}
  if(k==='?'){Modal.open('kbModal');return;}
  if((e.ctrlKey||e.metaKey)&&k==='a'){e.preventDefault();selAll();return;}
  if(k==='Delete'&&sel.size){document.getElementById('bbDel').click();return;}
  if(k==='Escape'){const op=document.querySelector('.ov.open');if(op){op.classList.remove('open');return;}clrSel();}
});

feather.replace();
</script>
</body>
</html>