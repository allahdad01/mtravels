<?php
require_once '../../admin/security.php';
enforce_auth(['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin', 'super_admin']);
require_once '../../includes/db.php';

header('Content-Type: application/json');

$video_id = trim($_GET['video_id'] ?? '');
$video_type = trim($_GET['video_type'] ?? '');

if (empty($video_id)) {
    echo json_encode(['success' => false]);
    exit;
}

if ($video_type === 'youtube') {
    echo json_encode(['success' => true, 'url' => 'https://img.youtube.com/vi/' . urlencode($video_id) . '/hqdefault.jpg']);
    exit;
}

$cache_file = sys_get_temp_dir() . '/vimeo_thumb_' . md5($video_id) . '.json';
if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached && isset($cached['url'])) {
        echo json_encode(['success' => true, 'url' => $cached['url']]);
        exit;
    }
}

$url = 'https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . urlencode($video_id);
$ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'MTravels/1.0']]);
$response = @file_get_contents($url, false, $ctx);

if ($response) {
    $data = json_decode($response, true);
    if ($data && !empty($data['thumbnail_url'])) {
        $thumb_url = $data['thumbnail_url'];
        file_put_contents($cache_file, json_encode(['url' => $thumb_url]), LOCK_EX);
        echo json_encode(['success' => true, 'url' => $thumb_url]);
        exit;
    }
}

echo json_encode(['success' => false]);
