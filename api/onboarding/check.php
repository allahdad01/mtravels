<?php
require_once '../../admin/security.php';
enforce_auth(['admin', 'finance', 'sales', 'umrah']);
require_once '../../includes/db.php';

header('Content-Type: application/json');

$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);
if ($tenant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid tenant']);
    exit;
}

require_once '../../includes/OnboardingGuide.php';
$guide = new OnboardingGuide($pdo, $tenant_id, $branch_id);

echo json_encode([
    'success' => true,
    'should_show' => $guide->shouldShow(),
    'progress' => $guide->getProgress(),
    'current_step' => $guide->getCurrentStep(),
    'percent' => $guide->getProgressPercent(),
    'step_label' => $guide->getCurrentStep() ? OnboardingGuide::getStepLabel($guide->getCurrentStep()) : null,
    'step_description' => $guide->getCurrentStep() ? OnboardingGuide::getStepDescription($guide->getCurrentStep()) : null,
    'step_page' => $guide->getCurrentStep() ? OnboardingGuide::getStepPage($guide->getCurrentStep()) : null,
]);
