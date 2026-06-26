<?php
require_once '../../admin/security.php';
enforce_auth(['admin', 'finance', 'sales', 'umrah', 'tenant_super_admin']);
require_once '../../includes/db.php';

header('Content-Type: application/json');

$_SESSION['onboarding_video_dismissed'] = true;

echo json_encode(['success' => true]);
