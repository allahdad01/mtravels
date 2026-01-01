<?php
/**
 * Pricing Helper Functions
 * Handles all pricing-related logic and rendering
 */

// Define pricing feature groups as constants
define('PRICING_FEATURE_GROUPS', [
    'ticket_management' => [
        'title' => 'Ticket Management',
        'features' => ['ticket_bookings', 'ticket_reservations', 'refunded_tickets', 'date_change_tickets', 'ticket_weights']
    ],
    'hotel_services' => [
        'title' => 'Hotel Services',
        'features' => ['hotel_bookings', 'hotel_refunds']
    ],
    'visa_services' => [
        'title' => 'Visa Services',
        'features' => ['visa_applications', 'visa_refunds', 'visa_transactions']
    ],
    'umrah_services' => [
        'title' => 'Umrah Services',
        'features' => ['family_management', 'member_management', 'id_card_generation', 'agreement_generation', 'cancellation_generation', 'refund_processing', 'payment_processing', 'multi_currency']
    ],
    'financial_management' => [
        'title' => 'Financial Management',
        'features' => ['debtors', 'creditors', 'sarafi', 'salary', 'additional_payments', 'jv_payments', 'financial_statements']
    ],
    'business_operations' => [
        'title' => 'Business Operations',
        'features' => ['manage_maktobs', 'assets', 'expense_management', 'customer_management', 'supplier_management']
    ],
    'communication' => [
        'title' => 'Communication',
        'features' => ['inter_tenant_chat']
    ],
    'reporting_analytics' => [
        'title' => 'Reporting & Analytics',
        'features' => ['business_analytics', 'reporting_system', 'activity_logging', 'dashboard_analytics', 'invoice_generation']
    ],
    'advanced_features' => [
        'title' => 'Advanced Features',
        'features' => ['user_management', 'financial_data_export']
    ]
]);

// Define special plan configurations
define('SPECIAL_PLAN_CONFIGS', [
    'umrah' => [
        'display_groups' => ['umrah_services', 'financial_management', 'business_operations', 'communication'],
        'specific_features' => [
            'umrah_services' => ['family_management', 'member_management', 'id_card_generation', 'agreement_generation', 'cancellation_generation', 'refund_processing', 'payment_processing', 'multi_currency'],
            'financial_management' => ['financial_statements'],
            'business_operations' => ['expense_management'],
            'communication' => ['inter_tenant_chat']
        ]
    ]
]);

/**
 * Get feature groups configuration
 */
function getPricingFeatureGroups() {
    return PRICING_FEATURE_GROUPS;
}

/**
 * Get special plan configuration
 */
function getSpecialPlanConfig($planName) {
    $planName = strtolower($planName);
    return SPECIAL_PLAN_CONFIGS[$planName] ?? null;
}

/**
 * Parse and normalize plan features
 */
function getPlanFeatures($plan) {
    $features = json_decode($plan['features'], true) ?? [];
    $planName = strtolower($plan['name']);
    
    // Add Umrah features to Enterprise plan
    if ($planName === 'enterprise') {
        $umrahFeatures = PRICING_FEATURE_GROUPS['umrah_services']['features'];
        $features = array_merge($features, $umrahFeatures);
    }
    
    return $features;
}

/**
 * Render a feature group with features
 */
function renderFeatureGroup($title, $features) {
    $featureItems = implode('', array_map(function($feature) {
        return '<li class="feature-item">' . htmlspecialchars(formatFeatureName($feature)) . '</li>';
    }, $features));
    
    return sprintf(
        '<div class="feature-group"><h5 class="feature-group-title">%s</h5><ul class="feature-group-list">%s</ul></div>',
        htmlspecialchars($title),
        $featureItems
    );
}

/**
 * Render features for special plans (Umrah)
 */
function renderSpecialPlanFeatures($planName) {
    $config = getSpecialPlanConfig($planName);
    if (!$config) {
        return '';
    }
    
    $featureGroups = getPricingFeatureGroups();
    $html = '';
    
    foreach ($config['display_groups'] as $groupKey) {
        if (!isset($featureGroups[$groupKey])) {
            continue;
        }
        
        $group = $featureGroups[$groupKey];
        $featuresToShow = $config['specific_features'][$groupKey] ?? $group['features'];
        $html .= renderFeatureGroup($group['title'], $featuresToShow);
    }
    
    return $html;
}

