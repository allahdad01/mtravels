<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to generate_agreement.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tenant_id) {
    die('Invalid tenant ID');
}

// Fetch tenant data
$stmt = $pdo->prepare("SELECT name, subdomain, identifier, plan, billing_email, created_at FROM tenants WHERE id = ? AND status != 'deleted'");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    die('Tenant not found');
}

// Fetch plan features
$stmt = $pdo->prepare("SELECT features FROM plans WHERE name = ? AND status = 'active'");
$stmt->execute([$tenant['plan']]);
$plan = $stmt->fetch();

// Format features for agreement
$features_html = '';
if ($plan && !empty($plan['features'])) {
    $features = json_decode($plan['features'], true);
    if (is_array($features)) {
        require_once '../includes/helpers.php';
        $formatted_features = array_map('formatFeatureName', $features);
        foreach ($formatted_features as $feature) {
            $features_html .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #E0E0E0;">• ' . htmlspecialchars($feature) . '</td></tr>';
        }
    } else {
        $features_html = '<tr><td style="padding: 8px 0;">' . htmlspecialchars($plan['features']) . '</td></tr>';
    }
} else {
    $features_html = '<tr><td style="padding: 8px 0; font-style: italic; color: #666;">Features not specified</td></tr>';
}

// Fetch settings
$stmt = $pdo->prepare("SELECT agency_name, title, phone, email, logo, address FROM settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$settings = $stmt->fetch();

// Fetch platform settings
$stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
$stmt->execute();
$platform_settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $platform_settings[$row['key']] = $row['value'];
}

// Load mPDF
require_once '../vendor/autoload.php';

// Prepare logo paths
$platform_logo_path = '';
if (!empty($platform_settings['platform_logo'])) {
    $platform_logo_path = '../uploads/logo/' . $platform_settings['platform_logo'];
}

$tenant_logo_path = '';
if (!empty($settings['logo'])) {
    $tenant_logo_path = '../uploads/logo/' . $settings['logo'];
}

// Create mPDF instance
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 20,
    'margin_right' => 20,
    'margin_top' => 50,
    'margin_bottom' => 25,
    'margin_header' => 5,
    'margin_footer' => 10,
]);

// Set document properties
$companyName = $platform_settings['platform_name'] ?? 'MTRAVELS';
$mpdf->SetTitle('Enterprise Service Agreement - ' . $tenant['name']);
$mpdf->SetAuthor($companyName);
$mpdf->SetCreator($companyName);
$mpdf->SetSubject('Master Service Agreement');
$mpdf->SetKeywords('MSA, Enterprise, SLA, Service Agreement');

// Define header with logos
$header = '<table width="100%" style="background-color: #F8F9FA; border-bottom: 2px solid #0D47A1; padding: 10px;">
    <tr>
        <td width="25%" style="text-align: left; vertical-align: middle;">';
if (!empty($platform_logo_path) && file_exists($platform_logo_path)) {
    $header .= '<img src="' . $platform_logo_path . '" height="35" style="max-width: 100px;" />';
} else {
    $header .= '<div style="font-size: 18pt; font-weight: bold; color: #0D47A1;">' . htmlspecialchars($companyName) . '</div>';
}
$header .= '</td>
        <td width="50%" style="text-align: center; vertical-align: middle;">
            <div style="font-size: 14pt; font-weight: bold; color: #424242;">MASTER SERVICE AGREEMENT</div>
            <div style="font-size: 9pt; color: #757575;">Enterprise Tenant Service Agreement</div>
        </td>
        <td width="25%" style="text-align: right; vertical-align: middle;">';
if (!empty($tenant_logo_path) && file_exists($tenant_logo_path)) {
    $header .= '<img src="' . $tenant_logo_path . '" height="35" style="max-width: 100px;" />';
}
$header .= '</td>
    </tr>
</table>';

$mpdf->SetHTMLHeader($header);

