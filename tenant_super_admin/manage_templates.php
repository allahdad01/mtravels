<?php
include 'header.php';

// Check if user is logged in and is tenant_super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('Location: ../login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Template Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        .template-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .template-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-button {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            color: #2196F3;
            border-bottom-color: #2196F3;
        }
        
        .tab-button:hover {
            color: #2196F3;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .editor-section {
            margin-bottom: 20px;
        }
        
        .editor-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        textarea.template-editor {
            width: 100%;
            min-height: 200px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            direction: rtl;
        }

        input[type="text"].template-editor {
            width: 100%;
            padding: 10px 12px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            border: 1px solid #ddd;
            border-radius: 4px;
            direction: rtl;
        }
        
        .editor-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
        }
        
        .btn-secondary {
            background: #757575;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #616161;
        }
        
        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #1976D2;
            font-size: 13px;
        }
        
        .placeholder-info {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #e65100;
            font-size: 12px;
            line-height: 1.6;
        }
        
        .placeholder-info strong {
            display: block;
            margin-bottom: 8px;
        }

        .section-header {
            background: #f5f5f5;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
        }

        .section-header h4 {
            margin: 0;
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }

        .section-header p {
            margin: 4px 0 0 0;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="template-container">
    <h1 style="margin-bottom: 30px;">Tazmin Agreement Template Management</h1>
    
    <div class="info-box">
        <strong>ℹ️ Information:</strong> Customize all sections of the Tazmin agreement template for your tenant. Changes will be automatically saved to the database.
    </div>
    
    <div class="template-tabs">
        <button class="tab-button active" onclick="switchTab('pashto')">
            Pashto (پشتو)
        </button>
        <button class="tab-button" onclick="switchTab('dari')">
            Dari (دری)
        </button>
    </div>
    
    <!-- Pashto Template Tab -->
    <div id="pashto" class="tab-content active">
        <div class="placeholder-info">
            <strong>Available Placeholders:</strong>
            {{agency_name}} • {{branch_name}} • {{guarantor_name}} • {{pilgrim_names}} • {{duration}}
        </div>

        <div class="section-header">
            <h4>1. Header Section</h4>
            <p>Appears at the top of the document (main title)</p>
        </div>
        <div class="editor-section">
            <div class="editor-label">Header Title</div>
            <input type="text" id="pashto-header" class="template-editor" placeholder="د {{agency_name}} - {{branch_name}} سیاحتی او توریستی شرکت سره د محترم {{guarantor_name}} ضمانت لیک">
        </div>

        <div class="editor-section">
            <div class="editor-label">Subtitle</div>
            <input type="text" id="pashto-subtitle" class="template-editor" placeholder="د معتمرینو د لیږد په اړه لاندی مسؤلیتونو ته پاملرنه">
        </div>

        <div class="section-header" style="margin-top: 30px;">
            <h4>2. Agreement Clauses</h4>
            <p>Main content with 18 clauses (as ordered list)</p>
        </div>
        <div class="editor-section">
            <div class="editor-label">Clauses (HTML)</div>
            <textarea id="pashto-content" class="template-editor"></textarea>
        </div>

        <div class="section-header" style="margin-top: 30px;">
            <h4>3. Guarantor Section</h4>
            <p>Guarantor pledge and commitment section</p>
        </div>
        <div class="editor-section">
            <div class="editor-label">Guarantor Section Title</div>
            <input type="text" id="pashto-guarantor-title" class="template-editor" placeholder="د ضمانت کوونکی ژمنه">
        </div>

        <div class="editor-section">
            <div class="editor-label">Guarantor Commitment Text</div>
            <textarea id="pashto-guarantor-text" class="template-editor" style="min-height: 100px;"></textarea>
        </div>
        
        <div class="editor-actions">
            <button class="btn btn-secondary" onclick="resetTemplate('ps')">Reset to Default</button>
            <button class="btn btn-primary" onclick="saveTemplate('ps')">Save All Changes</button>
        </div>
    </div>
    
    <!-- Dari Template Tab -->
    <div id="dari" class="tab-content">
        <div class="placeholder-info">
            <strong>Available Placeholders:</strong>
            {{agency_name}} • {{branch_name}} • {{guarantor_name}} • {{pilgrim_names}} • {{duration}}
        </div>

        <div class="section-header">
            <h4>1. Header Section</h4>
            <p>Appears at the top of the document (main title)</p>
        </div>
        <div class="editor-section">
            <div class="editor-label">Header Title</div>
            <input type="text" id="dari-header" class="template-editor" placeholder="توافقنامه ضمانت با شرکت سیاحتی و گردشگری {{agency_name}} - {{branch_name}}">
        </div>

        <div class="editor-section">
            <div class="editor-label">Subtitle</div>
            <input type="text" id="dari-subtitle" class="template-editor" placeholder="توجه به مسئولیات زیر در مورد انتقال معتمرین">
        </div>

        <div class="section-header" style="margin-top: 30px;">
            <h4>2. Agreement Clauses</h4>
            <p>Main content with 18 clauses (as ordered list)</p>
        </div>
        <div class="editor-section">
            <div class="editor-label">Clauses (HTML)</div>
            <textarea id="dari-content" class="template-editor"></textarea>
        </div>

        <div class="section-header" style="margin-top: 30px;">
            <h4>3. Guarantor Section</h4>
            <p>Guarantor pledge and commitment section</p>
        </div>
        <div class="editor-section">
            <div class="editor-label">Guarantor Section Title</div>
            <input type="text" id="dari-guarantor-title" class="template-editor" placeholder="تعهد ضامن">
        </div>

        <div class="editor-section">
            <div class="editor-label">Guarantor Commitment Text</div>
            <textarea id="dari-guarantor-text" class="template-editor" style="min-height: 100px;"></textarea>
        </div>
        
        <div class="editor-actions">
            <button class="btn btn-secondary" onclick="resetTemplate('dari')">Reset to Default</button>
            <button class="btn btn-primary" onclick="saveTemplate('dari')">Save All Changes</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
    const API_URL = '../api/tenant_super_admin/manage_templates.php';
    let loadedTemplates = {};

    function switchTab(language) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(language).classList.add('active');
        event.target.classList.add('active');
        
        // Load template if not already loaded
        loadTemplates(language);
    }

    function loadTemplates(language) {
        // If already loaded, don't reload
        if (loadedTemplates[language]) return;
        
        fetch(`${API_URL}?action=list`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.templates) {
                    const langData = data.templates.find(t => t.language === language);
                    if (langData && langData.sections) {
                        const prefix = language === 'ps' ? 'pashto' : 'dari';
                        
                        document.getElementById(`${prefix}-header`).value = langData.sections.tazmin_agreement_header || '';
                        document.getElementById(`${prefix}-subtitle`).value = langData.sections.tazmin_agreement_subtitle || '';
                        document.getElementById(`${prefix}-content`).value = langData.sections.tazmin_agreement || '';
                        document.getElementById(`${prefix}-guarantor-title`).value = langData.sections.tazmin_agreement_guarantor_title || '';
                        document.getElementById(`${prefix}-guarantor-text`).value = langData.sections.tazmin_agreement_guarantor_text || '';
                        
                        loadedTemplates[language] = true;
                    }
                } else {
                    Swal.fire('Error', data.message || 'Failed to load templates', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to load templates: ' + error.message, 'error');
            });
    }

    function saveTemplate(language) {
        const prefix = language === 'ps' ? 'pashto' : 'dari';
        
        const sections = {
            'tazmin_agreement_header': document.getElementById(`${prefix}-header`).value.trim(),
            'tazmin_agreement_subtitle': document.getElementById(`${prefix}-subtitle`).value.trim(),
            'tazmin_agreement': document.getElementById(`${prefix}-content`).value.trim(),
            'tazmin_agreement_guarantor_title': document.getElementById(`${prefix}-guarantor-title`).value.trim(),
            'tazmin_agreement_guarantor_text': document.getElementById(`${prefix}-guarantor-text`).value.trim()
        };
        
        // Validate all sections have content
        for (const [key, value] of Object.entries(sections)) {
            if (!value) {
                Swal.fire('Error', `All sections must have content. Empty: ${key}`, 'error');
                return;
            }
        }
        
        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Save each section
        let saveCount = 0;
        for (const [section_name, content] of Object.entries(sections)) {
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('template_name', section_name);
            formData.append('language', language);
            formData.append('content', content);
            
            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                saveCount++;
                if (!data.success) {
                    throw new Error(data.message || `Failed to save ${section_name}`);
                }
                
                // All sections saved
                if (saveCount === Object.keys(sections).length) {
                    Swal.fire('Success', 'All sections saved successfully!', 'success');
                    loadedTemplates[language] = true; // Mark as loaded
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to save: ' + error.message, 'error');
            });
        }
    }

    function resetTemplate(language) {
        Swal.fire({
            title: 'Reset to Default?',
            text: 'This will replace all custom sections with defaults. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Reset',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const sections = [
                    'tazmin_agreement_header',
                    'tazmin_agreement_subtitle',
                    'tazmin_agreement',
                    'tazmin_agreement_guarantor_title',
                    'tazmin_agreement_guarantor_text'
                ];
                
                let resetCount = 0;
                for (const section_name of sections) {
                    const formData = new FormData();
                    formData.append('action', 'reset');
                    formData.append('language', language);
                    formData.append('template_name', section_name);
                    
                    fetch(API_URL, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        resetCount++;
                        if (resetCount === sections.length) {
                            // Clear loaded flag and reload
                            loadedTemplates[language] = false;
                            loadTemplates(language);
                            Swal.fire('Success', 'All templates reset to default!', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            }
        });
    }

    // Load Pashto template on page load
    window.addEventListener('load', () => {
        loadTemplates('ps');
    });
</script>

<?php include 'footer.php'; ?>
</body>
</html>
