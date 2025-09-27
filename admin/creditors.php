<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}


$userName = $user['name'] ?? 'Guest';
$userRole = $user['role'] ?? 'User';
$userEmail = $user['email'] ?? '';
$userPhone = $user['phone'] ?? '';
$userAddress = $user['address'] ?? '';
$userHireDate = isset($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set';
$userCreatedAt = isset($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available';

// Handle new creditor submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_creditor'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $balance = $_POST['balance'];
    $currency = $_POST['currency'];
    $main_account_id = $_POST['main_account_id']; // This is now always required
    $skip_main_account = isset($_POST['skip_main_account']);
    
    try {
        $conn->begin_transaction();
        
        // Insert the creditor
        $stmt = $conn->prepare("INSERT INTO creditors (name, email, phone, address, balance, currency, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $name, $email, $phone, $address, $balance, $currency, $tenant_id);
        $stmt->execute();
        $creditor_id = $conn->insert_id;

        // Only add to main account balance and create transaction if not skipped
        if (!$skip_main_account) {
            // Get main account balance column name based on currency
            $balance_column = strtolower($currency) . '_balance';
            if ($currency == 'DARHAM') {
                $balance_column = 'darham_balance';
            } elseif ($currency == 'EUR') {
                $balance_column = 'euro_balance';
            } elseif ($currency == 'USD') {
                $balance_column = 'usd_balance';
            } elseif ($currency == 'AFS') {
                $balance_column = 'afs_balance';
            }

            // Get current main account balance
            $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $main_account_id, $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $main_account = $result->fetch_assoc();

            if (!$main_account) {
                throw new Exception(__("main_account_not_found"));
            }

            // Update main account balance (add the creditor amount)
            $new_main_balance = $main_account[$balance_column] + $balance;
            $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $new_main_balance, $main_account_id, $tenant_id);
            $stmt->execute();

            // Create main account transaction record
            $transaction_type = 'credit';
            $description = "Initial credit balance for creditor: $name";
            $tranasction_of = 'creditor';
            
            $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssssii", $main_account_id, $balance, $new_main_balance, $currency, $transaction_type, $description, $tranasction_of, $creditor_id, $tenant_id);
            $stmt->execute();

            $_SESSION['success_message'] = __("creditor_added_successfully_with_main_account_transaction");
        } else {
            $_SESSION['success_message'] = __("creditor_added_successfully_skipped_main_account_transaction");
        }
        
        $conn->commit();
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = __("error_adding_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $creditor_id = $_POST['creditor_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $creditor_currency = isset($_POST['creditor_currency']) ? $_POST['creditor_currency'] : $currency;
    $payment_date = $_POST['payment_date'];
    $receipt = $_POST['receipt'];
    $transaction_type = 'debit';
    $description = $_POST['description'];
    $paid_to = $_POST['paid_to'];

    try {
        $conn->begin_transaction();
        
        // Get creditor information
        $stmt = $conn->prepare("SELECT balance, currency FROM creditors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $creditor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $creditor = $result->fetch_assoc();
        
        // If the payment currency is different from the creditor's currency, apply exchange rate
        $converted_amount = $amount;
        $exchange_info = '';
        if ($currency !== $creditor_currency) {
            if (!isset($_POST['exchange_rate']) || empty($_POST['exchange_rate'])) {
                throw new Exception(__("exchange_rate_is_required_when_currencies_are_different"));
            }
            $exchange_rate = floatval($_POST['exchange_rate']);
            // Conversion logic: if payment is in AFS and creditor is USD, divide. If payment is USD and creditor is AFS, multiply.
            if ($currency == 'AFS' && $creditor_currency == 'USD') {
                $converted_amount = $amount / $exchange_rate;
                $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $creditor_currency)";
            } elseif ($currency == 'USD' && $creditor_currency == 'AFS') {
                $converted_amount = $amount * $exchange_rate;
                $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $creditor_currency)";
            } else {
                // General rule: if creditor currency is AFS, multiply; if payment currency is AFS, divide
                if ($creditor_currency == 'AFS') {
                    $converted_amount = $amount * $exchange_rate;
                    $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $creditor_currency)";
                } elseif ($currency == 'AFS') {
                    $converted_amount = $amount / $exchange_rate;
                    $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $creditor_currency)";
                } else {
                    // Fallback: multiply
                    $converted_amount = $amount * $exchange_rate;
                    $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $creditor_currency)";
                }
            }
            $description .= $exchange_info;
        }
        
        if ($creditor['balance'] >= $converted_amount) {
            // Create creditor transaction record
            $stmt = $conn->prepare("INSERT INTO creditor_transactions (creditor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssssi", $creditor_id, $converted_amount, $creditor_currency, $transaction_type, $description, $payment_date, $receipt, $tenant_id);
            $stmt->execute();
            $creditor_transaction_id = $conn->insert_id;
            
            // Update creditor balance
            $new_balance = $creditor['balance'] - $converted_amount;
            $stmt = $conn->prepare("UPDATE creditors SET balance = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $new_balance, $creditor_id, $tenant_id);
            $stmt->execute();

            // Check if balance is 0 and update status to inactive
            if ($new_balance == 0) {
                $stmt = $conn->prepare("UPDATE creditors SET status = 'inactive' WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("ii", $creditor_id, $tenant_id);
                $stmt->execute();
            }
            
            // Get main account balance column name for the specific currency
            $balance_column = strtolower($currency) . '_balance';
            if ($currency == 'DARHAM') {
                $balance_column = 'darham_balance';
            } elseif ($currency == 'EUR') {
                $balance_column = 'euro_balance';
            } elseif ($currency == 'USD') {
                $balance_column = 'usd_balance';
            } elseif ($currency == 'AFS') {
                $balance_column = 'afs_balance';
            }
            
            // Get current main account balance
            $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $paid_to, $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $main_account = $result->fetch_assoc();
            
            if (!$main_account) {
                throw new Exception(__("main_account_not_found"));
            }
            
            // Update main account balance (subtract original amount)
            $new_main_balance = $main_account[$balance_column] - $amount;
            $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $new_main_balance, $paid_to, $tenant_id);
            $stmt->execute();
            $tranasction_of = 'creditor';
            // Create main account transaction
            $main_transaction_description = $description;
            $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssssii", $paid_to, $amount, $new_main_balance, $currency, $transaction_type, $main_transaction_description, $tranasction_of, $creditor_transaction_id, $tenant_id);
            $stmt->execute();
            
            // Get the last inserted ID for the main account transaction
            $main_transaction_id = $conn->insert_id;

            // Create notification
            $notificationMessage = sprintf(
                "Payment made to creditor: %s - Amount %s %.2f",
                $creditor['name'],
                $currency,
                $amount
            );

            $notifStmt = $conn->prepare("
                INSERT INTO notifications 
                (transaction_id, transaction_type, message, status, created_at, tenant_id) 
                VALUES (?, 'creditor', ?, 'Unread', NOW(), ?)
            ");
            
            if (!$notifStmt->execute([$main_transaction_id, $notificationMessage, $tenant_id])) {
                throw new Exception("Failed to create notification");
            }
            
            $conn->commit();
            $_SESSION['success_message'] = __("payment_processed_successfully");
        } else {
            throw new Exception(__("insufficient_balance"));
        }
        
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = __("error_processing_payment") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle transaction deletion and reversal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $transaction_id = $_POST['transaction_id'];
    $creditor_id = $_POST['creditor_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    
    try {
        $conn->begin_transaction();
        
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM creditor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception(__("transaction_not_found"));
        }
        
        // Get the linked main account transaction
        $stmt = $conn->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'creditor' AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $main_transaction = $result->fetch_assoc();
        
        if (!$main_transaction) {
            throw new Exception(__("main_account_transaction_not_found"));
        }

        // Use main account transaction's amount and currency for all main account updates
        $main_amount = $main_transaction['amount'];
        $main_currency = $main_transaction['currency'];

        // Update balances of all subsequent transactions
        $updateSubsequentStmt = $conn->prepare("
            UPDATE main_account_transactions 
            SET balance = balance + ?
            WHERE main_account_id = ? 
            AND currency = ? 
            AND created_at > ? 
            AND id != ?
            AND tenant_id = ?
        ");
        $updateSubsequentStmt->bind_param("dsssii", $main_amount, $main_transaction['main_account_id'], $main_currency, $main_transaction['created_at'], $main_transaction['id'], $tenant_id);
        $updateSubsequentStmt->execute();
        
        // Get creditor information
        $stmt = $conn->prepare("SELECT balance FROM creditors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $creditor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $creditor = $result->fetch_assoc();
        
        // Update creditor balance (add amount back)
        $new_balance = $creditor['balance'] + $transaction['amount'];
        $stmt = $conn->prepare("UPDATE creditors SET balance = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $new_balance, $creditor_id, $tenant_id);
        $stmt->execute();
        
        // Get main account info and update the correct currency balance
        $balance_column = strtolower($main_currency) . '_balance';
        if ($main_currency == 'DARHAM') {
            $balance_column = 'darham_balance';
        } elseif ($main_currency == 'EUR') {
            $balance_column = 'euro_balance';
        } elseif ($main_currency == 'USD') {
            $balance_column = 'usd_balance';
        } elseif ($main_currency == 'AFS') {
            $balance_column = 'afs_balance';
        }
        
        // Get current main account balance
        $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_transaction['main_account_id'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $main_account = $result->fetch_assoc();
        
        if (!$main_account) {
            throw new Exception(__("main_account_not_found"));
        }
        
        // Update main account balance (add main transaction amount back)
        $new_main_balance = $main_account[$balance_column] + $main_amount;
        $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $new_main_balance, $main_transaction['main_account_id'], $tenant_id);
        $stmt->execute();
        
        // Delete the transactions
        $stmt = $conn->prepare("DELETE FROM creditor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_transaction['id'], $tenant_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = __("transaction_reversed_and_deleted_successfully");
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = __("error_reversing_transaction") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle creditor editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_creditor'])) {
    $creditor_id = $_POST['creditor_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $new_balance = $_POST['balance'];
    $new_currency = $_POST['currency'];
    
    try {
        $conn->begin_transaction();
        
        // Get current creditor information
        $stmt = $conn->prepare("SELECT balance, currency FROM creditors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $creditor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_creditor = $result->fetch_assoc();
        
        if (!$current_creditor) {
            throw new Exception(__("creditor_not_found"));
        }

        // Check if creditor has any main account transactions
        $stmt = $conn->prepare("SELECT mt.*, ma.id as main_account_id 
                              FROM main_account_transactions mt 
                              JOIN main_account ma ON mt.main_account_id = ma.id 
                              WHERE mt.transaction_of = 'creditor' 
                              AND mt.reference_id = ? 
                              AND mt.type = 'credit'
                              AND mt.tenant_id = ?
                              ORDER BY mt.created_at ASC LIMIT 1");
        $stmt->bind_param("ii", $creditor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $initial_transaction = $result->fetch_assoc();

        // Update creditor information
        $stmt = $conn->prepare("UPDATE creditors SET name = ?, email = ?, phone = ?, address = ?, balance = ?, currency = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ssssdsii", $name, $email, $phone, $address, $new_balance, $new_currency, $creditor_id, $tenant_id);
        $stmt->execute();

        // If creditor has main account transactions, update the transactions and balances
        if ($initial_transaction) {
            $balance_difference = $new_balance - $current_creditor['balance'];
            
            if ($balance_difference != 0) {
                // Get main account balance column based on currency
                $balance_column = strtolower($new_currency) . '_balance';
                if ($new_currency == 'DARHAM') {
                    $balance_column = 'darham_balance';
                } elseif ($new_currency == 'EUR') {
                    $balance_column = 'euro_balance';
                } elseif ($new_currency == 'USD') {
                    $balance_column = 'usd_balance';
                } elseif ($new_currency == 'AFS') {
                    $balance_column = 'afs_balance';
                }

                // Get current main account balance
                $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("ii", $initial_transaction['main_account_id'], $tenant_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $main_account = $result->fetch_assoc();

                if (!$main_account) {
                    throw new Exception(__("main_account_not_found"));
                }

                // Update the initial transaction amount and currency
                $new_transaction_amount = $initial_transaction['amount'] + $balance_difference;
                $stmt = $conn->prepare("UPDATE main_account_transactions 
                                      SET amount = ?, currency = ?, balance = balance + ? 
                                      WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("dsdi", $new_transaction_amount, $new_currency, $balance_difference, $initial_transaction['id'], $tenant_id);
                $stmt->execute();

                // Update all subsequent transaction balances in a single query
                $stmt = $conn->prepare("
                    UPDATE main_account_transactions 
                    SET balance = balance + ?
                    WHERE main_account_id = ? 
                    AND currency = ? 
                    AND created_at > ? 
                    AND id != ?
                    AND tenant_id = ?
                ");
                $stmt->bind_param("dissi", $balance_difference, $initial_transaction['main_account_id'], $new_currency, $initial_transaction['created_at'], $initial_transaction['id'], $tenant_id);
                $stmt->execute();

                // Update the main account's current balance
                $stmt = $conn->prepare("UPDATE main_account SET $balance_column = $balance_column + ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("di", $balance_difference, $initial_transaction['main_account_id'], $tenant_id);
                $stmt->execute();

                $_SESSION['success_message'] = __("creditor_updated_and_all_transactions_recalculated_successfully");
            } else {
                $_SESSION['success_message'] = __("creditor_information_updated_successfully");
            }
        } else {
            $_SESSION['success_message'] = __("creditor_information_updated_successfully_no_main_account_transaction_found");
        }
        
        $conn->commit();
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = __("error_updating_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Determine which status to display
$status_filter = isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'inactive' : 'active';

// Fetch creditors based on status filter
$stmt = $conn->prepare("SELECT * FROM creditors WHERE status = ? AND tenant_id = ? ORDER BY created_at DESC");
$stmt->bind_param("si", $status_filter, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$creditors = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total credits by currency
$currency_totals = [];
if (count($creditors) > 0) {
    foreach ($creditors as $creditor) {
        $currency = $creditor['currency'];
        $balance = $creditor['balance'];
        
        if (!isset($currency_totals[$currency])) {
            $currency_totals[$currency] = 0;
        }
        
        $currency_totals[$currency] += $balance;
    }
}

// Fetch main accounts for payment form
$stmt = $pdo->prepare("SELECT id, name FROM main_account where status = 'active' AND tenant_id = ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->execute();
$main_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">
<style>
    /* Modern Dashboard Styling */
    :root {
        --primary-color: #4099ff;
        --secondary-color: #2ed8b6;
        --danger-color: #ff5370;
        --warning-color: #ffb64d;
        --success-color: #2ed8b6;
        --dark-color: #222;
        --light-color: #f8f9fa;
        --border-radius: 8px;
        --box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        --transition: all 0.3s ease;
    }

    /* General Layout Improvements */
    .pcoded-main-container {
        background-color: #f8f9fa;
        padding: 20px;
    }

    .page-wrapper {
        margin-top: 20px;
    }

    /* Card Enhancements */
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        margin-bottom: 24px;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .card-header {
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.25rem;
        background: white;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    /* Summary Cards */
    .summary-card {
        padding: 1.5rem;
        border-radius: var(--border-radius);
        background: linear-gradient(45deg, var(--primary-color), #73b4ff);
        color: white;
        margin-bottom: 20px;
    }

    .summary-card h3 {
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }

    /* Table Enhancements */
    .table {
        margin-bottom: 0;
    }

    .table thead th {
        border-top: none;
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }

    .table td {
        vertical-align: middle;
        padding: 1rem;
    }

    /* Button Styling */
    .btn {
        border-radius: 50px;
        padding: 0.5rem 1.25rem;
        font-weight: 500;
        transition: var(--transition);
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 2px;
    }

    .btn-primary {
        background: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-success {
        background: var(--success-color);
        border-color: var(--success-color);
    }

    /* Status Badge Styling */
    .badge {
        padding: 0.5em 1em;
        border-radius: 50px;
        font-weight: 500;
    }

    .badge-light-primary {
        background-color: rgba(64, 153, 255, 0.1);
        color: var(--primary-color);
    }

    /* Search and Filter Styling */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #dee2e6;
        border-radius: 50px;
        padding: 8px 16px;
        padding-left: 40px;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>') no-repeat 16px center;
    }

    /* Avatar Styling */
    .avatar {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
    }

    /* Modal Enhancements */
    .modal-content {
        border: none;
        border-radius: var(--border-radius);
    }

    .modal-header {
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem;
    }

    .modal-footer {
        border-top: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem;
    }

    /* Form Styling */
    .form-control {
        border-radius: var(--border-radius);
        padding: 0.75rem 1rem;
        border: 1px solid #dee2e6;
        transition: var(--transition);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    /* Navigation Tabs */
    .nav-tabs {
        border-bottom: none;
        margin-bottom: 1.5rem;
    }

    .nav-tabs .nav-link {
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        color: #6c757d;
        transition: var(--transition);
    }

    .nav-tabs .nav-link.active {
        background-color: var(--primary-color);
        color: white;
    }

    /* Empty State Styling */
    .empty-state {
        padding: 3rem;
        text-align: center;
    }

    .empty-state i {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .btn-icon {
            width: 28px;
            height: 28px;
        }
        
        .table td {
            padding: 0.75rem;
        }
        
        .card-header {
            padding: 1rem;
        }
    }

    /* Animation Effects */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .card {
        animation: fadeIn 0.3s ease-out;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Toast Notifications */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        background: white;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 10px;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        animation: slideIn 0.3s ease-out;
        transition: all 0.3s ease;
    }

    .toast.success {
        border-left: 4px solid var(--success-color);
    }

    .toast.error {
        border-left: 4px solid var(--danger-color);
    }

    .toast.warning {
        border-left: 4px solid var(--warning-color);
    }

    .toast-content {
        display: flex;
        align-items: center;
    }

    .toast-icon {
        margin-right: 12px;
        font-size: 20px;
    }

    .toast.success .toast-icon {
        color: var(--success-color);
    }

    .toast.error .toast-icon {
        color: var(--danger-color);
    }

    .toast.warning .toast-icon {
        color: var(--warning-color);
    }

    .toast-message {
        color: var(--dark-color);
        font-size: 14px;
        margin: 0;
    }

    .toast-close {
        color: #6c757d;
        background: none;
        border: none;
        padding: 0;
        margin-left: 15px;
        cursor: pointer;
        font-size: 18px;
        opacity: 0.7;
        transition: opacity 0.3s ease;
    }

    .toast-close:hover {
        opacity: 1;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    /* ... existing styles ... */
</style>
<style>
    /* Apply gradient background to card headers matching the sidebar */
    .card-header {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        color: #ffffff !important;
        border-bottom: none !important;
    }

    .card-header h5 {
        color: #ffffff !important;
        margin-bottom: 0 !important;
    }

    .card-header .card-header-right {
        color: #ffffff !important;
    }

    .card-header .card-header-right .btn {
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
    }

    .card-header .card-header-right .btn:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
    }
</style>
<!-- Add this right before the closing </body> tag -->
<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Toast JavaScript -->
<script>
    class Toast {
        constructor() {
            this.container = document.querySelector('.toast-container');
        }

        show(message, type = 'success', duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            let icon = '';
            switch(type) {
                case 'success':
                    icon = 'check-circle';
                    break;
                case 'error':
                    icon = 'alert-circle';
                    break;
                case 'warning':
                    icon = 'alert-triangle';
                    break;
                default:
                    icon = 'info';
            }

            toast.innerHTML = `
                <div class="toast-content">
                    <i class="feather icon-${icon} toast-icon"></i>
                    <p class="toast-message">${message}</p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="feather icon-x"></i>
                </button>
            `;

            this.container.appendChild(toast);

            // Auto remove after duration
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);

            // Remove on click
            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            });
        }
    }

    // Initialize toast
    const toast = new Toast();

    // Show toasts if there are any messages
    <?php if (isset($success_message)): ?>
        toast.show('<?php echo addslashes($success_message); ?>', 'success');
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        toast.show('<?php echo addslashes($error_message); ?>', 'error');
    <?php endif; ?>

    // Remove the old alert divs
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => alert.remove());
    });
</script>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="container mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2><?= __("creditors_management") ?></h2>
                                    
                                </div>
                                
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo h($success_message); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                                <?php endif; ?>
                                
                                <!-- Total Credits by Currency Section -->
                                <?php if (!empty($currency_totals)): ?>
                                <div class="row">
                                    <?php foreach ($currency_totals as $currency => $total): ?>
                                    <div class="col-md-3 col-sm-6 mb-4">
                                        <div class="summary-card h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="currency-icon me-3">
                                                    <i class="feather icon-credit-card" style="font-size: 2rem;"></i>
                                                </div>
                                                <div>
                                                    <h3 class="mb-1"><?php echo number_format($total, 2); ?></h3>
                                                    <p class="mb-0 text-white-50"><?php echo htmlspecialchars($currency); ?> Total</p>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="progress" style="height: 4px; background: rgba(255,255,255,0.2);">
                                                    <div class="progress-bar bg-white" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status Toggle Tabs -->
                                <div class="card mb-4">
                                    <div class="card-body p-0">
                                        <ul class="nav nav-tabs nav-fill">
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo h($status_filter) === 'active' ? 'active' : ''; ?>" href="creditors.php">
                                                    <i class="feather icon-user-check mr-2"></i><?= __("active_creditors") ?>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?>" href="creditors.php?status=inactive">
                                                    <i class="feather icon-user-minus mr-2"></i><?= __("inactive_creditors") ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Creditors Table -->
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="feather icon-users mr-2"></i><?= __($status_filter . '_creditors') ?>
                                        </h5>
                                        <button type="button" class="btn btn-success d-flex align-items-center" data-toggle="modal" data-target="#addCreditorModal">
                                            <i class="feather icon-plus-circle mr-2"></i> <?= __("add_new_creditor") ?>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="creditorsTable" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th><?= __("name") ?></th>
                                                        <th><?= __("email") ?></th>
                                                        <th><?= __("phone") ?></th>
                                                        <th><?= __("address") ?></th>
                                                        <th><?= __("balance") ?></th>
                                                        <th><?= __("currency") ?></th>
                                                        <th class="text-center no-sort"><?= __("actions") ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($creditors) > 0): ?>
                                                        <?php foreach ($creditors as $creditor): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="avatar bg-light-primary text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($creditor['name'], 0, 1)); ?>
                                                                        </div>
                                                                        <div class="ml-3">
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($creditor['name']); ?></h6>
                                                                            <small class="text-muted">ID: <?php echo h($creditor['id']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['email'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-mail text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['email']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['phone'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-phone text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['phone']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['address'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-map-pin text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['address']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="font-weight-medium <?php echo $creditor['balance'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                                            <?php echo number_format($creditor['balance'], 2); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-light-primary">
                                                                        <?php echo htmlspecialchars($creditor['currency']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex justify-content-center">
                                                                        <button type="button" class="btn btn-icon btn-primary" 
                                                                                data-toggle="modal" 
                                                                                data-target="#paymentModal_<?php echo h($creditor['id']); ?>" 
                                                                                title="<?= __("process_payment") ?>">
                                                                            <i class="feather icon-credit-card"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-icon btn-info" 
                                                                                data-toggle="modal" 
                                                                                data-target="#transactionsModal_<?php echo h($creditor['id']); ?>" 
                                                                                title="<?= __("view_transactions") ?>">
                                                                            <i class="feather icon-list"></i>
                                                                        </button>
                                                                        <a href="print_creditor_statement.php?id=<?php echo h($creditor['id']); ?>" 
                                                                           class="btn btn-icon btn-secondary"
                                                                           target="_blank"
                                                                           title="<?= __("print_statement") ?>">
                                                                            <i class="feather icon-printer"></i>
                                                                        </a>
                                                                        <button type="button" class="btn btn-icon btn-warning" 
                                                                                data-toggle="modal" 
                                                                                data-target="#editCreditorModal_<?php echo h($creditor['id']); ?>" 
                                                                                title="<?= __("edit_creditor") ?>">
                                                                            <i class="feather icon-edit-2"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-icon btn-danger" 
                                                                                data-toggle="modal" 
                                                                                data-target="#deleteCreditorModal_<?php echo h($creditor['id']); ?>" 
                                                                                title="<?= __("delete_creditor") ?>">
                                                                            <i class="feather icon-trash-2"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7">
                                                                <div class="empty-state">
                                                                    <i class="feather icon-users"></i>
                                                                    <h5 class="mt-3"><?= __("no_creditors_found") ?></h5>
                                                                    <p class="text-muted">
                                                                        <?php if ($status_filter === 'active'): ?>
                                                                            <?= __("add_new_creditors_to_start_tracking_your_credits") ?>
                                                                        <?php else: ?>
                                                                            <?= __("deactivated_creditors_will_appear_here") ?>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Creditor Modal -->
    <div class="modal fade" id="addCreditorModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-user-plus mr-2"></i><?= __("add_new_creditor") ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label class="small text-muted mb-1"><?= __("name") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("email") ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-mail"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("phone") ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-phone"></i></span>
                                    </div>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted mb-1"><?= __("address") ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-map-pin"></i></span>
                                </div>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("initial_balance") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                                    <input type="number" class="form-control" name="balance" step="0.01" required>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("currency") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-credit-card"></i></span>
                                    </div>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __("usd") ?></option>
                                        <option value="AFS"><?= __("afs") ?></option>
                                        <option value="EUR"><?= __("eur") ?></option>
                                        <option value="DARHAM"><?= __("darham") ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted mb-1"><?= __("main_account") ?> *</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-briefcase"></i></span>
                                </div>
                                <select class="form-control" name="main_account_id" id="mainAccountSelect" required>
                                    <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="skipMainAccount" name="skip_main_account">
                            <label class="custom-control-label small" for="skipMainAccount">
                                <?= __("skip_adding_to_main_account_balance_and_transaction_record") ?>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-link" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __("cancel") ?>
                        </button>
                        <button type="submit" name="add_creditor" class="btn btn-success">
                            <i class="feather icon-check-circle mr-2"></i><?= __("add_creditor") ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

      <!-- Profile Modal -->
      <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __("user_profile") ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("email") ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("phone") ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("join_date") ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("address") ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __("account_information") ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __("account_created") ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __("close") ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __("profile_settings") ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __("click_to_change_profile_picture") ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __("personal_information") ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __("full_name") ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __("email_address") ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __("phone_number") ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __("address") ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __("change_password") ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __("current_password") ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __("new_password") ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __("confirm_password") ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __("cancel") ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __("save_changes") ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
    
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable for creditors
        $('#creditorsTable').DataTable({
            responsive: true,
            language: {
                search: "<?= __('search') ?>:",
                lengthMenu: "<?= __('show') ?> _MENU_ <?= __('entries') ?>",
                info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_ <?= __('entries') ?>",
                infoEmpty: "<?= __('showing') ?> 0 <?= __('to') ?> 0 <?= __('of') ?> 0 <?= __('entries') ?>",
                infoFiltered: "(<?= __('filtered_from') ?> _MAX_ <?= __('total_entries') ?>)",
                paginate: {
                    first: "<?= __('first') ?>",
                    last: "<?= __('last') ?>",
                    next: "<?= __('next') ?>",
                    previous: "<?= __('previous') ?>"
                }
            },
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "<?= __('all') ?>"]],
            columnDefs: [
                { targets: 'no-sort', orderable: false }
            ],
            order: [[0, 'asc']]
        });
        
        // Initialize DataTables for transaction tables
        $('.transaction-table').each(function() {
            $(this).DataTable({
                responsive: true,
                language: {
                    search: "<?= __('search') ?>:",
                    lengthMenu: "<?= __('show') ?> _MENU_",
                    info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_",
                    infoEmpty: "<?= __('no_records') ?>",
                    paginate: {
                        next: "<?= __('next') ?>",
                        previous: "<?= __('previous') ?>"
                    }
                },
                pageLength: 5,
                lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "<?= __('all') ?>"]],
                columnDefs: [
                    { targets: 'no-sort', orderable: false }
                ],
                order: [[0, 'desc']]
            });
        });
        
        // Handle modal open events to fix DataTables layout issues
        $('body').on('shown.bs.modal', function(e) {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
        });
    });
    </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

    <!-- Modal initialization script -->
    <script>
    $(document).ready(function() {
        // Ensure modal buttons work correctly
        $('[data-toggle="modal"]').on('click', function() {
            var target = $(this).attr('data-target');
            $(target).modal('show');
        });
    });
    </script>

<script>
// Function to check currency and show/hide exchange rate field for creditors
function checkCreditorCurrency(selectElement, creditorCurrency, creditorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv_' + creditorId);
    const selectedCurrencySpan = document.getElementById('selectedCreditorCurrency_' + creditorId);
    const creditorCurrencySpan = document.getElementById('creditorCurrency_' + creditorId);
    const exchangeRateInput = document.getElementById('exchangeRate_' + creditorId);
    
    if (selectedCurrency !== creditorCurrency) {
        // Show exchange rate field
        exchangeRateDiv.style.display = 'block';
        selectedCurrencySpan.textContent = selectedCurrency;
        creditorCurrencySpan.textContent = creditorCurrency;
        exchangeRateInput.required = true;
    } else {
        // Hide exchange rate field
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
</script>

<?php foreach ($creditors as $creditor): ?>
    <!-- Transactions Modal -->
    <div class="modal fade" id="transactionsModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("transactions") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped transaction-table">
                            <thead>
                                <tr>
                                    <th><?= __("date") ?></th>
                                    <th><?= __("amount") ?></th>
                                    <th><?= __("type") ?></th>
                                    <th><?= __("description") ?></th>
                                    <th><?= __("receipt") ?></th>
                                    <th class="no-sort"><?= __("actions") ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch transactions for this creditor
                                $transStmt = $conn->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? ORDER BY payment_date DESC");
                                $transStmt->bind_param("i", $creditor['id']);
                                $transStmt->execute();
                                $transResult = $transStmt->get_result();
                                
                                if ($transResult->num_rows > 0) {
                                    while ($transaction = $transResult->fetch_assoc()) {
                                        echo '<tr>';
                                        // Ensure we display the exact date and time as stored in the database
                                        $dateTime = new DateTime($transaction['created_at']);
                                        echo '<td>' . $dateTime->format('Y-m-d H:i:s') . '</td>';
                                        echo '<td>' . number_format($transaction['amount'], 2) . ' ' . $transaction['currency'] . '</td>';
                                        echo '<td>' . ($transaction['transaction_type'] == 'debit' ? '<span class="badge badge-success">' . __("payment") . '</span>' : '<span class="badge badge-danger">' . __("credit") . '</span>') . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['reference_number']) . '</td>';
                                        echo '<td>';
                                        // Add edit button
                                        echo '<button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#editTransactionModal_' . $transaction['id'] . '"><i class="feather icon-edit"></i> ' . __("edit") . '</button>';
                                        echo '<form method="POST" onsubmit="return confirm(\'' . __("are_you_sure_you_want_to_delete_this_transaction_this_will_reverse_the_payment") . '\');">';
                                        echo '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
                                        echo '<input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">';
                                        echo '<input type="hidden" name="creditor_id" value="' . $creditor['id'] . '">';
                                        echo '<input type="hidden" name="amount" value="' . $transaction['amount'] . '">';
                                        echo '<input type="hidden" name="currency" value="' . $transaction['currency'] . '">';
                                        echo '<button type="submit" name="delete_transaction" class="btn btn-danger btn-sm"><i class="feather icon-trash"></i> ' . __("delete") . '</button>';
                                        echo '</form>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">' . __("no_transactions_found") . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("close") ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("process_payment") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        <div class="form-group">
                            <label><?= __("amount") ?> *</label>
                            <input type="number" class="form-control" name="amount" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_currency") ?> *</label>
                            <select class="form-control" name="currency" required onchange="checkCreditorCurrency(this, '<?php echo h($creditor['currency']); ?>', '<?php echo h($creditor['id']); ?>')">
                                <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                            </select>
                        </div>
                        <!-- Exchange Rate Field - Initially Hidden -->
                        <div class="form-group" id="exchangeRateDiv_<?php echo h($creditor['id']); ?>" style="display: none;">
                            <label>Exchange Rate (1 <span id="selectedCreditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span> = ? <span id="creditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span>)</label>
                            <input type="number" class="form-control" name="exchange_rate" id="exchangeRate_<?php echo h($creditor['id']); ?>" step="0.000001" placeholder="Enter exchange rate">
                            <small class="form-text text-muted">Enter the exchange rate to convert from payment currency to creditor's currency</small>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_date") ?> *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("receipt_number") ?></label>
                            <input type="text" class="form-control" name="receipt">
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("paid_from") ?> *</label>
                            <select class="form-control" name="paid_to" required>
                                <?php foreach ($main_accounts as $account): ?>
                                    <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="pay" class="btn btn-primary"><?= __("process_payment") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Creditor Modal -->
    <div class="modal fade" id="editCreditorModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_creditor") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <div class="form-group">
                            <label><?= __("name") ?> *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($creditor['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("email") ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($creditor['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("phone") ?></label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($creditor['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("address") ?></label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($creditor['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("balance") ?> *</label>
                            <input type="number" class="form-control" name="balance" step="0.01" value="<?php echo h($creditor['balance']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("currency") ?> *</label>
                            <select class="form-control" name="currency" required>
                                <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="edit_creditor" class="btn btn-primary"><?= __("save_changes") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Add Delete Creditor Modal for each creditor -->
<?php foreach ($creditors as $creditor): ?>
    <div class="modal fade" id="deleteCreditorModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><?= __("delete_creditor") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" onsubmit="return confirm('<?= __("are_you_sure_you_want_to_delete_this_creditor_this_action_cannot_be_undone") ?>');">
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_balance" value="<?php echo h($creditor['balance']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        <p><?= __("are_you_sure_you_want_to_delete_this_creditor") ?> <strong><?php echo htmlspecialchars($creditor['name']); ?></strong>?</p>
                        <p><?= __("current_balance") ?>: <strong><?php echo number_format($creditor['balance'], 2) . ' ' . h($creditor['currency']); ?></strong></p>
                        <?php if ($creditor['balance'] > 0): ?>
                            <div class="alert alert-warning">
                                <i class="feather icon-alert-triangle mr-2"></i>
                                <?= __("warning") ?>: <?= __("this_creditor_has_a_non_zero_balance_deleting_will_affect_main_account_balances_if_transactions_exist") ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="delete_creditor" class="btn btn-danger"><?= __("delete_creditor") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
// Validate edit_creditor
$edit_creditor = isset($_POST['edit_creditor']) ? DbSecurity::validateInput($_POST['edit_creditor'], 'string', ['maxlength' => 255]) : null;

// Add Edit Transaction Modals for each transaction
foreach ($creditors as $creditor): 
    // Fetch transactions for this creditor
    $transStmt = $conn->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? ORDER BY payment_date DESC");
    $transStmt->bind_param("i", $creditor['id']);
    $transStmt->execute();
    $transResult = $transStmt->get_result();
    
    while ($transaction = $transResult->fetch_assoc()):
?>
    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal_<?php echo $transaction['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_transaction") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm_<?php echo $transaction['id']; ?>" class="edit-transaction-form">
                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                        <input type="hidden" name="creditor_id" value="<?php echo $creditor['id']; ?>">
                        <input type="hidden" name="original_amount" value="<?php echo $transaction['amount']; ?>">
                        <input type="hidden" name="original_currency" value="<?php echo $transaction['currency']; ?>">
                        
                        <div class="form-group">
                            <label><?= __("amount") ?> *</label>
                            <input type="number" class="form-control" name="payment_amount" value="<?php echo $transaction['amount']; ?>" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_date_and_time") ?> *</label>
                            <div class="row">
                                <div class="col-md-7">
                                    <?php 
                                    // Ensure we get the proper date
                                    $datetime = new DateTime($transaction['created_at']);
                                    $formattedDate = $datetime->format('d/m/Y');
                                    ?>
                                    <input type="text" class="form-control" name="payment_date" 
                                           placeholder="DD/MM/YYYY" value="<?php echo $formattedDate; ?>" required>
                                    <small class="form-text text-muted"><?= __("format") ?>: DD/MM/YYYY</small>
                                </div>
                                <div class="col-md-5">
                                    <?php 
                                    // Get the time part
                                    $formattedTime = $datetime->format('H:i:s');
                                    ?>
                                    <input type="text" class="form-control" name="payment_time" 
                                           placeholder="HH:MM:SS" value="<?php echo $formattedTime; ?>" required>
                                    <small class="form-text text-muted"><?= __("format") ?>: HH:MM:SS</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?= __("reference_number") ?></label>
                            <input type="text" class="form-control" name="reference_number" value="<?php echo htmlspecialchars($transaction['reference_number']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" name="payment_description" rows="3"><?php echo htmlspecialchars($transaction['description']); ?></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle mr-2"></i>
                            <?= __("warning") ?>: <?= __("editing_a_transaction_will_recalculate_balances") ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                    <button type="button" class="btn btn-primary" onclick="updateCreditorTransaction(<?php echo $transaction['id']; ?>)"><?= __("save_changes") ?></button>
                </div>
            </div>
        </div>
    </div>
<?php 
    endwhile;
endforeach; 
?>

<!-- Validation code -->
<?php
// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

// Validate delete_transaction
$delete_transaction = isset($_POST['delete_transaction']) ? DbSecurity::validateInput($_POST['delete_transaction'], 'string', ['maxlength' => 255]) : null;

// Validate paid_to
$paid_to = isset($_POST['paid_to']) ? DbSecurity::validateInput($_POST['paid_to'], 'string', ['maxlength' => 255]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate creditor_id
$creditor_id = isset($_POST['creditor_id']) ? DbSecurity::validateInput($_POST['creditor_id'], 'int', ['min' => 0]) : null;

// Validate pay
$pay = isset($_POST['pay']) ? DbSecurity::validateInput($_POST['pay'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate balance
$balance = isset($_POST['balance']) ? DbSecurity::validateInput($_POST['balance'], 'float', ['min' => 0]) : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Validate add_creditor
$add_creditor = isset($_POST['add_creditor']) ? DbSecurity::validateInput($_POST['add_creditor'], 'string', ['maxlength' => 255]) : null;

// Add the delete creditor handler at the end of the file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_creditor'])) {
    $creditor_id = $_POST['creditor_id'];
    $creditor_balance = $_POST['creditor_balance'];
    $creditor_currency = $_POST['creditor_currency'];
    
    try {
        $conn->begin_transaction();
        
        // Check if creditor has any main account transactions
        $stmt = $conn->prepare("SELECT mt.*, ma.id as main_account_id 
                              FROM main_account_transactions mt 
                              JOIN main_account ma ON mt.main_account_id = ma.id 
                              WHERE mt.transaction_of = 'creditor' 
                              AND mt.reference_id = ? 
                              AND mt.type = 'credit'
                              ORDER BY mt.created_at ASC LIMIT 1");
        $stmt->bind_param("i", $creditor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $initial_transaction = $result->fetch_assoc();

        if ($initial_transaction) {
            // Get main account balance column based on currency
            $balance_column = strtolower($creditor_currency) . '_balance';
            if ($creditor_currency == 'DARHAM') {
                $balance_column = 'darham_balance';
            } elseif ($creditor_currency == 'EUR') {
                $balance_column = 'euro_balance';
            } elseif ($creditor_currency == 'USD') {
                $balance_column = 'usd_balance';
            } elseif ($creditor_currency == 'AFS') {
                $balance_column = 'afs_balance';
            }

            // Update all subsequent transaction balances to remove the creditor's balance
            $stmt = $conn->prepare("
                UPDATE main_account_transactions 
                SET balance = balance - ?
                WHERE main_account_id = ? 
                AND currency = ? 
                AND created_at > ? 
                AND id != ?
            ");
            $stmt->bind_param("dissi", $creditor_balance, $initial_transaction['main_account_id'], $creditor_currency, $initial_transaction['created_at'], $initial_transaction['id']);
            $stmt->execute();

            // Update main account balance
            $stmt = $conn->prepare("UPDATE main_account SET $balance_column = $balance_column - ? WHERE id = ?");
            $stmt->bind_param("di", $creditor_balance, $initial_transaction['main_account_id']);
            $stmt->execute();

            // Delete all transactions related to this creditor
            $stmt = $conn->prepare("DELETE FROM main_account_transactions WHERE transaction_of = 'creditor' AND reference_id = ?");
            $stmt->bind_param("i", $creditor_id);
            $stmt->execute();
        }

        // Delete the creditor
        $stmt = $conn->prepare("DELETE FROM creditors WHERE id = ?");
        $stmt->bind_param("i", $creditor_id);
        $stmt->execute();

        $conn->commit();
        $_SESSION['success_message'] = __("creditor_deleted_successfully");
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = __("error_deleting_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}
?>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<script>
// Function to update creditor transaction
function updateCreditorTransaction(transactionId) {
    // Get form data
    const form = document.getElementById('editTransactionForm_' + transactionId);
    const formData = new FormData(form);
    
    // Show loading indicator
    const saveButton = event.target;
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="feather icon-loader spinner"></i> ' + originalText;
    saveButton.disabled = true;
    
    // Send AJAX request
    fetch('ajax/update_creditor_transaction.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('<?= __("transaction_updated_successfully") ?>');
            // Reload the page to show updated data
            window.location.reload();
        } else {
            // Show error message
            alert('<?= __("error") ?>: ' + data.message);
            // Reset button
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?= __("error_occurred_during_update") ?>');
        // Reset button
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
    });
}
</script>

</body>
</html> 