// Define footer
$footer = '<table width="100%" style="background-color: #F8F9FA; border-top: 1px solid #0D47A1; padding: 8px;">
    <tr>
        <td width="33%" style="text-align: left; font-size: 8pt; color: #757575;">
            CONFIDENTIAL & PROPRIETARY
        </td>
        <td width="34%" style="text-align: center; font-size: 9pt; color: #0D47A1; font-weight: bold;">
            Page {PAGENO} of {nb}
        </td>
        <td width="33%" style="text-align: right; font-size: 8pt; color: #757575;">
            ' . htmlspecialchars($tenant['name']) . ' | ' . date('Y') . '
        </td>
    </tr>
</table>';

$mpdf->SetHTMLFooter($footer);

// Prepare variables
$effective_date = date('F d, Y');
$jurisdiction = 'Afghanistan';
$website = $platform_settings['website'] ?? 'www.mtravels.net';
$contact_email = $platform_settings['contact_email'] ?? 'info@mtravels.net';
$tenant_email = $settings['email'] ?? $tenant['billing_email'];
$tenant_phone = $settings['phone'] ?? 'N/A';
$tenant_address = $settings['address'] ?? 'N/A';

// Enhanced CSS
$css = <<<CSS
<style>
    body { font-family: 'Helvetica', 'Arial', sans-serif; }
    
    .cover-title {
        font-size: 28pt;
        font-weight: bold;
        color: #0D47A1;
        text-align: center;
        margin: 30px 0 10px 0;
        letter-spacing: 1px;
    }
    
    .cover-subtitle {
        font-size: 12pt;
        color: #546E7A;
        text-align: center;
        margin-bottom: 30px;
        font-weight: 300;
    }
    
    .info-card {
        background: linear-gradient(135deg, #F8F9FA 0%, #E8EAF6 100%);
        border-left: 5px solid #0D47A1;
        padding: 20px;
        margin: 20px 0;
        border-radius: 0 8px 8px 0;
    }
    
    .info-grid {
        width: 100%;
        border-collapse: collapse;
    }
    
    .info-grid td {
        padding: 10px 15px;
        border-bottom: 1px solid #CFD8DC;
    }
    
    .info-grid td:first-child {
        font-weight: 600;
        color: #0D47A1;
        width: 35%;
        font-size: 9pt;
    }
    
    .info-grid td:last-child {
        color: #37474F;
        font-size: 9pt;
    }
    
    .intro-text {
        font-size: 10pt;
        line-height: 1.8;
        color: #37474F;
        text-align: justify;
        margin: 20px 0;
        padding: 15px;
        background-color: #FAFAFA;
        border-left: 3px solid #64B5F6;
    }
    
    h1 {
        font-size: 16pt;
        font-weight: bold;
        color: #0D47A1;
        margin: 25px 0 15px 0;
        padding: 12px 15px;
        background: linear-gradient(90deg, #E3F2FD 0%, #FFFFFF 100%);
        border-left: 5px solid #0D47A1;
        page-break-after: avoid;
    }
    
    .section-number {
        display: inline-block;
        background-color: #0D47A1;
        color: white;
        width: 30px;
        height: 30px;
        text-align: center;
        line-height: 30px;
        border-radius: 50%;
        margin-right: 10px;
        font-size: 12pt;
    }
    
    h2 {
        font-size: 11pt;
        font-weight: bold;
        color: #1976D2;
        margin: 18px 0 10px 0;
        padding-left: 15px;
        border-left: 3px solid #64B5F6;
    }
    
    p {
        font-size: 9.5pt;
        line-height: 1.7;
        color: #424242;
        text-align: justify;
        margin: 10px 0;
    }
    
    .content-box {
        background-color: #FAFAFA;
        border: 1px solid #E0E0E0;
        padding: 15px;
        margin: 15px 0;
        border-radius: 4px;
    }
    
    .feature-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    
    .feature-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #E0E0E0;
        font-size: 9pt;
        color: #546E7A;
    }
    
    .list-item {
        padding: 6px 0 6px 20px;
        font-size: 9.5pt;
        line-height: 1.6;
        color: #546E7A;
        position: relative;
    }
    
    .list-item:before {
        content: "▸";
        position: absolute;
        left: 5px;
        color: #1976D2;
        font-weight: bold;
    }
    
    .highlight-box {
        background-color: #FFF9C4;
        border-left: 4px solid #FBC02D;
        padding: 12px 15px;
        margin: 15px 0;
        font-size: 9.5pt;
        color: #F57F17;
        font-weight: 500;
    }
    
    .contact-section {
        margin-top: 25px;
    }
    
    .contact-box {
        background: linear-gradient(135deg, #E3F2FD 0%, #F5F5F5 100%);
        border: 2px solid #90CAF9;
        border-radius: 8px;
        padding: 20px;
        margin: 15px 0;
    }
    
    .contact-box h3 {
        color: #0D47A1;
        font-size: 11pt;
        font-weight: bold;
        margin: 0 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #64B5F6;
    }
    
    .signature-section {
        margin-top: 40px;
        page-break-inside: avoid;
    }
    
    .signature-header {
        text-align: center;
        font-size: 14pt;
        font-weight: bold;
        color: #0D47A1;
        padding: 15px;
        background: linear-gradient(90deg, #E3F2FD 0%, #FFFFFF 50%, #E3F2FD 100%);
        border-top: 3px solid #0D47A1;
        border-bottom: 3px solid #0D47A1;
        margin-bottom: 30px;
    }
    
    .signature-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .signature-cell {
        width: 50%;
        padding: 20px;
        vertical-align: top;
    }
    
    .signature-title {
        font-weight: bold;
        font-size: 11pt;
        color: #0D47A1;
        margin-bottom: 15px;
    }
    
    .signature-line {
        border-bottom: 2px solid #424242;
        margin: 50px 0 8px 0;
    }
    
    .signature-label {
        font-size: 8pt;
        color: #757575;
        margin: 5px 0;
    }
</style>
CSS;

// Build HTML content
$html = $css;

// Cover section
$html .= <<<HTML
<div class="cover-title">MASTER SERVICE AGREEMENT</div>
<div class="cover-subtitle">Enterprise Tenant Service Agreement</div>

<div class="info-card">
    <table class="info-grid">
        <tr>
            <td>AGREEMENT TYPE</td>
            <td>Enterprise Tenant Service Agreement (MSA)</td>
        </tr>
        <tr>
            <td>EFFECTIVE DATE</td>
            <td>{$effective_date}</td>
        </tr>
        <tr>
            <td>DOCUMENT VERSION</td>
            <td>1.0</td>
        </tr>
        <tr>
            <td>SERVICE PROVIDER</td>
            <td>{$companyName}</td>
        </tr>
        <tr>
            <td>ENTERPRISE CUSTOMER</td>
            <td>{$tenant['name']}</td>
        </tr>
        <tr>
            <td>CUSTOMER PLAN</td>
            <td>{$tenant['plan']}</td>
        </tr>
        <tr>
            <td>GOVERNING LAW</td>
            <td>{$jurisdiction}</td>
        </tr>
    </table>
</div>

<div class="intro-text">
    <strong>AGREEMENT OVERVIEW:</strong> This Enterprise Tenant Service Agreement ("Agreement") is entered into by and between <strong>{$companyName}</strong> ("Service Provider", "Company", "We") and <strong>{$tenant['name']}</strong> ("Tenant", "Client", "Enterprise Customer"). By executing this Agreement or accessing the {$companyName} platform, the Tenant agrees to be bound by all terms and conditions set forth herein.
</div>

<h1><span class="section-number">1</span> SCOPE OF SERVICES</h1>

<p>{$companyName} provides a comprehensive, cloud-based, multi-tenant Travel Management Software platform ("Platform") designed to streamline and optimize travel operations for enterprise customers.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">INCLUDED FEATURES & CAPABILITIES:</strong>
    <table class="feature-table">
        {$features_html}
    </table>
</div>

<p>Specific modules, usage limits, data storage quotas, and detailed service levels shall be defined in the Enterprise Subscription Order executed between the parties.</p>

<h1><span class="section-number">2</span> ENTERPRISE ACCESS & ADMINISTRATION</h1>

<p>The Tenant shall designate one or more Authorized Administrators who will have the authority to manage user accounts, configure system settings, and oversee platform usage. All access is role-based, permission-controlled, and fully auditable.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">TENANT RESPONSIBILITIES:</strong>
    <div class="list-item">User provisioning, de-provisioning, and permission management</div>
    <div class="list-item">All activities performed by authorized users under the Tenant's account</div>
    <div class="list-item">Maintaining the confidentiality and security of login credentials</div>
    <div class="list-item">Promptly notifying {$companyName} of any suspected unauthorized access</div>
    <div class="list-item">Compliance with all acceptable use policies and terms of service</div>
</div>

<p>{$companyName} reserves the right to enforce user limits, branch restrictions, feature access, or usage quotas as specified in the Enterprise Subscription Order.</p>

<h1><span class="section-number">3</span> DATA OWNERSHIP & DATA USE</h1>

<p>All data uploaded, created, processed, or stored through the Platform by the Tenant ("Tenant Data") remains the exclusive property of the Tenant. {$companyName} acts solely as a data processor and does not claim any ownership rights to Tenant Data.</p>

<div class="highlight-box">
    <strong>IMPORTANT:</strong> {$companyName} will never sell, share, license, or monetize your data for any purpose outside of providing contracted services.
</div>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">DATA PROCESSING COMMITMENTS:</strong>
    <div class="list-item">Process Tenant Data only as necessary to deliver contracted services</div>
    <div class="list-item">Implement appropriate technical and organizational measures to protect data</div>
    <div class="list-item">Provide data portability and export capabilities upon request</div>
    <div class="list-item">Delete or anonymize Tenant Data after the agreed retention period</div>
    <div class="list-item">Maintain data processing records and provide audit trails</div>
</div>

<h1><span class="section-number">4</span> INFORMATION SECURITY & COMPLIANCE</h1>

<p>{$companyName} implements enterprise-grade security controls designed to protect the Platform infrastructure and all Tenant Data against unauthorized access, disclosure, alteration, or destruction.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">SECURITY MEASURES:</strong>
    <div class="list-item">Multi-factor authentication and role-based access controls</div>
    <div class="list-item">Tenant data isolation using database-level or schema-level separation</div>
    <div class="list-item">End-to-end encryption for data in transit (TLS 1.2+) and at rest (AES-256)</div>
    <div class="list-item">Automated backup systems with point-in-time recovery capabilities</div>
    <div class="list-item">24/7 security monitoring and incident response protocols</div>
    <div class="list-item">Regular penetration testing and vulnerability assessments</div>
    <div class="list-item">Compliance with industry standards and regulatory requirements</div>
</div>

<p>While {$companyName} applies reasonable and industry-standard security safeguards, the Tenant acknowledges that no system can guarantee absolute security against all potential threats. {$companyName} commits to promptly notify the Tenant of any security incidents that may affect Tenant Data.</p>

<h1><span class="section-number">5</span> SERVICE AVAILABILITY & SLA</h1>

<p>{$companyName} is committed to maintaining high system availability and reliability for all enterprise customers.</p>

<div class="highlight-box">
    <strong>UPTIME COMMITMENT:</strong> 99.5% monthly availability (excluding scheduled maintenance)
</div>

<p>Planned maintenance windows will be communicated to the Tenant at least 48 hours in advance whenever operationally feasible. Emergency maintenance may be performed with reduced notice when necessary to maintain security or system stability.</p>

<p>Service Level Agreement (SLA) credits, compensation mechanisms, and specific performance metrics shall be defined in the Enterprise Subscription Order and constitute the sole and exclusive remedy for service availability failures.</p>

<h1><span class="section-number">6</span> ACCEPTABLE USE & CONDUCT</h1>

<p>The Tenant and all authorized users must use the Platform in compliance with all applicable laws, regulations, and the terms of this Agreement.</p>

<div class="content-box">
    <strong style="color: #D32F2F; font-size: 10pt;">PROHIBITED ACTIVITIES:</strong>
    <div class="list-item">Violating any applicable laws, regulations, or third-party rights</div>
    <div class="list-item">Attempting unauthorized access to systems, networks, or data</div>
    <div class="list-item">Introducing malware, viruses, or any malicious code</div>
    <div class="list-item">Exceeding contracted usage limits or abusing system resources</div>
    <div class="list-item">Reverse engineering, decompiling, or extracting source code</div>
    <div class="list-item">Reselling, sublicensing, or redistributing Platform access</div>
    <div class="list-item">Sending unsolicited communications (spam) or phishing attempts</div>
</div>

<p>Material violations may result in immediate suspension of service pending investigation and resolution. {$companyName} reserves the right to cooperate with law enforcement in cases of illegal activity.</p>

<h1><span class="section-number">7</span> CONFIDENTIALITY OBLIGATIONS</h1>

<p>Each party agrees to protect the other party's Confidential Information with the same degree of care used to protect its own confidential information, but in no event less than reasonable care.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">CONFIDENTIAL INFORMATION INCLUDES:</strong>
    <div class="list-item">Business strategies, financial data, and customer information</div>
    <div class="list-item">Technical specifications, system architecture, and proprietary algorithms</div>
    <div class="list-item">Pricing terms, contract details, and commercial arrangements</div>
    <div class="list-item">Product roadmaps, development plans, and strategic initiatives</div>
</div>

<p>Confidential Information shall be used solely for the purposes outlined in this Agreement and may only be disclosed to employees, contractors, or advisors with a legitimate business need to know. These confidentiality obligations survive termination of this Agreement for a period of three (3) years.</p>

<h1><span class="section-number">8</span> FEES, BILLING & PAYMENT TERMS</h1>

<p>Service fees, billing cycles, payment methods, and related financial terms are specified in the Enterprise Subscription Order attached to this Agreement.</p>

<div class="highlight-box">
    <strong>IMPORTANT:</strong> All fees are exclusive of taxes, levies, duties, or similar governmental charges.
</div>

<p>The Tenant is responsible for all applicable taxes associated with the services, excluding only taxes based on {$companyName}'s net income.</p>

<div class="content-box">
    <strong style="color: #D32F2F; font-size: 10pt;">CONSEQUENCES OF NON-PAYMENT:</strong>
    <div class="list-item">Suspension of access to the Platform after 15 days past due</div>
    <div class="list-item">Late payment fees or interest charges as permitted by law</div>
    <div class="list-item">Termination of this Agreement after 30 days past due</div>
    <div class="list-item">Recovery of collection costs and legal fees</div>
</div>

<p>Unless otherwise expressly specified in writing, all fees are non-refundable once services have been rendered.</p>

<h1><span class="section-number">9</span> INTELLECTUAL PROPERTY RIGHTS</h1>

<p>{$companyName} retains all right, title, and interest in and to all intellectual property related to the Platform.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">COMPANY-OWNED INTELLECTUAL PROPERTY:</strong>
    <div class="list-item">Platform software, source code, and all derivative works</div>
    <div class="list-item">System architecture, algorithms, and technical designs</div>
    <div class="list-item">Trademarks, service marks, logos, and branding elements</div>
    <div class="list-item">Documentation, training materials, and methodologies</div>
    <div class="list-item">Any improvements or enhancements developed during the term</div>
</div>

<p>This Agreement grants the Tenant a limited, non-exclusive, non-transferable, revocable license to access and use the Platform solely for the Tenant's internal business purposes during the term of the Agreement. No ownership rights, source code access, or intellectual property transfers to the Tenant.</p>

<h1><span class="section-number">10</span> AUDIT & COMPLIANCE</h1>

<p>{$companyName} reserves the right to audit system usage, user activity, and resource consumption to ensure compliance with the terms of this Agreement and the Enterprise Subscription Order.</p>

<p>The Tenant agrees to cooperate with reasonable audit requests, provide necessary information and documentation, and grant access to relevant personnel for audit purposes.</p>

<p>If an audit reveals material non-compliance with usage limits or license terms, the Tenant shall promptly take corrective actions and may be responsible for reasonable audit costs incurred by {$companyName}.</p>

<h1><span class="section-number">11</span> INDEMNIFICATION</h1>

<h2>11.1 Tenant Indemnification</h2>

<p>The Tenant agrees to indemnify, defend, and hold harmless {$companyName}, its affiliates, officers, directors, employees, and agents from and against any third-party claims, damages, liabilities, costs, or expenses (including reasonable attorneys' fees) arising from:</p>

<div class="content-box">
    <div class="list-item">Illegal, unauthorized, or improper use of Tenant Data</div>
    <div class="list-item">Violations of applicable laws, regulations, or third-party rights</div>
    <div class="list-item">Misconduct, negligence, or unauthorized actions by Tenant's users</div>
    <div class="list-item">Material breach of this Agreement by the Tenant</div>
</div>

<h2>11.2 Service Provider Indemnification</h2>

<p>{$companyName} agrees to indemnify the Tenant against third-party claims alleging that the Platform, when used in accordance with this Agreement, infringes upon valid intellectual property rights, provided that the Tenant:</p>

<div class="content-box">
    <div class="list-item">Promptly notifies {$companyName} in writing of the claim</div>
    <div class="list-item">Cooperates fully in the defense and settlement process</div>
    <div class="list-item">Grants {$companyName} sole control over defense and settlement</div>
</div>

<h1><span class="section-number">12</span> LIMITATION OF LIABILITY</h1>

<p>To the maximum extent permitted by applicable law, the following limitations apply:</p>

<div class="content-box">
    <strong style="color: #D32F2F; font-size: 10pt;">EXCLUDED DAMAGES:</strong>
    <div class="list-item">Indirect, incidental, consequential, or punitive damages</div>
    <div class="list-item">Loss of profits, revenue, business opportunities, or goodwill</div>
    <div class="list-item">Loss of data or cost of procurement of substitute services</div>
    <div class="list-item">Business interruption or system downtime costs</div>
</div>

<div class="highlight-box">
    <strong>LIABILITY CAP:</strong> {$companyName}'s total aggregate liability under this Agreement shall not exceed the total fees paid by the Tenant in the twelve (12) months immediately preceding the event giving rise to liability.
</div>

<p>These limitations apply regardless of the legal theory of liability (contract, tort, negligence, strict liability, or otherwise). Nothing in this Agreement limits or excludes liability for fraud, gross negligence, willful misconduct, death or personal injury, or violations of applicable law that cannot be limited.</p>

<h1><span class="section-number">13</span> TERM & TERMINATION</h1>

<p>This Agreement commences on the Effective Date stated above and continues for the initial term specified in the Enterprise Subscription Order, with automatic renewal for successive periods unless terminated by either party in accordance with the terms below.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">TERMINATION RIGHTS:</strong>
    <div class="list-item"><strong>For Material Breach:</strong> Either party may terminate if the other party materially breaches this Agreement and fails to cure within thirty (30) days of written notice</div>
    <div class="list-item"><strong>For Insolvency:</strong> Either party may terminate if the other party becomes insolvent, files for bankruptcy, or enters into liquidation proceedings</div>
    <div class="list-item"><strong>For Non-Payment:</strong> {$companyName} may terminate if Tenant fails to pay undisputed fees within thirty (30) days after written notice</div>
    <div class="list-item"><strong>For Legal Reasons:</strong> Either party may terminate as required by law, court order, or regulatory directive</div>
    <div class="list-item"><strong>For Convenience:</strong> Either party may terminate by providing ninety (90) days advance written notice (if permitted by Subscription Order)</div>
</div>

<h2>Effects of Termination</h2>

<div class="content-box">
    <div class="list-item">All Tenant access to the Platform will be immediately revoked</div>
    <div class="list-item">Tenant may request complete data export within thirty (30) days</div>
    <div class="list-item">{$companyName} will delete or anonymize Tenant Data per retention policies</div>
    <div class="list-item">Tenant remains liable for all fees incurred prior to termination date</div>
    <div class="list-item">Sections 3, 7, 9, 11, 12, 15, and 16 survive termination</div>
</div>

<h1><span class="section-number">14</span> FORCE MAJEURE</h1>

<p>Neither party shall be liable for failure or delay in performing its obligations under this Agreement due to events beyond its reasonable control.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">FORCE MAJEURE EVENTS INCLUDE:</strong>
    <div class="list-item">Natural disasters (earthquakes, floods, hurricanes, fires)</div>
    <div class="list-item">Acts of war, terrorism, civil unrest, or armed conflict</div>
    <div class="list-item">Government actions, embargoes, or changes in applicable law</div>
    <div class="list-item">Internet infrastructure failures or telecommunications outages</div>
    <div class="list-item">Pandemics, epidemics, or public health emergencies</div>
    <div class="list-item">Labor disputes, strikes, or lockouts not involving the affected party's employees</div>
</div>

<p>The affected party shall promptly notify the other party of the force majeure event, provide regular updates, and make commercially reasonable efforts to mitigate the impact and resume performance.</p>

<h1><span class="section-number">15</span> GOVERNING LAW & DISPUTE RESOLUTION</h1>

<p>This Agreement shall be governed by, construed, and enforced in accordance with the laws of <strong>{$jurisdiction}</strong>, without regard to its conflict of law principles.</p>

<div class="content-box">
    <strong style="color: #0D47A1; font-size: 10pt;">DISPUTE RESOLUTION PROCESS:</strong>
    <div class="list-item"><strong>Step 1 - Negotiation:</strong> Any disputes shall first be subject to good-faith negotiations between senior executives of both parties for a period of thirty (30) days</div>
    <div class="list-item"><strong>Step 2 - Mediation:</strong> If negotiations fail, disputes may be submitted to non-binding mediation with a mutually agreed mediator</div>
    <div class="list-item"><strong>Step 3 - Arbitration/Litigation:</strong> Unresolved disputes shall be settled through binding arbitration or litigation in the courts of {$jurisdiction}, as mutually agreed</div>
</div>

<p>Each party retains the right to seek injunctive or equitable relief in any court of competent jurisdiction to prevent irreparable harm, protect intellectual property rights, or enforce confidentiality obligations.</p>

<h1><span class="section-number">16</span> GENERAL PROVISIONS</h1>

<p><strong>Entire Agreement:</strong> This Agreement, together with the Enterprise Subscription Order and any referenced exhibits or addenda, constitutes the entire understanding between the parties and supersedes all prior agreements, proposals, negotiations, and communications, whether written or oral.</p>

<p><strong>Amendments:</strong> No modification, amendment, or waiver of any provision of this Agreement shall be effective unless in writing and signed by authorized representatives of both parties. Online acceptance or clickwrap agreements do not constitute amendments to this Agreement.</p>

<p><strong>Severability:</strong> If any provision of this Agreement is held to be invalid, illegal, or unenforceable by a court of competent jurisdiction, the remaining provisions shall continue in full force and effect, and the invalid provision shall be modified to the minimum extent necessary to make it enforceable.</p>

<p><strong>Waiver:</strong> No failure or delay by either party in exercising any right, power, or remedy shall operate as a waiver thereof. No single or partial exercise of any right shall preclude any other or further exercise of that or any other right.</p>

<p><strong>Assignment:</strong> Neither party may assign, transfer, or delegate this Agreement or any rights or obligations hereunder without the prior written consent of the other party, except that either party may assign this Agreement in connection with a merger, acquisition, corporate reorganization, or sale of all or substantially all of its assets.</p>

<p><strong>Independent Contractors:</strong> The parties are independent contractors. Nothing in this Agreement creates a partnership, joint venture, agency, franchise, employment, or fiduciary relationship between the parties.</p>

<p><strong>Notices:</strong> All notices required under this Agreement shall be in writing and delivered via email with read receipt, certified mail, or courier service to the addresses specified in Section 17.</p>

<p><strong>Counterparts:</strong> This Agreement may be executed in counterparts, each of which shall be deemed an original and all of which together shall constitute one and the same instrument. Electronic signatures shall be deemed valid and binding.</p>

<div class="contact-section">
    <h1><span class="section-number">17</span> CONTACT INFORMATION</h1>

    <div class="contact-box">
        <h3>SERVICE PROVIDER</h3>
        <table class="info-grid">
            <tr>
                <td>Company Name</td>
                <td>{$companyName}</td>
            </tr>
            <tr>
                <td>Website</td>
                <td>{$website}</td>
            </tr>
            <tr>
                <td>Email Address</td>
                <td>{$contact_email}</td>
            </tr>
        </table>
    </div>

    <div class="contact-box">
        <h3>ENTERPRISE CUSTOMER</h3>
        <table class="info-grid">
            <tr>
                <td>Company Name</td>
                <td>{$tenant['name']}</td>
            </tr>
            <tr>
                <td>Email Address</td>
                <td>{$tenant_email}</td>
            </tr>
            <tr>
                <td>Phone Number</td>
                <td>{$tenant_phone}</td>
            </tr>
            <tr>
                <td>Business Address</td>
                <td>{$tenant_address}</td>
            </tr>
        </table>
    </div>
</div>

<div class="signature-section">
    <div class="signature-header">SIGNATURE PAGE</div>
    
    <p style="text-align: center; font-size: 9pt; color: #546E7A; margin-bottom: 25px;">
        By signing below, both parties acknowledge that they have read, understood, and agree to be bound by all terms and conditions of this Master Service Agreement.
    </p>
    
    <table class="signature-table">
        <tr>
            <td class="signature-cell">
                <div class="signature-title">{$companyName}</div>
                <div style="color: #757575; font-size: 8pt; margin-bottom: 10px;">Service Provider</div>
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Signature</div>
                <div style="margin-top: 15px;">
                    <div class="signature-label">Print Name: _________________________________</div>
                    <div class="signature-label">Title: _________________________________</div>
                    <div class="signature-label">Date: _________________________________</div>
                </div>
            </td>
            <td class="signature-cell">
                <div class="signature-title">{$tenant['name']}</div>
                <div style="color: #757575; font-size: 8pt; margin-bottom: 10px;">Enterprise Customer</div>
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Signature</div>
                <div style="margin-top: 15px;">
                    <div class="signature-label">Print Name: _________________________________</div>
                    <div class="signature-label">Title: _________________________________</div>
                    <div class="signature-label">Date: _________________________________</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<div style="margin-top: 30px; padding: 20px; background-color: #F5F5F5; border-left: 4px solid #0D47A1; font-size: 8pt; color: #757575; text-align: center;">
    <strong>Document ID:</strong> MSA-{$tenant['identifier']}-{$effective_date} | <strong>Generated:</strong> {$effective_date} | <strong>Status:</strong> Pending Execution
</div>
HTML;

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Output PDF
$filename = 'MSA_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $tenant['name']) . '_' . date('Ymd') . '.pdf';
$mpdf->Output($filename, 'I');
?>