/**
 * Get additional features for a plan (compared to previous plan)
 */
function getAdditionalFeatures($currentPlanFeatures, $previousPlanFeatures) {
    return array_diff($currentPlanFeatures, $previousPlanFeatures);
}

/**
 * Render additional features grouped by category
 */
function renderAdditionalFeaturesGrouped($additionalFeatures, $previousPlanName) {
    $featureGroups = getPricingFeatureGroups();
    
    $html = '<div class="feature-group">';
    $html .= '<h5 class="feature-group-title" style="color: var(--primary); font-weight: 700;">Everything in ' . htmlspecialchars(formatFeatureName($previousPlanName)) . ' plus:</h5>';
    $html .= '<ul class="feature-group-list">';
    
    foreach ($featureGroups as $group) {
        $groupAdditionalFeatures = array_intersect($group['features'], $additionalFeatures);
        if (!empty($groupAdditionalFeatures)) {
            $html .= '<li class="feature-item" style="font-weight: 600; color: var(--primary);">' . htmlspecialchars($group['title']) . ':</li>';
            foreach ($groupAdditionalFeatures as $feature) {
                $html .= '<li class="feature-item" style="margin-left: 1rem;">' . htmlspecialchars(formatFeatureName($feature)) . '</li>';
            }
        }
    }
    
    $html .= '</ul></div>';
    return $html;
}

/**
 * Render features for basic plan (first plan)
 */
function renderBasicPlanFeatures($planFeatures) {
    $featureGroups = getPricingFeatureGroups();
    $html = '';
    
    foreach ($featureGroups as $group) {
        $availableFeatures = array_intersect($group['features'], $planFeatures);
        if (!empty($availableFeatures)) {
            $html .= renderFeatureGroup($group['title'], $availableFeatures);
        }
    }
    
    return $html;
}

/**
 * Render account and support section
 */
function renderAccountSupport($plan, $index, $plans) {
    $html = '<div class="feature-group">';
    $html .= '<h5 class="feature-group-title">Account & Support</h5>';
    $html .= '<ul class="feature-group-list">';
    
    if ($index > 0) {
        $previousPlan = $plans[$index - 1];
        $additionalUsers = $plan['max_users'] - $previousPlan['max_users'];
        if ($additionalUsers > 0) {
            $html .= '<li class="feature-highlight">Up to ' . htmlspecialchars($plan['max_users']) . ' users (' . $additionalUsers . ' more than ' . htmlspecialchars(formatFeatureName($previousPlan['name'])) . ')</li>';
        } else {
            $html .= '<li class="feature-highlight">Up to ' . htmlspecialchars($plan['max_users']) . ' users</li>';
        }
    } else {
        $html .= '<li class="feature-highlight">Up to ' . htmlspecialchars($plan['max_users']) . ' users</li>';
    }
    
    $html .= '<li class="feature-highlight">' . htmlspecialchars($plan['trial_days']) . ' day free trial</li>';
    $html .= '</ul></div>';
    
    return $html;
}

/**
 * Render complete pricing card features section
 */
function renderPricingCardFeatures($plan, $index, $plans) {
    $planName = strtolower($plan['name']);
    $planFeatures = getPlanFeatures($plan);
    $html = '';
    
    // Handle special plans
    if ($planName === 'umrah') {
        $html .= renderSpecialPlanFeatures($planName);
    }
    // For plans after index 1, show additional features only
    elseif ($index > 1) {
        $previousPlan = $plans[$index - 1];
        $previousPlanFeatures = getPlanFeatures($previousPlan);
        $additionalFeatures = getAdditionalFeatures($planFeatures, $previousPlanFeatures);
        $html .= renderAdditionalFeaturesGrouped($additionalFeatures, $previousPlan['name']);
    }
    // For basic plan (index 0 or 1), show all features
    else {
        $html .= renderBasicPlanFeatures($planFeatures);
    }
    
    // Always add account & support section
    $html .= renderAccountSupport($plan, $index, $plans);
    
    return $html;
}
