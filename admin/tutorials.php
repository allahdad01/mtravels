<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get page title and other settings
$page_title = "Tutorials & Guides";
?>

<?php 
include '../includes/header.php';
?>

<!-- Add Bootstrap-select CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .tutorial-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .tutorial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .tutorial-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
        }
        
        .tutorial-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .step-item {
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .category-section {
            margin-bottom: 40px;
        }
        
        .bg-tickets { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-hotel { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .bg-visa { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-umrah { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .bg-finance { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .bg-reports { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .bg-system { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        
        .screenshot-placeholder {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #6c757d;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        
        .screenshot-placeholder:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-color: #495057;
            color: #495057;
        }
        
        .screenshot-placeholder i {
            font-size: 1.2em;
            color: #007bff;
        }
        
        /* Search and Filter Styles */
        .search-section .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        
        .search-section .input-group-text {
            border: none;
            border-radius: 8px 0 0 8px;
        }
        
        .search-section .form-control {
            border: 1px solid #e9ecef;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }
        
        .search-section .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .search-section select.form-control {
            border-radius: 8px;
        }
        
        /* Animation for filtered results */
        .fade-in {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Hidden sections */
        .category-section[style*="display: none"] {
            display: none !important;
        }
        
        /* Quick stats */
        .search-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .search-stats .stat-item {
            display: inline-block;
            margin: 0 20px;
        }
        
        .search-stats .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            display: block;
        }
        
        .search-stats .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        /* No Results Message */
        .no-results-message .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .no-results-message .fas {
            color: #6c757d;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .search-section .row > div {
                margin-bottom: 10px;
            }
            
            .search-stats .stat-item {
                margin: 0 10px;
            }
            
            .search-stats .stat-number {
                font-size: 1.2em;
            }
        }
    </style>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('tutorials') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('tutorials') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    
                                                
                                            <div class="section-header">
                                                <h2><i class="fas fa-graduation-cap me-3"></i><?= __('system_tutorials_user_guides') ?></h2>
                                                <p class="mb-0"><?= __('comprehensive_guides_for_using_all_features_of_the_almoqadas_management_system') ?></p>
                                            </div>
                                            
                                            <!-- Search Section -->
                                            <div class="search-section mb-4">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-6">
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text bg-primary text-white">
                                                                            <i class="fas fa-search"></i>
                                                                        </span>
                                                                    </div>
                                                                    <input type="text" class="form-control" id="tutorialSearch" 
                                                                           placeholder="<?= __('search_tutorials_placeholder') ?>" 
                                                                           onkeyup="filterTutorials()">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <select class="form-control" id="categoryFilter" onchange="filterTutorials()">
                                                                    <option value=""><?= __('all_categories') ?></option>
                                                                    <option value="tickets"><?= __('ticket_management') ?></option>
                                                                    <option value="hotel"><?= __('hotel_management') ?></option>
                                                                    <option value="visa"><?= __('visa_services') ?></option>
                                                                    <option value="umrah"><?= __('umrah_services') ?></option>
                                                                    <option value="sarafi"><?= __('sarafi_management') ?></option>
                                                                    <option value="jv"><?= __('jv_management') ?></option>
                                                                    <option value="finance"><?= __('finance_management') ?></option>
                                                                    <option value="creditor"><?= __('creditor_management') ?></option>
                                                                    <option value="debtor"><?= __('debtor_management') ?></option>
                                                                    <option value="additional"><?= __('additional_payments') ?></option>
                                                                    <option value="user"><?= __('user_management') ?></option>
                                                                    <option value="reports"><?= __('reports_system_management') ?></option>
                                                                    <option value="system"><?= __('system_management') ?></option>
                                                                    <option value="letter"><?= __('letter_management') ?></option>
                                                                    <option value="asset"><?= __('asset_management') ?></option>
                                                                    <option value="supplier"><?= __('supplier_management') ?></option>
                                                                    <option value="client"><?= __('client_management') ?></option>
                                                                    <option value="expense"><?= __('expense_management') ?></option>
                                                                    <option value="budget"><?= __('budget_allocation_management') ?></option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <button type="button" class="btn btn-outline-secondary btn-block" onclick="clearFilters()">
                                                                    <i class="fas fa-times"></i> <?= __('clear') ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle"></i> 
                                                                <?= __('search_tip') ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                                                        </div>
                                            
                                            <!-- Search Statistics -->
                                            <div class="search-stats" id="searchStats">
                                                <div class="stat-item">
                                                    <span class="stat-number" id="totalTutorials">0</span>
                                                    <span class="stat-label"><?= __('total_tutorials') ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <span class="stat-number" id="visibleTutorials">0</span>
                                                    <span class="stat-label"><?= __('visible_tutorials') ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <span class="stat-number" id="visibleCategories">0</span>
                                                    <span class="stat-label"><?= __('visible_categories') ?></span>
                                                </div>
                                            </div>
                                            
                                            <!-- No Results Message -->
                                            <div class="no-results-message" id="noResultsMessage" style="display: none;">
                                                <div class="card text-center py-5">
                                                    <div class="card-body">
                                                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                        <h4 class="text-muted"><?= __('no_tutorials_found') ?></h4>
                                                        <p class="text-muted"><?= __('try_different_search_terms') ?></p>
                                                        <button class="btn btn-primary" onclick="clearFilters()">
                                                            <i class="fas fa-refresh"></i> <?= __('clear_filters') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Ticket Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-ticket-alt me-2"></i> <?= __('ticket_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="tickets" data-title="<?= __('book_new_tickets') ?>" data-description="<?= __('learn_how_to_book_tickets_for_clients') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-tickets">
                                                                    <i class="fas fa-plus"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('book_new_tickets') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_book_tickets_for_clients') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('book-tickets')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="tickets" data-title="<?= __('refund_tickets') ?>" data-description="<?= __('process_ticket_refunds_and_cancellations') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-tickets">
                                                                    <i class="fas fa-undo"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('refund_tickets') ?></h5>
                                                                <p class="text-muted"><?= __('process_ticket_refunds_and_cancellations') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('refund-tickets')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="tickets" data-title="<?= __('change_ticket_dates') ?>" data-description="<?= __('modify_travel_dates_for_existing_tickets') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-tickets">
                                                                    <i class="fas fa-calendar-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('change_ticket_dates') ?></h5>
                                                                <p class="text-muted"><?= __('modify_travel_dates_for_existing_tickets') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('date-change')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="tickets" data-title="<?= __('add_weight_to_tickets') ?>" data-description="<?= __('manage_additional_baggage_weight') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-tickets">
                                                                    <i class="fas fa-weight"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('add_weight_to_tickets') ?></h5>
                                                                <p class="text-muted"><?= __('manage_additional_baggage_weight') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('ticket-weight')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="tickets" data-title="<?= __('reserve_tickets') ?>" data-description="<?= __('complete_process_for_ticket_reservations') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-tickets">
                                                                    <i class="fas fa-plane"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('reserve_tickets') ?></h5>
                                                                <p class="text-muted"><?= __('complete_process_for_ticket_reservations') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('ticket-reservations')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="tickets" data-title="<?= __('manage_reservation_transactions') ?>" data-description="<?= __('add_and_manage_payments_for_reservations') ?>"  >
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-tickets">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_reservation_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('add_and_manage_payments_for_reservations') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('manage-reservation-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Hotel Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-hotel me-2"></i> <?= __('hotel_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="hotel" data-title="<?= __('create_hotel_bookings') ?>" data-description="<?= __('learn_how_to_book_hotels_for_guests') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-hotel">
                                                                    <i class="fas fa-plus"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('create_hotel_bookings') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_book_hotels_for_guests') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('hotel-bookings')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="hotel" data-title="<?= __('manage_hotel_transactions') ?>" data-description="<?= __('handle_payments_for_hotel_bookings') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-hotel">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_hotel_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('handle_payments_for_hotel_bookings') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('hotel-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="hotel" data-title="<?= __('process_hotel_refunds') ?>" data-description="<?= __('handle_booking_cancellations_and_refunds') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-hotel">
                                                                    <i class="fas fa-undo"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('process_hotel_refunds') ?></h5>
                                                                <p class="text-muted"><?= __('handle_booking_cancellations_and_refunds') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('hotel-refunds')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Visa Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-passport me-2"></i> <?= __('visa_services') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="visa" data-title="<?= __('create_visa_applications') ?>" data-description="<?= __('learn_how_to_create_new_visa_applications') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-visa">
                                                                    <i class="fas fa-plus"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('create_visa_applications') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_create_new_visa_applications') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('visa-applications')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="visa" data-title="<?= __('manage_visa_transactions') ?>" data-description="<?= __('handle_payments_for_visa_applications') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-visa">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_visa_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('handle_payments_for_visa_applications') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('visa-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="visa" data-title="<?= __('process_visa_refunds') ?>" data-description="<?= __('handle_visa_application_cancellations') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-visa">
                                                                    <i class="fas fa-undo"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('process_visa_refunds') ?></h5>
                                                                <p class="text-muted"><?= __('handle_visa_application_cancellations') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('visa-refunds')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Umrah Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-users me-2"></i> <?= __('umrah_services') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="umrah" data-title="<?= __('manage_umrah_families') ?>" data-description="<?= __('learn_how_to_create_and_manage_umrah_family_groups') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-umrah">
                                                                    <i class="fas fa-users"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_umrah_families') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_create_and_manage_umrah_family_groups') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('umrah-family-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="umrah" data-title="<?= __('add_umrah_bookings') ?>" data-description="<?= __('learn_how_to_add_members_to_umrah_family_groups') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-umrah">
                                                                    <i class="fas fa-book"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('add_umrah_bookings') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_add_members_to_umrah_family_groups') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('umrah-booking-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="umrah" data-title="<?= __('manage_transactions') ?>" data-description="<?= __('learn_how_to_handle_umrah_booking_payments') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-umrah">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_handle_umrah_booking_payments') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('umrah-transaction-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="umrah" data-title="<?= __('process_refunds') ?>" data-description="<?= __('learn_how_to_handle_umrah_booking_refunds') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-umrah">
                                                                    <i class="fas fa-undo"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('process_refunds') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_handle_umrah_booking_refunds') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('umrah-refund-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="umrah" data-title="<?= __('generate_documents') ?>" data-description="<?= __('learn_how_to_create_umrah_related_documents') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-umrah">
                                                                    <i class="fas fa-file-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('generate_documents') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_create_umrah_related_documents') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('umrah-document-generation')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Sarafi Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('sarafi_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="sarafi" data-title="<?= __('sarafi_overview') ?>" data-description="<?= __('understand_sarafi_financial_management') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-money-bill-wave"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('sarafi_overview') ?></h5>
                                                                <p class="text-muted"><?= __('understand_sarafi_financial_management') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('sarafi-overview')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="sarafi" data-title="<?= __('deposits_withdrawals') ?>" data-description="<?= __('learn_customer_financial_transactions') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-exchange-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('deposits_withdrawals') ?></h5>
                                                                <p class="text-muted"><?= __('learn_customer_financial_transactions') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('sarafi-deposits-withdrawals')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="sarafi" data-title="<?= __('hawala_exchanges') ?>" data-description="<?= __('manage_transfers_and_currency_exchanges') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-globe"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('hawala_exchanges') ?></h5>
                                                                <p class="text-muted"><?= __('manage_transfers_and_currency_exchanges') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('sarafi-hawala-exchanges')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- JV Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('jv_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="jv" data-title="<?= __('jv_payments_overview') ?>" data-description="<?= __('understand_journal_voucher_payment_management') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-exchange-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('jv_payments_overview') ?></h5>
                                                                <p class="text-muted"><?= __('understand_journal_voucher_payment_management') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('jv-payments-overview')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="jv" data-title="<?= __('create_jv_payment') ?>" data-description="<?= __('learn_how_to_create_client_supplier_payments') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-plus-circle"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('create_jv_payment') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_create_client_supplier_payments') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('create-jv-payment')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="jv" data-title="<?= __('manage_jv_payments') ?>" data-description="<?= __('view_edit_and_manage_payment_details') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-eye"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_jv_payments') ?></h5>
                                                                <p class="text-muted"><?= __('view_edit_and_manage_payment_details') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('manage-jv-payment-details')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Finance Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('finance_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('main_account_management') ?>" data-description="<?= __('learn_how_to_manage_internal_accounts') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-briefcase"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('main_account_management') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_manage_internal_accounts') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('account-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('supplier_account_management') ?>" data-description="<?= __('manage_supplier_financial_accounts') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-users"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('supplier_account_management') ?></h5>
                                                                <p class="text-muted"><?= __('manage_supplier_financial_accounts') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('supplier-account-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('client_account_management') ?>" data-description="<?= __('manage_and_process_client_accounts') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-user-friends"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('client_account_management') ?></h5>
                                                                <p class="text-muted"><?= __('manage_and_process_client_accounts') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('client-account-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('account_transactions') ?>" data-description="<?= __('view_and_manage_transaction_histories') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-exchange-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('account_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('view_and_manage_transaction_histories') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('account-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('account_transfers') ?>" data-description="<?= __('transfer_balances_between_accounts') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-random"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('account_transfers') ?></h5>
                                                                <p class="text-muted"><?= __('transfer_balances_between_accounts') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('account-transfers')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Salary Management Section -->
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('salary_management') ?>" data-description="<?= __('learn_how_to_manage_employee_salaries') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-dollar-sign"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('salary_management') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_manage_employee_salaries') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('salary-management-overview')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('bonuses_deductions') ?>" data-description="<?= __('manage_employee_financial_adjustments') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-money-bill-wave"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('bonuses_deductions') ?></h5>
                                                                <p class="text-muted"><?= __('manage_employee_financial_adjustments') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('salary-bonuses-deductions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="finance" data-title="<?= __('payroll_reporting') ?>" data-description="<?= __('generate_and_print_payroll_reports') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-print"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('payroll_reporting') ?></h5>
                                                                <p class="text-muted"><?= __('generate_and_print_payroll_reports') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('payroll-reporting')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Creditor Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('creditor_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="creditor" data-title="<?= __('creditors_overview') ?>" data-description="<?= __('manage_and_track_creditor_accounts') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-users"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('creditors_overview') ?></h5>
                                                                <p class="text-muted"><?= __('manage_and_track_creditor_accounts') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('creditors-overview')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="creditor" data-title="<?= __('add_new_creditor') ?>" data-description="<?= __('learn_how_to_add_a_new_creditor') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-user-plus"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('add_new_creditor') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_add_a_new_creditor') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('add-new-creditor')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="creditor" data-title="<?= __('process_creditor_payments') ?>" data-description="<?= __('handle_payments_for_creditors') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('process_creditor_payments') ?></h5>
                                                                <p class="text-muted"><?= __('handle_payments_for_creditors') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('process-creditor-payment')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="creditor" data-title="<?= __('manage_creditor_transactions') ?>" data-description="<?= __('view_and_edit_transaction_history') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-list-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_creditor_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('view_and_edit_transaction_history') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('manage-creditor-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="creditor" data-title="<?= __('creditor_status_management') ?>" data-description="<?= __('manage_creditor_account_statuses') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-toggle-on"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('creditor_status_management') ?></h5>
                                                                <p class="text-muted"><?= __('manage_creditor_account_statuses') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('creditor-status-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Debtor Management Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('debtor_management') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="debtor" data-title="<?= __('debtors_overview') ?>" data-description="<?= __('manage_and_track_debtor_accounts') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-user-friends"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('debtors_overview') ?></h5>
                                                                <p class="text-muted"><?= __('manage_and_track_debtor_accounts') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('debtors-overview')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="debtor" data-title="<?= __('add_new_debtor') ?>" data-description="<?= __('learn_how_to_add_a_new_debtor') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-user-plus"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('add_new_debtor') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_add_a_new_debtor') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('add-new-debtor')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="debtor" data-title="<?= __('process_debtor_payments') ?>" data-description="<?= __('handle_payments_for_debtors') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('process_debtor_payments') ?></h5>
                                                                <p class="text-muted"><?= __('handle_payments_for_debtors') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('process-debtor-payment')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="debtor" data-title="<?= __('manage_debtor_transactions') ?>" data-description="<?= __('view_and_edit_transaction_history') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-list-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_debtor_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('view_and_edit_transaction_history') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('manage-debtor-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="debtor" data-title="<?= __('create_additional_payments') ?>" data-description="<?= __('step_by_step_guide_to_adding_new_payments') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-toggle-on"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('debtor_status_management') ?></h5>
                                                                <p class="text-muted"><?= __('manage_debtor_account_statuses') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('debtor-status-management')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Additional Payments Section -->
                                            <div class="category-section">
                                                <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('additional_payments') ?></h3>
                                                <div class="row">
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="additional" data-title="<?= __('additional_payments') ?>" data-description="<?= __('learn_how_to_manage_extra_financial_transactions') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-money-bill-wave"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('additional_payments') ?></h5>
                                                                <p class="text-muted"><?= __('learn_how_to_manage_extra_financial_transactions') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('additional-payments-overview')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="additional" data-title="<?= __('create_additional_payments') ?>" data-description="<?= __('step_by_step_guide_to_adding_new_payments') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-plus-circle"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('create_additional_payments') ?></h5>
                                                                <p class="text-muted"><?= __('step_by_step_guide_to_adding_new_payments') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('create-additional-payment')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 col-md-6 mb-4" data-category="additional" data-title="<?= __('manage_payment_transactions') ?>" data-description="<?= __('track_and_record_payment_transactions') ?>">
                                                        <div class="card tutorial-card">
                                                            <div class="card-body text-center">
                                                                <div class="tutorial-icon bg-finance">
                                                                    <i class="fas fa-exchange-alt"></i>
                                                                </div>
                                                                <h5 class="card-title"><?= __('manage_payment_transactions') ?></h5>
                                                                <p class="text-muted"><?= __('track_and_record_payment_transactions') ?></p>
                                                                <button class="btn btn-primary btn-sm" onclick="showTutorial('manage-additional-payment-transactions')"><?= __('view_guide') ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Administrative & Reporting Row -->
                                            <div class="row">
                                                <!-- User Management Section -->
                                                <div class="col-lg-4 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-university me-2"></i> <?= __('user_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="user" data-title="<?= __('user_management_overview') ?>" data-description="<?= __('understand_user_management_features') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-finance">
                                                                            <i class="fas fa-users"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('user_management_overview') ?></h5>
                                                                        <p class="text-muted"><?= __('understand_user_management_features') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('user-management-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Reports & System Section -->
                                                <div class="col-lg-8 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-chart-bar me-2"></i> <?= __('reports_system_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-lg-6 col-md-6 mb-4" data-category="reports" data-title="<?= __('export_reports') ?>" data-description="<?= __('generate_and_export_system_reports') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-reports">
                                                                            <i class="fas fa-file-export"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('export_reports') ?></h5>
                                                                        <p class="text-muted"><?= __('generate_and_export_system_reports') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('export-reports')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="col-lg-6 col-md-6 mb-4" data-category="reports" data-title="<?= __('database_backup') ?>" data-description="<?= __('create_and_manage_database_backups') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-system">
                                                                            <i class="fas fa-database"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('database_backup') ?></h5>
                                                                        <p class="text-muted"><?= __('create_and_manage_database_backups') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('db-backup')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Administrative Management Row -->
                                            <div class="row">
                                                <!-- System Management Section -->
                                                <div class="col-lg-4 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-cogs me-2"></i> <?= __('system_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="system" data-title="<?= __('file_browser') ?>" data-description="<?= __('manage_uploads_and_files') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-folder-open"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('file_browser') ?></h5>
                                                                        <p class="text-muted"><?= __('manage_uploads_and_files') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('file-browser-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Letter Management Section -->
                                                <div class="col-lg-4 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-file-alt me-2"></i> <?= __('letter_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="letter" data-title="<?= __('create_letters') ?>" data-description="<?= __('learn_how_to_create_official_letters') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-plus"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('create_letters') ?></h5>
                                                                        <p class="text-muted"><?= __('learn_how_to_create_official_letters') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('letter-management-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Asset Management Section -->
                                                <div class="col-lg-4 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-box me-2"></i> <?= __('asset_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="asset" data-title="<?= __('add_assets') ?>" data-description="<?= __('learn_how_to_add_new_company_assets') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-plus"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('add_assets') ?></h5>
                                                                        <p class="text-muted"><?= __('learn_how_to_add_new_company_assets') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('asset-management-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        
                                            <!-- Business Management Row -->
                                            <div class="row">
                                                <!-- Supplier Management Section -->
                                                <div class="col-lg-6 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-truck me-2"></i> <?= __('supplier_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="supplier" data-title="<?= __('add_suppliers') ?>" data-description="<?= __('learn_how_to_add_new_suppliers') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-plus"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('add_suppliers') ?></h5>
                                                                        <p class="text-muted"><?= __('learn_how_to_add_new_suppliers') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('supplier-management-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Client Management Section -->
                                                <div class="col-lg-6 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-users me-2"></i> <?= __('client_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="client" data-title="<?= __('add_clients') ?>" data-description="<?= __('learn_how_to_add_new_clients') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-plus"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('add_clients') ?></h5>
                                                                        <p class="text-muted"><?= __('learn_how_to_add_new_clients') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('client-management-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Financial Management Row -->
                                            <div class="row">
                                                <!-- Expense Management Section -->
                                                <div class="col-lg-6 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i> <?= __('expense_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="expense" data-title="<?= __('add_expenses') ?>" data-description="<?= __('learn_how_to_record_expenses') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-plus"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('add_expenses') ?></h5>
                                                                        <p class="text-muted"><?= __('learn_how_to_record_expenses') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('expense-management-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Budget Allocation Management Section -->
                                                <div class="col-lg-6 col-md-6 mb-4">
                                                    <div class="category-section">
                                                        <h3 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i> <?= __('budget_allocation_management') ?></h3>
                                                        <div class="row">
                                                            <div class="col-12" data-category="budget" data-title="<?= __('create_allocations') ?>" data-description="<?= __('learn_how_to_create_budget_allocations') ?>">
                                                                <div class="card tutorial-card">
                                                                    <div class="card-body text-center">
                                                                        <div class="tutorial-icon bg-primary">
                                                                            <i class="fas fa-plus"></i>
                                                                        </div>
                                                                        <h5 class="card-title"><?= __('create_allocations') ?></h5>
                                                                        <p class="text-muted"><?= __('learn_how_to_create_budget_allocations') ?></p>
                                                                        <button class="btn btn-primary btn-sm" onclick="showTutorial('budget-allocation-overview')"><?= __('view_guide') ?></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Tutorial Content Sections (Hidden by default) -->
                                            <?php include 'tutorial_content.php'; ?> 
                                       
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
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
                                    <label class="text-muted mb-1"><?= __('email') ?></label>
                                    <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('phone') ?></label>
                                    <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                    <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('address') ?></label>
                                    <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 mt-3">
                            <h6 class="mb-3"><?= __('account_information') ?></h6>
                            <div class="activity-timeline">
                                <div class="timeline-item">
                                    <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                    <div class="timeline-content">
                                        <p class="mb-0"><?= __('account_created') ?></p>
                                        <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    
                </div>
            </div>
        </div>
    </div>


    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <form id="updateProfileForm" enctype="multipart/form-data">
                <div class="modal-content shadow-lg border-0">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title">
                            <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
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
                                <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                            </div>

                            <!-- Right Column - Form Fields -->
                            <div class="col-md-8">
                                <!-- Personal Info Section -->
                                <div class="settings-section active" id="personalInfo">
                                    <h6 class="text-primary mb-3">
                                        <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                    </h6>
                                    <div class="form-group floating-label">
                                        <input type="text" class="form-control" id="updateName" name="name" 
                                            value="<?= htmlspecialchars($user['name']) ?>" required>
                                        <label for="updateName"><?= __('full_name') ?></label>
                                    </div>
                                    <div class="form-group floating-label">
                                        <input type="email" class="form-control" id="updateEmail" name="email" 
                                            value="<?= htmlspecialchars($user['email']) ?>" required>
                                        <label for="updateEmail"><?= __('email_address') ?></label>
                                    </div>
                                    <div class="form-group floating-label">
                                        <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                            value="<?= htmlspecialchars($user['phone']) ?>">
                                        <label for="updatePhone"><?= __('phone_number') ?></label>
                                    </div>
                                    <div class="form-group floating-label">
                                        <textarea class="form-control" id="updateAddress" name="address" 
                                                rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                        <label for="updateAddress"><?= __('address') ?></label>
                                    </div>
                                </div>

                                <!-- Password Section -->
                                <div class="settings-section mt-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                    </h6>
                                    <div class="form-group floating-label">
                                        <input type="password" class="form-control" id="currentPassword" 
                                            name="current_password">
                                        <label for="currentPassword"><?= __('current_password') ?></label>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group floating-label">
                                                <input type="password" class="form-control" id="newPassword" 
                                                    name="new_password">
                                                <label for="newPassword"><?= __('new_password') ?></label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group floating-label">
                                                <input type="password" class="form-control" id="confirmPassword" 
                                                    name="confirm_password">
                                                <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
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
                                    <!-- Add Bootstrap-select JavaScript -->
                                    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
                                    

                                    <script>
        function showTutorial(tutorialId) {
            // Hide all tutorial content
            const allContent = document.querySelectorAll('.tutorial-content');
            allContent.forEach(content => {
                content.style.display = 'none';
            });
            
            // Show selected tutorial
            const selectedContent = document.getElementById(tutorialId);
            if (selectedContent) {
                selectedContent.style.display = 'block';
                selectedContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        function hideTutorial(tutorialId) {
            const content = document.getElementById(tutorialId);
            if (content) {
                content.style.display = 'none';
            }
        }
        
        function filterTutorials() {
            const searchTerm = document.getElementById('tutorialSearch').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const tutorialCards = document.querySelectorAll('[data-category]');
            
            let visibleCount = 0;
            const visibleCategories = new Set();
            
            tutorialCards.forEach(card => {
                const category = card.getAttribute('data-category');
                const title = card.getAttribute('data-title').toLowerCase();
                const description = card.getAttribute('data-description').toLowerCase();
                
                let showCard = true;
                
                // Category filter
                if (categoryFilter && category !== categoryFilter) {
                    showCard = false;
                }
                
                // Search term filter
                if (searchTerm && !title.includes(searchTerm) && !description.includes(searchTerm)) {
                    showCard = false;
                }
                
                // Show/hide card
                if (showCard) {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                    visibleCount++;
                    visibleCategories.add(category);
                } else {
                    card.style.display = 'none';
                    card.classList.remove('fade-in');
                }
            });
            
            // Update section visibility
            updateSectionVisibility();
            
            // Update statistics
            updateSearchStats(visibleCount, visibleCategories.size);
            
            // Show/hide no results message
            const noResultsMessage = document.getElementById('noResultsMessage');
            if (visibleCount === 0 && (searchTerm || categoryFilter)) {
                noResultsMessage.style.display = 'block';
            } else {
                noResultsMessage.style.display = 'none';
            }
        }
        
        function updateSectionVisibility() {
            const sections = document.querySelectorAll('.category-section');
            sections.forEach(section => {
                const visibleCards = section.querySelectorAll('[data-category]:not([style*="display: none"])');
                if (visibleCards.length === 0) {
                    section.style.display = 'none';
                } else {
                    section.style.display = 'block';
                }
            });
        }
        
        function clearFilters() {
            document.getElementById('tutorialSearch').value = '';
            document.getElementById('categoryFilter').value = '';
            filterTutorials();
        }
        
        function updateSearchStats(visibleCount, visibleCategories) {
            const totalTutorials = document.querySelectorAll('[data-category]').length;
            
            document.getElementById('totalTutorials').textContent = totalTutorials;
            document.getElementById('visibleTutorials').textContent = visibleCount;
            document.getElementById('visibleCategories').textContent = visibleCategories;
            
            // Update stats styling based on results
            const searchStats = document.getElementById('searchStats');
            if (visibleCount === 0 && (document.getElementById('tutorialSearch').value || document.getElementById('categoryFilter').value)) {
                searchStats.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%)';
            } else if (visibleCount < totalTutorials) {
                searchStats.style.background = 'linear-gradient(135deg, #feca57 0%, #ff9ff3 100%)';
            } else {
                searchStats.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
        }
        
        // Close tutorial when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('tutorial-content')) {
                e.target.style.display = 'none';
            }
        });
        
        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            filterTutorials();
        });
    </script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>