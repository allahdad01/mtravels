<?php
/**
 * Feature Selector Component
 * Reusable drag & drop feature selection UI for custom plan creation
 */

define('CUSTOM_FEATURE_CATEGORIES', [
    'ticket_management' => [
        'title' => 'Ticket Management',
        'icon' => 'ticket',
        'features' => [
            'ticket_bookings' => 'Ticket Bookings',
            'ticket_reservations' => 'Ticket Reservations',
            'refunded_tickets' => 'Refunded Tickets',
            'date_change_tickets' => 'Date Change Tickets',
            'ticket_weights' => 'Ticket Weights'
        ]
    ],
    'hotel_services' => [
        'title' => 'Hotel Services',
        'icon' => 'hotel',
        'features' => [
            'hotel_bookings' => 'Hotel Bookings',
            'hotel_refunds' => 'Hotel Refunds'
        ]
    ],
    'visa_services' => [
        'title' => 'Visa Services',
        'icon' => 'visa',
        'features' => [
            'visa_applications' => 'Visa Applications',
            'visa_refunds' => 'Visa Refunds',
            'visa_transactions' => 'Visa Transactions'
        ]
    ],
    'umrah_services' => [
        'title' => 'Umrah Services',
        'icon' => 'umrah',
        'features' => [
            'family_management' => 'Family Management',
            'member_management' => 'Member Management',
            'id_card_generation' => 'ID Card Generation',
            'agreement_generation' => 'Agreement Generation',
            'cancellation_generation' => 'Cancellation Generation',
            'refund_processing' => 'Refund Processing',
            'payment_processing' => 'Payment Processing',
            'multi_currency' => 'Multi-Currency Support'
        ]
    ],
    'financial_management' => [
        'title' => 'Financial Management',
        'icon' => 'finance',
        'features' => [
            'debtors' => 'Debtors Management',
            'creditors' => 'Creditors Management',
            'sarafi' => 'Sarafi (Exchange)',
            'salary' => 'Salary Management',
            'additional_payments' => 'Additional Payments',
            'jv_payments' => 'JV Payments',
            'financial_statements' => 'Financial Statements'
        ]
    ],
    'business_operations' => [
        'title' => 'Business Operations',
        'icon' => 'maktob',
        'features' => [
            'manage_maktobs' => 'Maktob Management',
            'assets' => 'Asset Management',
            'expense_management' => 'Expense Management',
            'customer_management' => 'Customer Management',
            'supplier_management' => 'Supplier Management'
        ]
    ],
    'communication' => [
        'title' => 'Communication',
        'icon' => 'communication',
        'features' => [
            'inter_tenant_chat' => 'Inter-Tenant Chat',
            'email_notifications' => 'Email Notifications',
            'whatsapp_integration' => 'WhatsApp Integration'
        ]
    ],
    'reporting_analytics' => [
        'title' => 'Reporting & Analytics',
        'icon' => 'dashboard',
        'features' => [
            'business_analytics' => 'Business Analytics',
            'reporting_system' => 'Reporting System',
            'activity_logging' => 'Activity Logging',
            'dashboard_analytics' => 'Dashboard Analytics',
            'invoice_generation' => 'Invoice Generation'
        ]
    ],
    'advanced_features' => [
        'title' => 'Advanced Features',
        'icon' => 'automation',
        'features' => [
            'user_management' => 'User Management',
            'financial_data_export' => 'Financial Data Export',
            'multi_branch' => 'Multi-Branch Support',
            'roles_permissions' => 'Roles & Permissions'
        ]
    ]
]);

function getCustomFeatureCategories() {
    return CUSTOM_FEATURE_CATEGORIES;
}

function getCategorySvgIcon($iconKey) {
    $icons = [
        'ticket' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>',
        'hotel' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>',
        'visa' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M12 9v3"/><path d="M16 9v3"/></svg>',
        'umrah' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
        'finance' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'maktob' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'communication' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'dashboard' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'automation' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/></svg>',
    ];
    return $icons[$iconKey] ?? $icons['dashboard'];
}

