<?php
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('Location: ../login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#22c55e;--red:#ef4444;--amber:#f59e0b;
    /* Template Management: Violet â†’ Pink */
    --c1:#7c3aed;--c2:#db2777;
    --radius:14px;--shadow:0 2px 12px rgba(124,58,237,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

/* Header */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(64,153,255,.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-.4px;position:relative}
.dash-header p{color:rgba(255,255,255,.8);margin:0;font-size:13px;position:relative}

/* Card */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff,#2ed8b6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}

/* Info note */
.info-note{background:rgba(124,58,237,.06);border:1.5px solid rgba(124,58,237,.18);border-radius:12px;padding:13px 16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#5b21b6;font-weight:500}
.info-note i{color:#7c3aed;flex-shrink:0;margin-top:1px}

/* Language tabs */
.lang-tabs{display:flex;gap:6px;margin-bottom:20px}
.lang-tab{display:flex;align-items:center;gap:7px;border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);background:var(--card-bg);cursor:pointer;transition:all .2s}
.lang-tab.active,.lang-tab:hover{background:linear-gradient(135deg,#4099ff,#2ed8b6);border-color:transparent;color:#fff;box-shadow:0 4px 14px rgba(64,153,255,.25)}
.lang-tab .lang-badge{font-size:11px;opacity:.8}

/* Tab panels */
.tab-panel{display:none}
.tab-panel.active{display:block}

/* Placeholders bar */
.placeholders-bar{background:rgba(245,158,11,.06);border:1.5px solid rgba(245,158,11,.2);border-radius:10px;padding:11px 16px;margin-bottom:20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px}
.placeholders-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#92400e;margin-right:4px}
.ph-tag{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;background:rgba(245,158,11,.15);color:#92400e;border-radius:6px;padding:3px 8px;cursor:pointer;border:none;transition:background .15s}
.ph-tag:hover{background:rgba(245,158,11,.28)}

/* Section blocks */
.section-block{margin-bottom:24px}
.section-block:last-child{margin-bottom:0}
.section-num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,#4099ff,#2ed8b6);color:#fff;font-size:11px;font-weight:800;flex-shrink:0}
.section-title{font-size:13px;font-weight:700;color:var(--text-main);display:flex;align-items:center;gap:8px;margin-bottom:3px}
.section-desc{font-size:11px;color:var(--text-sub);margin-bottom:12px;padding-left:30px}
.field-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px;display:block}

/* Inputs */
.tmpl-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s,box-shadow .2s;direction:rtl}
.tmpl-input:focus{border-color:#4099ff;background:#fff;box-shadow:0 0 0 3px rgba(64,153,255,.1)}
textarea.tmpl-input{resize:vertical;min-height:160px;font-family:'JetBrains Mono',monospace;font-size:12px;line-height:1.6}
textarea.tmpl-input.short{min-height:90px}

/* Divider */
.section-divider{border:none;border-top:1px solid var(--border);margin:20px 0}

/* Actions */
.editor-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid var(--border);margin-top:8px}
.reset-btn{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 18px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.reset-btn:hover{border-color:var(--red);color:var(--red)}
.save-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#4099ff,#2ed8b6);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .2s}
.save-btn:hover{opacity:.9}
.save-btn:disabled{opacity:.6;cursor:not-allowed}

/* Loading skeleton */
.skeleton{background:linear-gradient(90deg,var(--surface) 25%,#e8edf5 50%,var(--surface) 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;border-radius:8px;height:40px;margin-bottom:8px}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-file-text" style="margin-right:8px;"></i>Tazmin Agreement Templates</h4>
            <p>Customize agreement sections for each language —  Pashto &amp; Dari</p>
        </div>
    </div>

    <!-- Info note -->
    <div class="info-note">
        <i class="feather icon-info"></i>
        Customize all sections of the Tazmin agreement template for your tenant. Changes are saved to the database. Click a placeholder tag to copy it to your clipboard.
    </div>

    <!-- Language tabs -->
    <div class="lang-tabs">
        <button class="lang-tab active" id="tab-pashto" onclick="switchTab('ps', this)">
            <i class="feather icon-file-text"></i>Pashto <span class="lang-badge">(پشتو)</span>
        </button>
        <button class="lang-tab" id="tab-dari" onclick="switchTab('dari', this)">
            <i class="feather icon-file-text"></i>Dari <span class="lang-badge">(دری)</span>
        </button>
    </div>

    <!-- â”€â”€ Pashto Tab â”€â”€ -->
    <div class="tab-panel active" id="panel-pashto">
        <div class="dash-card">
            <div class="dash-card-head">
                <h6><span class="ico"><i class="feather icon-edit-3"></i></span>Pashto Template Editor</h6>
            </div>
            <div class="dash-card-body">

                <div class="placeholders-bar">
                    <span class="placeholders-label">Placeholders</span>
                    <button class="ph-tag" onclick="copyPh('{{agency_name}}')">{{agency_name}}</button>
                    <button class="ph-tag" onclick="copyPh('{{branch_name}}')">{{branch_name}}</button>
                    <button class="ph-tag" onclick="copyPh('{{guarantor_name}}')">{{guarantor_name}}</button>
                    <button class="ph-tag" onclick="copyPh('{{pilgrim_names}}')">{{pilgrim_names}}</button>
                    <button class="ph-tag" onclick="copyPh('{{duration}}')">{{duration}}</button>
                </div>

                <!-- Section 1: Header -->
                <div class="section-block">
                    <div class="section-title"><span class="section-num">1</span>Header Section</div>
                    <div class="section-desc">Appears at the top of the document (main title and subtitle)</div>

                    <div style="margin-bottom:12px;">
    <label class="field-label">Header Title</label>
    <input type="text" id="pashto-header" class="tmpl-input"
        placeholder="د {{agency_name}} - {{branch_name}} سیاحتی او توریستی شرکت سره د محترم {{guarantor_name}} ضمانت لیک">
</div>

<div>
    <label class="field-label">Subtitle</label>
    <input type="text" id="pashto-subtitle" class="tmpl-input"
        placeholder="د معتمریـنو د لیږد په اړه لاندې مسوولیتونو ته پام لرنه">
</div>
                </div>

                <hr class="section-divider">

                <!-- Section 2: Clauses -->
                <div class="section-block">
                    <div class="section-title"><span class="section-num">2</span>Agreement Clauses</div>
                    <div class="section-desc">Main content with numbered clauses —  use an HTML ordered list &lt;ol&gt;&lt;li&gt;...&lt;/li&gt;&lt;/ol&gt;</div>
                    <label class="field-label">Clauses (HTML)</label>
                    <textarea id="pashto-content" class="tmpl-input"></textarea>
                </div>

                <hr class="section-divider">

                <!-- Section 3: Guarantor -->
                <div class="section-block">
                    <div class="section-title"><span class="section-num">3</span>Guarantor Section</div>
                    <div class="section-desc">Guarantor pledge and commitment section</div>

                    <div style="margin-bottom:12px;">
                        <label class="field-label">Guarantor Section Title</label>
                        <input type="text" id="pashto-guarantor-title" class="tmpl-input"
placeholder="د ضمانت کوونکي عنوان">
                    </div>
                    <div>
                        <label class="field-label">Guarantor Commitment Text</label>
                        <textarea id="pashto-guarantor-text" class="tmpl-input short"></textarea>
                    </div>
                </div>

                <div class="editor-actions">
                    <button class="reset-btn" onclick="resetTemplate('ps')"><i class="feather icon-refresh-ccw"></i>Reset to Default</button>
                    <button class="save-btn" id="save-ps" onclick="saveTemplate('ps')"><i class="feather icon-save"></i>Save All Changes</button>
                </div>

            </div>
        </div>
    </div>

    <!-- â”€â”€ Dari Tab â”€â”€ -->
    <div class="tab-panel" id="panel-dari">
        <div class="dash-card">
            <div class="dash-card-head">
                <h6><span class="ico"><i class="feather icon-edit-3"></i></span>Dari Template Editor</h6>
            </div>
            <div class="dash-card-body">

                <div class="placeholders-bar">
                    <span class="placeholders-label">Placeholders</span>
                    <button class="ph-tag" onclick="copyPh('{{agency_name}}')">{{agency_name}}</button>
                    <button class="ph-tag" onclick="copyPh('{{branch_name}}')">{{branch_name}}</button>
                    <button class="ph-tag" onclick="copyPh('{{guarantor_name}}')">{{guarantor_name}}</button>
                    <button class="ph-tag" onclick="copyPh('{{pilgrim_names}}')">{{pilgrim_names}}</button>
                    <button class="ph-tag" onclick="copyPh('{{duration}}')">{{duration}}</button>
                </div>

                <!-- Section 1 -->
                <div class="section-block">
                    <div class="section-title"><span class="section-num">1</span>Header Section</div>
                    <div class="section-desc">Appears at the top of the document (main title and subtitle)</div>

                    <div style="margin-bottom:12px;">
    <label class="field-label">Header Title</label>
    <input type="text" id="dari-header" class="tmpl-input"
        placeholder="توافقنامه ضمانت با شرکت سیاحتی و گردشگری {{agency_name}} - {{branch_name}}">
</div>

<div>
    <label class="field-label">Subtitle</label>
    <input type="text" id="dari-subtitle" class="tmpl-input"
        placeholder="توجه به مسئولیت‌های زیر در مورد انتقال معتمرین">
</div>
                </div>

                <hr class="section-divider">

                <!-- Section 2 -->
                <div class="section-block">
                    <div class="section-title"><span class="section-num">2</span>Agreement Clauses</div>
                    <div class="section-desc">Main content with numbered clauses —  use an HTML ordered list &lt;ol&gt;&lt;li&gt;...&lt;/li&gt;&lt;/ol&gt;</div>
                    <label class="field-label">Clauses (HTML)</label>
                    <textarea id="dari-content" class="tmpl-input"></textarea>
                </div>

                <hr class="section-divider">

                <!-- Section 3 -->
                <div class="section-block">
                    <div class="section-title"><span class="section-num">3</span>Guarantor Section</div>
                    <div class="section-desc">Guarantor pledge and commitment section</div>

                    <div style="margin-bottom:12px;">
                        <label class="field-label">Guarantor Section Title</label>
                        <input type="text" id="dari-guarantor-title" class="tmpl-input"
placeholder="تعهد ضامن">
                    </div>
                    <div>
                        <label class="field-label">Guarantor Commitment Text</label>
                        <textarea id="dari-guarantor-text" class="tmpl-input short"></textarea>
                    </div>
                </div>

                <div class="editor-actions">
                    <button class="reset-btn" onclick="resetTemplate('dari')"><i class="feather icon-refresh-ccw"></i>Reset to Default</button>
                    <button class="save-btn" id="save-dari" onclick="saveTemplate('dari')"><i class="feather icon-save"></i>Save All Changes</button>
                </div>

            </div>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
const API_URL = '../api/tenant_super_admin/manage_templates.php';
let loadedTemplates = {};

function switchTab(language, btn) {
    document.querySelectorAll('.lang-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const prefix = language === 'ps' ? 'pashto' : 'dari';
    document.getElementById('panel-' + prefix).classList.add('active');
    loadTemplates(language);
}

function loadTemplates(language) {
    if (loadedTemplates[language]) return;
    const prefix = language === 'ps' ? 'pashto' : 'dari';

    fetch(`${API_URL}?action=list`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.templates) {
                const langData = data.templates.find(t => t.language === language);
                if (langData && langData.sections) {
                    document.getElementById(`${prefix}-header`).value          = langData.sections.tazmin_agreement_header || '';
                    document.getElementById(`${prefix}-subtitle`).value         = langData.sections.tazmin_agreement_subtitle || '';
                    document.getElementById(`${prefix}-content`).value          = langData.sections.tazmin_agreement || '';
                    document.getElementById(`${prefix}-guarantor-title`).value  = langData.sections.tazmin_agreement_guarantor_title || '';
                    document.getElementById(`${prefix}-guarantor-text`).value   = langData.sections.tazmin_agreement_guarantor_text || '';
                    loadedTemplates[language] = true;
                }
            } else {
                Swal.fire({ icon:'error', title:'Load Failed', text: data.message || 'Failed to load templates', confirmButtonColor:'#7c3aed' });
            }
        })
        .catch(err => {
            Swal.fire({ icon:'error', title:'Error', text: 'Failed to load templates: ' + err.message, confirmButtonColor:'#7c3aed' });
        });
}

function saveTemplate(language) {
    const prefix = language === 'ps' ? 'pashto' : 'dari';
    const btn = document.getElementById('save-' + (language === 'ps' ? 'ps' : 'dari'));
    const sections = {
        tazmin_agreement_header:           document.getElementById(`${prefix}-header`).value.trim(),
        tazmin_agreement_subtitle:         document.getElementById(`${prefix}-subtitle`).value.trim(),
        tazmin_agreement:                  document.getElementById(`${prefix}-content`).value.trim(),
        tazmin_agreement_guarantor_title:  document.getElementById(`${prefix}-guarantor-title`).value.trim(),
        tazmin_agreement_guarantor_text:   document.getElementById(`${prefix}-guarantor-text`).value.trim()
    };

    for (const [key, val] of Object.entries(sections)) {
        if (!val) {
            Swal.fire({ icon:'warning', title:'Empty Field', text:`All sections must have content. Empty: ${key}`, confirmButtonColor:'#7c3aed' });
            return;
        }
    }

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="feather icon-loader"></i> Saving...'; }

    Swal.fire({ title:'Saving...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

    let done = 0;
    const total = Object.keys(sections).length;

    for (const [name, content] of Object.entries(sections)) {
        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('template_name', name);
        fd.append('language', language);
        fd.append('content', content);

        fetch(API_URL, { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                done++;
                if (!data.success) throw new Error(data.message || `Failed to save ${name}`);
                if (done === total) {
                    loadedTemplates[language] = true;
                    if (btn) { btn.disabled=false; btn.innerHTML='<i class="feather icon-save"></i>Save All Changes'; }
                    Swal.fire({ icon:'success', title:'Saved!', text:'All sections saved successfully.', confirmButtonColor:'#7c3aed' });
                }
            })
            .catch(err => {
                if (btn) { btn.disabled=false; btn.innerHTML='<i class="feather icon-save"></i>Save All Changes'; }
                Swal.fire({ icon:'error', title:'Save Failed', text:err.message, confirmButtonColor:'#7c3aed' });
            });
    }
}

function resetTemplate(language) {
    Swal.fire({
        icon: 'warning',
        title: 'Reset to Default?',
        text: 'This will replace all custom sections with defaults. This cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Reset',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#db2777',
        cancelButtonColor: '#6b7a99'
    }).then(result => {
        if (!result.isConfirmed) return;
        const sections = ['tazmin_agreement_header','tazmin_agreement_subtitle','tazmin_agreement','tazmin_agreement_guarantor_title','tazmin_agreement_guarantor_text'];
        let done = 0;
        sections.forEach(name => {
            const fd = new FormData();
            fd.append('action','reset'); fd.append('language',language); fd.append('template_name',name);
            fetch(API_URL,{method:'POST',body:fd}).then(r=>r.json()).then(()=>{
                done++;
                if (done === sections.length) {
                    loadedTemplates[language] = false;
                    loadTemplates(language);
                    Swal.fire({ icon:'success', title:'Reset!', text:'All templates reset to default.', confirmButtonColor:'#7c3aed' });
                }
            }).catch(console.error);
        });
    });
}

function copyPh(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:`Copied: ${text}`, showConfirmButton:false, timer:1500, timerProgressBar:true });
    });
}

window.addEventListener('load', () => loadTemplates('ps'));
</script>

<?php include 'footer.php'; ?>