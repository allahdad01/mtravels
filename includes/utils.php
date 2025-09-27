<?php
/**
 * Utility functions for the application
 * @package AlMoqadas
 */

/**
 * Safely get array value with default
 * @param array $array The array to get value from
 * @param string $key The key to look for
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The value or default
 */
function getValue($array, $key, $default = 'N/A') {
    if (!is_array($array)) {
        return $default;
    }
    
    if (!isset($array[$key])) {
        return $default;
    }
    
    if (empty($array[$key]) && $array[$key] !== 0 && $array[$key] !== '0') {
        return $default;
    }
    
    return htmlspecialchars($array[$key]);
}

/**
 * Format date in a consistent way
 * @param string $date The date string
 * @param string $format The desired format
 * @return string Formatted date or N/A
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) {
        return 'N/A';
    }
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Format currency amount
 * @param float $amount The amount to format
 * @param string $currency The currency code
 * @return string Formatted amount with currency
 */
function formatAmount($amount, $currency = 'USD') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Generate pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern for pagination links
 * @return string Generated pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern = '?page=') {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<ul class="pagination pagination-sm mb-0">';

    // Previous button
    $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevDisabled . '">
                <a class="page-link" href="' . $urlPattern . ($currentPage - 1) . '" tabindex="-1">
                    <i class="feather icon-chevron-left"></i>
                </a>
              </li>';

    // Page numbers
    $maxPages = 5; // Maximum number of page links to show
    $startPage = max(1, min($currentPage - floor($maxPages / 2), $totalPages - $maxPages + 1));
    $endPage = min($startPage + $maxPages - 1, $totalPages);

    // First page
    if ($startPage > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $urlPattern . '1">1</a>
                  </li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled">
                        <span class="page-link">...</span>
                     </li>';
        }
    }

    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $activeClass = ($i == $currentPage) ? ' active' : '';
        $html .= '<li class="page-item' . $activeClass . '">
                    <a class="page-link" href="' . $urlPattern . $i . '">' . $i . '</a>
                  </li>';
    }

    // Last page
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled">
                        <span class="page-link">...</span>
                     </li>';
        }
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $urlPattern . $totalPages . '">' . $totalPages . '</a>
                  </li>';
    }

    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextDisabled . '">
                <a class="page-link" href="' . $urlPattern . ($currentPage + 1) . '">
                    <i class="feather icon-chevron-right"></i>
                </a>
              </li>';

    $html .= '</ul>';
    return $html;
}

/**
 * Generate page info text
 * @param int $currentPage Current page number
 * @param int $itemsPerPage Items per page
 * @param int $totalItems Total number of items
 * @return string Generated page info text
 */
function generatePageInfo($currentPage, $itemsPerPage, $totalItems) {
    if ($totalItems <= 0) {
        return 'No items found';
    }

    $startItem = (($currentPage - 1) * $itemsPerPage) + 1;
    $endItem = min($currentPage * $itemsPerPage, $totalItems);

    return sprintf('Showing %d to %d of %d entries', 
        $startItem,
        $endItem,
        $totalItems
    );
} 