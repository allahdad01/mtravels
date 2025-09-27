<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $settings['agency_name']; ?> Company Employment Agreement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .agreement-body {
            text-align: justify;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .clause {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="../../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" alt="Logo" style="max-width: 150px; margin-bottom: 10px;">
        <h1><?php echo $settings['agency_name']; ?> COMPANY<br>EMPLOYMENT AGREEMENT</h1>
    </div>

    <div class="agreement-body">
        <p>This Employment Agreement (the "Agreement") is made between:</p>
        
        <p><?php echo $settings['agency_name']; ?> Company (hereinafter referred to as the "Company")</p>
        
        <p>AND</p>
        
        <p>I, _________________________, son/daughter of _________________________, 
        a resident of _________________________ Province, _________________________ District,
        currently residing in _________________________ Province, _________________________ District,
        ID Card Number: _________________________</p>

        <p>hereby agree to work with <?php echo $settings['agency_name']; ?> Company under the following terms and conditions:</p>

        <div class="clause">
            <h3>1. Legal and Religious Compliance</h3>
            <p>Every employee is obligated to show complete respect for Afghanistan's Constitution, office regulations, and Islamic values. Employees must strictly avoid involvement in political matters. In case of violation, individual responsibility lies with judicial organs, and the <?php echo $settings['agency_name']; ?> Company is exempt from individual criminal liability.</p>
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
        </p>
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

        <div class="signature-section">
            <div class="employee-signature">
                <p>Employee Signature: _________________________<br>
                Date: _________________________</p>
            </div>
            <div class="company-signature">
                <p>Abdul Qadir Sabawoon<br>
                President, <?php echo $settings['agency_name']; ?> Company<br>
                Date: _________________________</p>
            </div>
        </div>
    </div>
</body>
</html> 