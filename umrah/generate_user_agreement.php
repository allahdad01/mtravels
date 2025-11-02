<?php
// Include necessary files
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once '../includes/language_helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

// Validate and sanitize inputs - check both POST and GET
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
}

if (!$user_id) {
    die('Invalid user ID provided');
}

try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('User not found');
    }

    // Fetch company settings
  $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
$settingStmt->execute([$tenant_id]);
$settings = $settingStmt->fetch(PDO::FETCH_ASSOC);


    // Check if the logo exists and set the path
    $logoPath = __DIR__ . '../uploads/logo/' . $settings['logo'];
    if (isset($settings['logo']) && !empty($settings['logo']) && file_exists('../uploads/logo/' . $settings['logo'])) {
        $logoPath = '../uploads/logo/' . $settings['logo'];
    }

    $rule = filter_input(INPUT_GET, 'rule', FILTER_DEFAULT);


} catch (PDOException $e) {
    error_log("Database error in generate_user_agreement.php: " . $e->getMessage());
    die("An error occurred while generating the agreement. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Employment Agreement - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
        <style>
        @media print {
            @page {
                size: A4;
                margin: 0.5cm;
            }
            body {
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
                font-size: 12pt;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-after: always;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .logo-container {
            position: absolute;
            left: 0;
            top: 0;
        }
        
        .logo-container img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .title-container {
            margin: 0 auto;
            max-width: 80%;
        }
        
        .title-container h1 {
            font-size: 16pt;
            color: #333;
            margin: 0 0 10px 0;
            line-height: 1.3;
            text-transform: uppercase;
        }
        
        .date {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 11pt;
            color: #333;
        }
        
        .divider {
            border-bottom: 1px solid #333;
            margin: 20px 0;
        }
        
        .agreement-body {
            padding: 0 20px;
        }
        
        .personal-info {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .clause {
            margin-bottom: 15px;
        }
        
        .clause h3 {
            font-size: 12pt;
            color: #333;
                margin-bottom: 5px;
            margin-top: 15px;
            }
        
        .signature-section {
                margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            text-align: center;
        }
        
            .signature-line {
                border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 30px;
        }
        
        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #eee;
            border-radius: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn:hover {
            opacity: 0.8;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <!-- Print Controls -->
        <div class="controls no-print">
            <button onclick="window.print();" class="btn">Print Agreement</button>
            <button onclick="window.history.back();" class="btn">Back</button>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Company Logo">
            </div>
            <div class="title-container">
                <h1><?php echo htmlspecialchars($settings['agency_name']); ?><br>EMPLOYMENT AGREEMENT</h1>
            </div>
            <div class="date">
                Date: <?php echo date('F j, Y'); ?>
            </div>
        </div>
        <div class="divider"></div>

        <!-- Agreement Body -->
        <div class="agreement-body">
            <div class="personal-info">
                <p>This Employment Agreement (the "Agreement") is made between:</p>
                <p><?php echo htmlspecialchars($settings['agency_name']); ?> (hereinafter referred to as the "Company")</p>
                <p>AND</p>
                <p>I, _________________________, son/daughter of _________________________, 
                a resident of _________________________ Province, _________________________ District,
                currently residing in _________________________ Province, _________________________ District,
                ID Card Number: _________________________</p>
                <p>hereby agree to work with <?php echo htmlspecialchars($settings['agency_name']); ?> under the following terms and conditions:</p>
            </div>

            <div class="clause">
                <h3>1. Legal and Religious Compliance</h3>
                <p>Every employee is obligated to show complete respect for Afghanistan's Constitution, office regulations, and Islamic values. Employees must strictly avoid involvement in political matters. In case of violation, individual responsibility lies with judicial organs, and the Al-Moqadas Company is exempt from individual criminal liability.</p>
            </div>

            <div class="clause">
                <h3>2. Working Hours and Attendance</h3>
                <p>2.1. Employees must report to their workplace by 8:00 AM daily.<br>
                2.2. Late arrival will result in being marked absent for the entire day unless administrative permission or legitimate religious excuse is provided.<br>
                2.3. Unauthorized absence for a full day will result in a three-day salary deduction.</p>
            </div>

            <div class="clause">
                <h3>3. Professional Conduct</h3>
                <p>3.1. Conducting personal or non-official work during official hours is strictly prohibited.<br>
                3.2. Violations will result in a fine of 500 Rupees.<br>
                3.3. Repeated violations will be subject to administrative action.</p>
            </div>

            <div class="clause">
                <h3>4. Confidentiality</h3>
                <p>4.1. Employees must maintain strict confidentiality of office information, both general and specific.<br>
                4.2. Breach of confidentiality will result in immediate termination and a two-month salary deduction.</p>
            </div>

            <div class="clause">
                <h3>5. External Communications</h3>
                <p>Employees are prohibited from meeting with employees or officials of other companies within the office, room, or any third location without administrative approval.</p>
            </div>

            <div class="clause">
                <h3>6. Professional Liability</h3>
                <p>Individual employees are responsible for their professional negligence. Any resulting damages will be the personal responsibility of the employee, with the Company being exempt from such liabilities.</p>
            </div>

            <div class="clause">
                <h3>7. Leave Policy</h3>
                <p>7.1. Employees are entitled to three (3) days of leave every two months, subject to legitimate religious excuse.<br>
                7.2. Additional leave will result in proportional salary deduction.</p>
            </div>

            <div class="clause">
                <h3>8. Visitor Protocol</h3>
                <p>Employees must inform the office manager in advance of any expected visitors.</p>
            </div>

            <div class="clause">
                <h3>9. Working Hours</h3>
                <p>9.1. Official working hours are from 8:00 AM to 6:00 PM.<br>
                9.2. Extended hours may be required during high workload periods.</p>
            </div>

            <div class="clause">
                <h3>9. Salary</h3>
                <p>9.1. The start of salary is 70000 AFN.<br>
                9.2. When the employee exceeds 300 USD profit per month, the employee will receive 10% of the profit as bonus afterwards of 300 USD profit per month.<br>
                9.3. Salary will be paid on the 1st of every month.<br>
                9.4. Salary will be paid in cash or through bank transfer.
            </div>

            <div class="clause">
                <h3>10. Guarantees</h3>
                <p>Employees are prohibited from providing guarantees for clients or on behalf of the office.</p>
            </div>

            <div class="clause">
                <h3>11. Communication Devices</h3>
                <p>11.1. Personal phone use during official hours is prohibited.<br>
                11.2. Office phones may be used when necessary for work-related purposes.</p>
            </div>

            <div class="clause">
                <h3>12. Work Completion</h3>
                <p>12.1. Daily tasks must be completed each day.<br>
                12.2. Incomplete work will result in salary withholding for the affected days.</p>
            </div>

            <div class="clause">
                <h3>13. Security Deposit</h3>
                <p>13.1. 50% of the total salary will be held as security deposit.<br>
                13.2. The deposit will be returned at year-end if no discrepancies are found.<br>
                13.3. Full salary may be received with provision of a reliable guarantor.</p>
            </div>

            <div class="clause">
                <h3>14. Performance Guarantee</h3>
                <p>Employees must provide a guarantee to compensate for any damages resulting from poor performance.</p>
            </div>
            <div class="clause">
                <h3>15. Other Rules</h3>
                <p><?= $rule ?></p>
            </div>

            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">
                        <p>Employee Signature<br>
                        _________________________<br>
                        Date: _________________________</p>
                    </div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        <p>Manager Signature<br>
                        _________________________<br>
                        Date: _________________________</p>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </body>
</html> 