function renderFeatureSelector($categories = null) {
    if ($categories === null) {
        $categories = CUSTOM_FEATURE_CATEGORIES;
    }
    $html = '';
    foreach ($categories as $catKey => $category) {
        $html .= '<div class="cp-category" data-category="' . htmlspecialchars($catKey) . '">';
        $html .= '<div class="cp-category-header">';
        $html .= '<span class="cp-category-icon">' . getCategorySvgIcon($category['icon']) . '</span>';
        $html .= '<h3 class="cp-category-title">' . htmlspecialchars($category['title']) . '</h3>';
        $html .= '<span class="cp-category-count">0</span>';
        $html .= '</div>';
        $html .= '<div class="cp-category-features">';
        foreach ($category['features'] as $featKey => $featLabel) {
            $html .= '<div class="cp-feature" data-feature="' . htmlspecialchars($featKey) . '" draggable="true">';
            $html .= '<span class="cp-feature-icon">+</span>';
            $html .= '<span class="cp-feature-label">' . htmlspecialchars($featLabel) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }
    return $html;
}

function renderFeatureSelectorScript() {
    return <<<'JS'
<script>
(function() {
    let selectedFeatures = new Set();

    function updateCategoryCounts() {
        document.querySelectorAll('.cp-category').forEach(cat => {
            const feats = cat.querySelectorAll('.cp-feature');
            let count = 0;
            feats.forEach(f => { if (f.classList.contains('selected')) count++; });
            cat.querySelector('.cp-category-count').textContent = count;
        });
    }

    function updateSelectedList() {
        const container = document.getElementById('cp-selected-features');
        const empty = document.getElementById('cp-selected-empty');
        const hidden = document.getElementById('cp-selected-features-input');
        const countEl = document.getElementById('cp-selected-count');
        if (!container) return;

        const features = Array.from(selectedFeatures);
        if (features.length === 0) {
            container.innerHTML = '';
            if (empty) empty.style.display = 'block';
            if (countEl) countEl.textContent = '0';
            if (hidden) hidden.value = '';
            return;
        }

        if (empty) empty.style.display = 'none';
        if (countEl) countEl.textContent = features.length;

        const allCategories = {};
        document.querySelectorAll('.cp-feature').forEach(el => {
            const cat = el.closest('.cp-category');
            if (cat) {
                const catKey = cat.dataset.category;
                if (!allCategories[catKey]) {
                    const title = cat.querySelector('.cp-category-title')?.textContent || catKey;
                    allCategories[catKey] = title;
                }
            }
        });

        let html = '';
        const cats = {};
        features.forEach(key => {
            const el = document.querySelector(`.cp-feature[data-feature="${key}"]`);
            const cat = el?.closest('.cp-category');
            const catKey = cat?.dataset.category || 'other';
            const label = el?.querySelector('.cp-feature-label')?.textContent || key;
            if (!cats[catKey]) {
                cats[catKey] = { title: allCategories[catKey] || catKey, items: [] };
            }
            cats[catKey].items.push({ key, label });
        });

        for (const [catKey, cat] of Object.entries(cats)) {
            html += '<div class="cp-selected-group">';
            html += `<div class="cp-selected-group-title">${cat.title}</div>`;
            cat.items.forEach(item => {
                html += `<div class="cp-selected-item" data-feature="${item.key}">
                    <span class="cp-selected-item-label">${item.label}</span>
                    <button type="button" class="cp-selected-item-remove" data-feature="${item.key}" title="Remove">&times;</button>
                </div>`;
            });
            html += '</div>';
        }

        container.innerHTML = html;
        if (hidden) hidden.value = JSON.stringify(features);

        container.querySelectorAll('.cp-selected-item-remove').forEach(btn => {
            btn.addEventListener('click', function() {
                const key = this.dataset.feature;
                selectedFeatures.delete(key);
                const el = document.querySelector(`.cp-feature[data-feature="${key}"]`);
                if (el) el.classList.remove('selected');
                updateCategoryCounts();
                updateSelectedList();
            });
        });
    }

    function initDragDrop() {
        document.querySelectorAll('.cp-feature').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const key = this.dataset.feature;
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    selectedFeatures.delete(key);
                } else {
                    this.classList.add('selected');
                    selectedFeatures.add(key);
                }
                updateCategoryCounts();
                updateSelectedList();
            });

            el.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('text/plain', this.dataset.feature);
                this.classList.add('dragging');
            });
            el.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
            });
        });

        const dropZone = document.getElementById('cp-selected-area');
        if (dropZone) {
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });
            dropZone.addEventListener('dragleave', function(e) {
                this.classList.remove('drag-over');
            });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                const key = e.dataTransfer.getData('text/plain');
                if (key && !selectedFeatures.has(key)) {
                    selectedFeatures.add(key);
                    const el = document.querySelector(`.cp-feature[data-feature="${key}"]`);
                    if (el) el.classList.add('selected');
                    updateCategoryCounts();
                    updateSelectedList();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initDragDrop();

        const form = document.getElementById('cp-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const hidden = document.getElementById('cp-selected-features-input');
                if (!hidden || !hidden.value || JSON.parse(hidden.value).length === 0) {
                    e.preventDefault();
                    alert('Please select at least one feature for your custom plan.');
                    document.getElementById('cp-selected-area')?.scrollIntoView({ behavior: 'smooth' });
                    return;
                }
            });
        }

        document.getElementById('cp-select-all')?.addEventListener('click', function() {
            document.querySelectorAll('.cp-feature:not(.selected)').forEach(el => {
                el.classList.add('selected');
                selectedFeatures.add(el.dataset.feature);
            });
            updateCategoryCounts();
            updateSelectedList();
        });

        document.getElementById('cp-clear-all')?.addEventListener('click', function() {
            document.querySelectorAll('.cp-feature.selected').forEach(el => {
                el.classList.remove('selected');
            });
            selectedFeatures.clear();
            updateCategoryCounts();
            updateSelectedList();
        });
    });
})();
</script>
JS;
}
