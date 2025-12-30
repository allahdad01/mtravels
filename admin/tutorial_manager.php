<?php
// Tutorial Manager - Update video IDs for tutorials
// This page allows admins to add Vimeo video IDs to tutorials

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Sample tutorial data - you can load this from a database later
$tutorials = [
    [
        'id' => 1,
        'title' => 'Dashboard Overview',
        'category' => 'basics',
        'description' => 'Learn the dashboard layout, key metrics, financial wealth distribution, and quick action buttons.',
        'duration' => '5:00',
        'level' => 'Beginner'
    ],
    [
        'id' => 2,
        'title' => 'Navigating the Admin Panel',
        'category' => 'basics',
        'description' => 'Understand the sidebar menu, user profile options, and how to access different sections.',
        'duration' => '4:30',
        'level' => 'Beginner'
    ],
    [
        'id' => 3,
        'title' => 'Adding Ticket Bookings',
        'category' => 'tickets',
        'description' => 'Step-by-step guide to creating new ticket bookings with passenger details and flight information.',
        'duration' => '7:00',
        'level' => 'Beginner'
    ],
    [
        'id' => 4,
        'title' => 'Viewing and Filtering Tickets',
        'category' => 'tickets',
        'description' => 'Learn how to search, filter, and view ticket bookings by various criteria.',
        'duration' => '6:00',
        'level' => 'Beginner'
    ],
    [
        'id' => 5,
        'title' => 'Ticket Refunds and Date Changes',
        'category' => 'tickets',
        'description' => 'Process ticket refunds and manage date change requests with proper documentation.',
        'duration' => '8:30',
        'level' => 'Intermediate'
    ],
    [
        'id' => 6,
        'title' => 'Ticket Weights and Reservations',
        'category' => 'tickets',
        'description' => 'Manage ticket weights, reservations, and track ticket status throughout their lifecycle.',
        'duration' => '7:15',
        'level' => 'Intermediate'
    ],
    [
        'id' => 7,
        'title' => 'Adding New Clients',
        'category' => 'clients',
        'description' => 'Create client profiles with contact information, addresses, and identification details.',
        'duration' => '6:00',
        'level' => 'Beginner'
    ],
    [
        'id' => 8,
        'title' => 'Managing Client Information',
        'category' => 'clients',
        'description' => 'Edit, update, and maintain client profiles and transaction history.',
        'duration' => '5:30',
        'level' => 'Beginner'
    ],
    [
        'id' => 9,
        'title' => 'Client Credits and Debtors',
        'category' => 'clients',
        'description' => 'Track client credits, manage debtors list, and handle outstanding balances.',
        'duration' => '7:45',
        'level' => 'Intermediate'
    ],
    [
        'id' => 10,
        'title' => 'Adding Suppliers',
        'category' => 'suppliers',
        'description' => 'Create supplier accounts with payment terms, currencies, and contact information.',
        'duration' => '6:30',
        'level' => 'Beginner'
    ],
    [
        'id' => 11,
        'title' => 'Supplier Accounts and Transactions',
        'category' => 'suppliers',
        'description' => 'Monitor supplier balances, manage transactions, and track payment history.',
        'duration' => '8:00',
        'level' => 'Intermediate'
    ],
    [
        'id' => 12,
        'title' => 'Sarafi and Money Exchange',
        'category' => 'suppliers',
        'description' => 'Process sarafi deposits, exchanges, hawala transactions, and withdrawals.',
        'duration' => '9:45',
        'level' => 'Advanced'
    ],
    [
        'id' => 13,
        'title' => 'Accounts and Main Accounts',
        'category' => 'finance',
        'description' => 'Set up and manage main accounts with multiple currency support.',
        'duration' => '7:00',
        'level' => 'Intermediate'
    ],
    [
        'id' => 14,
        'title' => 'Payment Management',
        'category' => 'finance',
        'description' => 'Process payments, manage payment details, and generate payment statements.',
        'duration' => '8:30',
        'level' => 'Intermediate'
    ],
    [
        'id' => 15,
        'title' => 'Journal Vouchers',
        'category' => 'finance',
        'description' => 'Create and manage journal vouchers for client-supplier transactions.',
        'duration' => '10:00',
        'level' => 'Advanced'
    ],
    [
        'id' => 16,
        'title' => 'Financial Reports',
        'category' => 'finance',
        'description' => 'Generate comprehensive financial reports, balance sheets, and profit/loss statements.',
        'duration' => '12:00',
        'level' => 'Advanced'
    ],
    [
        'id' => 17,
        'title' => 'Employee Management',
        'category' => 'hr',
        'description' => 'Add, edit, and manage employee records and personal information.',
        'duration' => '6:45',
        'level' => 'Beginner'
    ],
    [
        'id' => 18,
        'title' => 'Salary Management',
        'category' => 'hr',
        'description' => 'Configure salary structures, manage bonuses, deductions, and salary advances.',
        'duration' => '10:30',
        'level' => 'Intermediate'
    ],
    [
        'id' => 19,
        'title' => 'Payroll Processing',
        'category' => 'hr',
        'description' => 'Process monthly payroll, generate payslips, and manage salary payments.',
        'duration' => '11:00',
        'level' => 'Advanced'
    ],
    [
        'id' => 20,
        'title' => 'Employee Performance Tracking',
        'category' => 'hr',
        'description' => 'Monitor employee performance metrics, commissions, and productivity.',
        'duration' => '8:45',
        'level' => 'Intermediate'
    ],
    [
        'id' => 21,
        'title' => 'Umrah Bookings',
        'category' => 'services',
        'description' => 'Manage umrah package bookings, itineraries, and customer details.',
        'duration' => '9:00',
        'level' => 'Intermediate'
    ],
    [
        'id' => 22,
        'title' => 'Visa Applications',
        'category' => 'services',
        'description' => 'Process visa applications, track status, and manage visa-related documents.',
        'duration' => '8:30',
        'level' => 'Intermediate'
    ],
    [
        'id' => 23,
        'title' => 'Hotel Bookings and Refunds',
        'category' => 'services',
        'description' => 'Manage hotel reservations, rates, and process hotel refunds.',
        'duration' => '7:30',
        'level' => 'Intermediate'
    ],
    [
        'id' => 24,
        'title' => 'Additional Services',
        'category' => 'services',
        'description' => 'Configure and manage additional services and payments.',
        'duration' => '6:00',
        'level' => 'Beginner'
    ],
    [
        'id' => 25,
        'title' => 'Dashboard Reports',
        'category' => 'reports',
        'description' => 'Understand sales trends, due amounts, and top performers on the dashboard.',
        'duration' => '6:30',
        'level' => 'Beginner'
    ],
    [
        'id' => 26,
        'title' => 'Running Monthly Reports',
        'category' => 'reports',
        'description' => 'Generate and export monthly reports for analysis and compliance.',
        'duration' => '7:45',
        'level' => 'Intermediate'
    ],
    [
        'id' => 27,
        'title' => 'Email Analytics',
        'category' => 'reports',
        'description' => 'Track email campaigns, delivery status, and engagement metrics.',
        'duration' => '6:15',
        'level' => 'Intermediate'
    ],
    [
        'id' => 28,
        'title' => 'Activity and Audit Logs',
        'category' => 'reports',
        'description' => 'Monitor system activity, audit trails, and track user actions.',
        'duration' => '5:45',
        'level' => 'Advanced'
    ],
    [
        'id' => 29,
        'title' => 'User Management and Roles',
        'category' => 'settings',
        'description' => 'Create users, assign roles, set permissions, and manage access levels.',
        'duration' => '9:30',
        'level' => 'Advanced'
    ],
    [
        'id' => 30,
        'title' => 'System Settings and Configuration',
        'category' => 'settings',
        'description' => 'Configure system preferences, business rules, and operational settings.',
        'duration' => '10:00',
        'level' => 'Advanced'
    ],
    [
        'id' => 31,
        'title' => 'Chat and Communication Settings',
        'category' => 'settings',
        'description' => 'Configure chat settings, message management, and communication preferences.',
        'duration' => '5:30',
        'level' => 'Beginner'
    ],
    [
        'id' => 32,
        'title' => 'Security Settings',
        'category' => 'security',
        'description' => 'Configure two-factor authentication, IP blacklisting, and rate limiting.',
        'duration' => '8:45',
        'level' => 'Advanced'
    ],
    [
        'id' => 33,
        'title' => 'Data Import and Export',
        'category' => 'security',
        'description' => 'Safely import and export data using Excel templates and batch operations.',
        'duration' => '9:00',
        'level' => 'Intermediate'
    ],
    [
        'id' => 34,
        'title' => 'Compliance Reporting',
        'category' => 'security',
        'description' => 'Generate compliance reports and ensure regulatory requirements are met.',
        'duration' => '10:30',
        'level' => 'Advanced'
    ],
    [
        'id' => 35,
        'title' => 'API Documentation',
        'category' => 'security',
        'description' => 'Understand API endpoints, authentication, and integration capabilities.',
        'duration' => '12:00',
        'level' => 'Advanced'
    ]
];

