<?php
require_once '../../includes/db.php';
require_once '../../includes/TemplateManager.php';
session_start();

// Check authorization
if (!isset($_SESSION['tenant_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$templateManager = new TemplateManager($pdo, $tenant_id);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get':
            // Get template content
            $template_name = $_GET['template_name'] ?? null;
            $language = $_GET['language'] ?? 'ps';
            
            if (!$template_name) {
                throw new Exception('Template name required');
            }
            
            require_once '../../api/umrah/tazmin_default_templates.php';
            $defaultTemplate = $DEFAULT_TEMPLATES['tazmin_agreement'][$language] ?? '';
            $content = $templateManager->getTemplate('tazmin_agreement', $language, $defaultTemplate);
            
            echo json_encode([
                'success' => true,
                'template_name' => $template_name,
                'language' => $language,
                'content' => $content
            ]);
            break;

        case 'save':
            // Save template
            $template_name = $_POST['template_name'] ?? null;
            $language = $_POST['language'] ?? 'ps';
            $content = $_POST['content'] ?? null;
            
            if (!$template_name || !$content) {
                throw new Exception('Template name and content required');
            }
            
            if ($templateManager->saveTemplate($template_name, $language, $content)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Template saved successfully'
                ]);
            } else {
                throw new Exception('Failed to save template');
            }
            break;

        case 'list':
            // Get all templates
            $templates = [];
            $languages = ['ps', 'dari'];
            $template_sections = ['tazmin_agreement_header', 'tazmin_agreement_subtitle', 'tazmin_agreement', 'tazmin_agreement_guarantor_title', 'tazmin_agreement_guarantor_text'];
            
            foreach ($languages as $lang) {
                require_once '../../api/umrah/tazmin_default_templates.php';
                
                $section_data = [];
                foreach ($template_sections as $section_name) {
                    $defaultTemplate = $DEFAULT_TEMPLATES[$section_name][$lang] ?? '';
                    $content = $templateManager->getTemplate($section_name, $lang, $defaultTemplate);
                    $section_data[$section_name] = $content;
                }
                
                $templates[] = [
                    'language' => $lang,
                    'language_label' => $lang === 'ps' ? 'Pashto' : 'Dari',
                    'sections' => $section_data
                ];
            }
            
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            break;

        case 'reset':
            // Reset to default template
            $language = $_POST['language'] ?? 'ps';
            $templateManager->deleteTemplate('tazmin_agreement', $language);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template reset to default'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
