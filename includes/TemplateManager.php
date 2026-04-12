<?php

class TemplateManager {
    private $pdo;
    private $tenant_id;

    public function __construct($pdo, $tenant_id) {
        $this->pdo = $pdo;
        $this->tenant_id = $tenant_id;
    }

    /**
     * Get template content for a specific language
     * Returns custom template if exists, otherwise returns default
     */
    public function getTemplate($template_name, $language = 'ps', $default_content = '') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT template_content 
                FROM tenant_templates 
                WHERE tenant_id = ? AND template_name = ? AND language = ?
            ");
            $stmt->execute([$this->tenant_id, $template_name, $language]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['template_content'];
            }
            
            return $default_content;
        } catch (Exception $e) {
            return $default_content;
        }
    }

    /**
     * Save or update template
     */
    public function saveTemplate($template_name, $language, $content) {
        try {
            // Check if template exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM tenant_templates 
                WHERE tenant_id = ? AND template_name = ? AND language = ?
            ");
            $stmt->execute([$this->tenant_id, $template_name, $language]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update existing template
                $stmt = $this->pdo->prepare("
                    UPDATE tenant_templates 
                    SET template_content = ?, updated_at = NOW()
                    WHERE tenant_id = ? AND template_name = ? AND language = ?
                ");
                $stmt->execute([$content, $this->tenant_id, $template_name, $language]);
            } else {
                // Insert new template
                $stmt = $this->pdo->prepare("
                    INSERT INTO tenant_templates (tenant_id, template_name, language, template_content)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$this->tenant_id, $template_name, $language, $content]);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get all templates for a template_name across all languages
     */
    public function getTemplatesByName($template_name) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT language, template_content 
                FROM tenant_templates 
                WHERE tenant_id = ? AND template_name = ?
                ORDER BY language
            ");
            $stmt->execute([$this->tenant_id, $template_name]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Delete a template
     */
    public function deleteTemplate($template_name, $language) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM tenant_templates 
                WHERE tenant_id = ? AND template_name = ? AND language = ?
            ");
            $stmt->execute([$this->tenant_id, $template_name, $language]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