// Group tutorials by category
$by_category = [];
foreach ($tutorials as $tutorial) {
    if (!isset($by_category[$tutorial['category']])) {
        $by_category[$tutorial['category']] = [];
    }
    $by_category[$tutorial['category']][] = $tutorial;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutorial Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tutorial-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .category-section {
            margin-bottom: 30px;
        }
        .category-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 3px solid #4680ff;
            padding-bottom: 10px;
        }
        .tutorial-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #4680ff;
            border-radius: 4px;
        }
        .tutorial-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .tutorial-meta {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="row">
            <div class="col-md-12">
                <h1 class="mb-4">
                    <i class="icon">📺</i> Tutorial Manager
                </h1>
                <div class="alert alert-info">
                    <h5>Instructions:</h5>
                    <p>Below is a complete list of all tutorials. To add Vimeo video IDs:</p>
                    <ol>
                        <li>Copy the PHP code snippet below</li>
                        <li>Update each tutorial with its corresponding Vimeo ID</li>
                        <li>Replace the empty <code>'vimeo_id' => ''</code> values with your video IDs</li>
                        <li>Save the file at <code>/admin/tutorial.php</code></li>
                    </ol>
                </div>

                <div class="tutorial-list">
                    <h3 class="mb-4">Tutorial Structure Reference</h3>
                    
                    <?php foreach ($by_category as $category => $items): ?>
                    <div class="category-section">
                        <div class="category-title"><?= ucfirst(str_replace('_', ' ', $category)) ?> (<?= count($items) ?> tutorials)</div>
                        
                        <?php foreach ($items as $tutorial): ?>
                        <div class="tutorial-item">
                            <div class="tutorial-title">
                                #<?= $tutorial['id'] ?> - <?= htmlspecialchars($tutorial['title']) ?>
                            </div>
                            <div class="tutorial-meta">
                                <strong>Category:</strong> <?= htmlspecialchars($tutorial['category']) ?> | 
                                <strong>Level:</strong> <span class="badge badge-secondary"><?= htmlspecialchars($tutorial['level']) ?></span> | 
                                <strong>Duration:</strong> <?= htmlspecialchars($tutorial['duration']) ?>
                            </div>
                            <p class="mb-2">
                                <small class="text-muted">
                                    <?= htmlspecialchars($tutorial['description']) ?>
                                </small>
                            </p>
                            <div class="code-block">
[<br>
&nbsp;&nbsp;&nbsp;&nbsp;'id' => <?= $tutorial['id'] ?>,<br>
&nbsp;&nbsp;&nbsp;&nbsp;'title' => '<?= htmlspecialchars($tutorial['title']) ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'category' => '<?= htmlspecialchars($tutorial['category']) ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'description' => '<?= htmlspecialchars($tutorial['description']) ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'vimeo_id' => '<span style="background-color: #ffeb3b;">YOUR_VIMEO_ID_HERE</span>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'duration' => '<?= htmlspecialchars($tutorial['duration']) ?>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'level' => '<?= htmlspecialchars($tutorial['level']) ?>'<br>
],
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-success mt-4">
                    <h5>Next Steps:</h5>
                    <ul>
                        <li>Create or upload your tutorial videos to Vimeo</li>
                        <li>Note down the Vimeo video IDs (found in the URL or embed code)</li>
                        <li>Update the tutorial.php file with the video IDs</li>
                        <li>Visit <a href="tutorial.php">/admin/tutorial.php</a> to view the tutorials</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
